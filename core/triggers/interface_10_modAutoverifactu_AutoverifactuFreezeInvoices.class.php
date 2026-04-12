<?php

/* Copyright (C) 2023       Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2025		Lucas García			<lucas@codeccoop.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modAutoverifactu_AutoverifactuTriggers.class.php
 * \ingroup autoverifactu
 * \brief   Example of trigger file.
 *
 * You can create other triggered files by copying this one.
 * - File name should be either:
 *      - interface_99_modAutoverifactu_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMyTrigger
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

require_once dirname(__DIR__, 2) . '/lib/autoverifactu.lib.php';
require_once dirname(__DIR__, 2) . '/lib/verifactu.lib.php';
require_once dirname(__DIR__, 2) . '/lib/validation.lib.php';


/**
 *  Class of triggers for Autoverifactu module
 */
class InterfaceAutoverifactuFreezeInvoices extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        parent::__construct($db);
        $this->family = 'financial';
        $this->description = 'Auto-Veri*Factu triggers';
        $this->version = self::VERSIONS['dev'];
        $this->picto = 'autoverifactu@autoverifactu';
    }

    /**
     * Function called when a Dolibarr business event is done.
     * All functions "runTrigger" are triggered if the file is inside the directory core/triggers
     *
     * @param string        $action     Event action code
     * @param CommonObject  $object     Object
     * @param User          $user       Object user
     * @param Translate     $langs      Object langs
     * @param Conf          $conf       Object conf
     *
     * @return int                      Return integer <0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if (!autoverifactuEnabled() && $action !== 'USER_LOGOUT') {
            return 0;
        }

        static $context;

        /**
         * Tracked invoices types:
         *   0: Default ✓
         *   1: Replacement ✓
         *   2: Credit note ✓
         *   3: Down payment ✓
         *   4: Proforma ✕
         *   5: Situation ✕
         *
         * Invoice status:
         *   0: Draft
         *   1: Validated
         *   2: Closed
         *   3: Abandoned
         */

        // TODO: Handle donations.
        // As far as i know, they have to be declared as invoices to the AEAT, it isn't?
        switch ($action) {
            case 'BILL_CREATE':
                if (isset($object->context['createfromclone'])) {
                    $object->array_options['options_verifactu_tms'] = null;
                    $object->array_options['options_verifactu_hash'] = null;
                    $object->array_options['options_verifactu_error'] = null;

                    $result = $object->insertExtraFields();
                    if ($result < 0) {
                        return $result;
                    }
                }

                if (!$context) {
                    $context = new stdClass;
                    $context->invoice = $object;
                }

                if (
                    $object->origin
                    && $object->origin_id
                    && getDolGlobalInt('AUTOVERIFACTU_SPLIT_INVOICES')
                ) {
                    global $db, $user;

                    if (isset($context->origin) && $context->origin->element === $object->origin) {
                        return;
                    }

                    if ($object->origin === 'propal') {
                        dol_include_once('/comm/propal/class/propal.class.php');
                        $sourceObject = new Propal($db);
                    } elseif ($object->origin_type === 'order' || $object->origin_type === 'commande') {
                        dol_include_once('/commande/class/commande.class.php');
                        $sourceObject = new Commande($db);
                    } elseif ($object->origin_type === 'contrat' || $object->origin_type === 'contract') {
                        dol_include_once('/contrat/class/contrat.class.php');
                        $sourceObject = new Contrat($db);
                    } elseif ($object->origin_type === 'shipping') {
                        dol_include_once('/expedition/class/expedition.class.php');
                        $sourceObject = new Expedition($db);
                    } elseif ($object->origin_type === 'fichinter') {
                        dol_include_once('/fichinter/class/fichinter.class.php');
                        $sourceObject = new Fichinter($db);
                    }

                    if (!isset($sourceObject)) {
                        return;
                    }

                    $sourceObject->fetch($object->origin_id);
                    $sourceObject->fetch_lines();

                    $context->origin = $sourceObject;

                    $sourceLines = count($sourceObject->lines);
                    if ($sourceLines > 12) {
                        $context->splitInvoice = true;

                        $sourceLines -= 12;

                        while ($sourceLines >= 0) {
                            $siblingId = $object->createFromCurrent($user);
                            if ($siblingId < 0) {
                                dol_syslog('Unable to create a copy of the invoice in the split invoices loop', LOG_ERR);
                                $this->errors[] = $langs->trans('SplitInvoiceError');
                                return $siblingId;
                            }

                            $result = $object->add_object_linked('facture', $siblingId);
                            if ($result < 0) {
                                dol_syslog('Unable to link the partial invoice to the source invoice', LOG_ERR);
                                $this->errors[] = $langs->trans('SplitInvoiceError');
                                return $result;
                            }

                            $result = $sourceObject->add_object_linked('facture', $siblingId);
                            if ($result < 0) {
                                dol_syslog('Unable to link the partial invoice to the source entity', LOG_ERR);
                                $this->errors[] = $langs->trans('SplitInvoiceError');
                                return $result;
                            }

                            $sourceLines -= 12;
                        }
                    }
                }

                break;
            case 'BILL_CANCEL':
                $trigger = $_GET['action'] ?? '';
                $facid = $_GET['facid'] ?? INF;

                // If it's triggered by a replacment invoice, skip the cancel record registration.
                if ($trigger === 'confirm_valid' && $facid > $object->id) {
                    return 0;
                }

                $result = autoverifactuRegisterInvoice($object, $action);
                if ($result < 0) {
                    dol_syslog('Error while sending a cancel record to the Veri*Factu API', LOG_ERR);
                    $this->errors[] = $langs->trans('CancelRecordFail');
                }

                return $result;
            case 'BILL_VALIDATE':
            // case 'DON_VALIDATE':
                $object->fetch_lines();
                if (is_array($object->lines) && count($object->lines) > 12) {
                    dol_syslog('Veri*Factu bans invoices with more than 12 lines', LOG_INFO);
                    $this->errors[] = $langs->trans('MaxInvoiceLinesError');
                    return -1;
                }

                $result = autoverifactuRegisterInvoice($object, $action);

                if ($result < 0) {
                    $this->errors[] = $langs->trans('RecordCreationFail');
                }

                return $result;
            case 'BILL_UNVALIDATE':
            case 'BILL_UNPAYED':
                if ($object->type <= Facture::TYPE_DEPOSIT) {
                    dol_syslog('Veri*Factu disables invoice unvalidations', LOG_INFO);
                    $this->errors[] = $langs->trans('ValidatedNotEditable');
                    return -1;
                }

                break;
            case 'BILL_DELETE':
            // case 'DON_DELETE':
                if (
                    $object->status != Facture::STATUS_DRAFT
                    && $object->type <= Facture::TYPE_DEPOSIT
                ) {
                    dol_syslog('Veri*Factu disables validated invoices removals', LOG_INFO);
                    $this->errors[] = $langs->trans('ValidatedNotDeletable');
                    return -1;
                }

                break;
            // TODO: Handle subsanaciones
            // NOTE: El protocolo contempla la subsanación de facturas en los casos en los que no
            // sea necesaria una emisión rectificativa.
            case 'BILL_MODIFY':
            // case 'DON_MODIFY':
                if (
                    $object->status != Facture::STATUS_DRAFT
                    && $object->type <= Facture::TYPE_DEPOSIT
                ) {
                    dol_syslog('Veri*Factu disables validated invoices edits', LOG_INFO);
                    $this->errors[] = $langs->trans('ValidatedNotModifiable');
                    return -1;
                }

                break;
            case 'LINEBILL_INSERT':
                global $db, $mysoc;

                $facture = $context->invoice ?? null;

                if (!$facture) {
                    $facture = new Facture($db);
                    $facture->fetch($object->fk_facture);
                }

                $facture->fetch_lines();

                if (count($facture->lines) > 12) {
                    if (isset($context->splitInvoice) && $context->splitInvoice) {
                        $result = $facture->fetchObjectLinked();
                        if ($result < 0) {
                            dol_syslog('Error while loading partial invoices relations', LOG_ERR);
                            $this->errors[] = $langs->trans('FetchSplitInvoicesError');
                            return $result;
                        }

                        $linkedInvoices = $facture->linkedObjects['facture'] ?? array();
                        foreach ($linkedInvoices as $candidate) {
                            $candidate->fetch_lines();

                            if (count($candidate->lines) < 12) {
                                $linkedInvoice = $candidate;
                            }
                        }

                        if (!isset($linkedInvoice)) {
                            dol_syslog('Error while loading partial invoices relations', LOG_ERR);
                            $this->errors[] = $langs->trans('MaxInvoiceLinesError');
                            return -1;
                        }

                        $result = $object->delete(null, 1);
                        if ($result < 0) {
                            dol_syslog('Error while shifting lines from split partial invoice', LOG_ERR);
                            $this->errors[] = $langs->trans('SplitInvoiceLinesError');
                            return -1;
                        }

                        $object->fk_facture = $linkedInvoice->id;
                        $result = $object->insert($user, 1);
                        if ($result < 0) {
                            dol_syslog('Error while shifting lines from a split partial invoice', LOG_ERR);
                            $this->errors[] = $langs->trans('SplitInvoiceLinesError');
                            return -1;
                        }

                        $linkedInvoice->update_price(1, 'auto', 0, $mysoc);
                    } else {
                        dol_syslog('Veri*Factu bans invoices with more than 12 lines', LOG_INFO);
                        setEventMessage($langs->trans('MaxInvoiceLinesWarn'), 'warnings');
                    }
                }

                break;
            case 'LINEPROPAL_INSERT':
            case 'LINEORDER_INSERT':
            case 'LINECONTRACT_INSERT':
            case 'LINEFICHINTER_CREATE':
            case 'LINESHIPPING_INSERT':
                if (!getDolGlobalInt('AUTOVERIFACTU_SPLIT_INVOICES')) {
                    setEventMessage($langs->trans('MaxEntityLinesWarn'), 'warnings');
                }

                break;
            case 'USER_LOGOUT':
                autoverifactu_set_const('AUTOVERIFACTU_DISMISSED_NOTICES', '');
                break;
        }

        return 0;
    }
}

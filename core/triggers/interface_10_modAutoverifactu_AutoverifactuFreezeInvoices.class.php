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

require_once dirname(__DIR__, 2) . '/lib/verifactu.lib.php';


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
        if (!isModEnabled('autoverifactu')) {
            return 0;
        }

        /**
         * Tracked invoices types:
         *   0: Default ✓
         *   1: Replacement ✓
         *   2: Credit note ✓
         *   3: Down payment ✓
         *   4: Progorma ✕
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
            case 'BILL_CANCEL':
                $trigger = $_GET['action'] ?? '';
                $facid = $_GET['facid'] ?? INF;

                // If it's triggered by a replacment invoice, skip the cancel record registration.
                if ($trigger === 'confirm_valid' && $facid > $object->id) {
                    return 0;
                }

                $result = autoverifactuRegisterInvoice($object, $action);
                if ($result < 0) {
                    $this->errors[] = $langs->trans(
                        'Veri*Factu invoice record creation has failed'
                    );
                }

                return $result;
            case 'BILL_VALIDATE':
            // case 'DON_VALIDATE':
                $result = autoverifactuRegisterInvoice($object, $action);

                if ($result < 0) {
                    $this->errors[] = $langs->trans(
                        'Veri*Factu invoice record creation has failed'
                    );
                }

                return $result;
            case 'BILL_UNVALIDATE':
            case 'BILL_UNPAYED':
                if ($object->type <= Facture::TYPE_DEPOSIT) {
                    dol_syslog('Veri*Factu disables invoice unvalidations');
                    $this->errors[] = $langs->trans('Validated invoices are not editables');
                    return -1;
                }

                break;
            case 'BILL_DELETE':
            // case 'DON_DELETE':
                if (
                    $object->status != Facture::STATUS_DRAFT
                    && $object->type <= Facture::TYPE_DEPOSIT
                ) {
                    dol_syslog('Veri*Factu disables validated invoices removals');
                    $this->errors[] = $langs->trans('Validated invoices can\'t be deleted');
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
                    dol_syslog('Veri*Factu disables validated invoices edits');
                    $this->errors[] = $langs->trans('Validated invoices can\'t be modified');
                    return -1;
                }

                break;
        }

        return 0;
    }
}

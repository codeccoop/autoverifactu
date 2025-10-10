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

        // TODO: Handle donations. As far as i know, theu have to be declared as invoices to the AEAT, it isn't?
        // print_r(['action' => $action, 'object' => $object->element, 'status' => $object->status]);
        switch ($action) {
            case 'BILL_CANCEL':
                $result = autoverifactuRegisterInvoice($object, $action);
                if ($result < 0) {
                    dol_print_error(
                        $this->db,
                        $langs->trans(
                            'Veri*Factu invoice record creation has failed'
                        ),
                    );
                }

                return $result;
            case 'BILL_VALIDATE':
            // case 'DON_VALIDATE':
            // case 'CASHCONTROL_VALIDATE':
                $object->fetch_thirdparty();
                $thirdparty = $object->thirdparty;
                $valid_id = $thirdparty->id_prof_check(1, $thirdparty);

                if (!$valid_id) {
                    dol_syslog(
                        sprintf('Skip validation for invoice #%d due to a thirdparty without idprof1', $object->id),
                        LOG_ERR
                    );

                    dol_print_error(
                        $this->db,
                        $langs->trans(
                            'Veri*Factu requires invoice third parties to have a valid professional ID'
                        ),
                    );

                    return -1;
                }

                $result = autoverifactuRegisterInvoice($object, $action);
                if ($result < 0) {
                    dol_print_error(
                        $this->db,
                        $langs->trans(
                            'Veri*Factu invoice record creation has failed'
                        ),
                    );
                }

                return $result;
            case 'BILL_UNVALIDATE':
            case 'BILL_UNPAYED':
                dol_syslog('Auto-Veri*Factu disables invoice unvalidations');
                dol_print_error($this->db, $langs->trans('Validated invoices are not editables'));
                return -1;
            case 'BILL_DELETE':
            // case 'DON_DELETE':
                if ($object->status != 0) {
                    dol_syslog('Auto-Veri*Factu disables validated invoices removals');
                    dol_print_error($this->db, $langs->trans('Validated invoices can\'t be deleted'));
                    return -1;
                }

                return 0;
            case 'BILL_MODIFY':
            // case 'DON_MODIFY':
                if ($object->status != 0) {
                    dol_syslog('Auto-Veri*Factu disables validated invoices edits');
                    dol_print_error($this->db, $langs->trans('Validated invoices can\'t be modified'));
                    return -1;
                }

                return 0;
        }

        return 0;
    }
}

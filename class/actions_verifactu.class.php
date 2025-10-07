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
 * \file    htdocs/custom/verifactu/class/actions_verifactu.class.php
 * \ingroup verifactu
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';
require_once dirname(__DIR__) . '/lib/verifactu.lib.php';

/**
 * Class ActionsVerifactu
 */
class ActionsVerifactu extends CommonHookActions
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var string[] Errors
     */
    public $errors = array();


    /**
     * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var ?string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var int     Priority of hook (50 is used if value is not defined)
     */
    public $priority;


    /**
     * Constructor
     *
     *  @param  DoliDB  $db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overload the doActions function : replacing the parent's function with the one below
     *
     * @param   array<string,mixed> $parameters     Hook metadata (context, etc...)
     * @param   CommonObject        $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   ?string             $action         Current action (if set). Generally create or edit or null
     * @param   HookManager         $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                                 Return integer < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter

        if ('invoicecard' === $parameters['currentcontext']) {       // do something only for the context 'somecontext1' or 'somecontext2'
            // global $conf;
            // if (isset($conf->global) && is_object($conf->global)) {
            //     $conf->global->INVOICE_DISALLOW_REOPEN = '1';
            // };
            //
            // $error = false;
            // if (!$error) {
            //     $this->results = array('myreturn' => 999);
            //     $this->resprints = 'A text to show';
            //     return 0; // or return 1 to replace standard code
            // } else {
            //     $this->errors[] = 'Error message';
            //     return -1;
            // }
        }

        return 0;
    }

    /**
     * Execute action before PDF (document) creation
     *
     * @param   array<string,mixed> $parameters Array of parameters
     * @param   CommonObject        $object     Object output on PDF
     * @param   string              $action     'add', 'update', 'view'
     * @return  int                             Return integer <0 if KO,
     *                                          =0 if OK but we want to process standard actions too,
     *                                          >0 if OK and we want to replace standard actions.
     */
    public function beforePDFCreation($parameters, &$object, &$action)
    {
        if ($object->element === 'facture' && $object->status == 1) {
            // $pdf_file = $parameters['file'];
            // $pathinfo = pathinfo($pdf_file);
            // $xml_file = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.xml';
            //
            // try {
            //     $xml_content = verifactuSendInvoice($object);
            // } catch (Exception $e) {
            //     echo '<pre>';
            //     print_r($object);
            //     echo '</pre>';
            //     die($e->getMessage());
            //     $this->errors[] = $e->getMessage();
            //     return -1;
            // }
            //
            // file_put_contents($xml_file, $xml_content);
        }

        return 0;
    }

    public function printUnderHeaderPDFline($parameters, &$pdfhandler)
    {
        $object = $parameters['object'];

        if ($object->element === 'facture' && $object->status == 1) {
            $pdf = &$parameters['pdf'];

            $uri = 'https://www.codeccoop.org';

            $pdf->write2DBarcode(
                $uri,
                'QRCODE,M',
                $pdfhandler->marge_gauche,
                $pdfhandler->tab_top - 5,
                25,
                25,
                array(
                    'border' => false,
                    'padding' => 0,
                    'fgcolor' => array(25, 25, 25),
                    'bgcolor' => false,
                    'module_width' => 1,
                    'module_height' => 1,
                ),
                25,
            );

            $this->results = array('extra_under_address_shift' => 25);
        }

        return 0;
    }

    /**
     * Execute action after PDF (document) creation
     *
     * @param   array<string,mixed> $parameters Array of parameters
     * @param   CommonDocGenerator  $pdfhandler PDF builder handler
     * @param   string              $action     'add', 'update', 'view'
     * @return  int                             Return integer <0 if KO,
     *                                          =0 if OK but we want to process standard actions too,
     *                                          >0 if OK and we want to replace standard actions.
     */
    public function afterPDFCreation($parameters, &$pdfhandler, &$action)
    {
        return 0;
    }
}

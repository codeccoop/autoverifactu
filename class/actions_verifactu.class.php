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

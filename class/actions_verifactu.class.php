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
require_once DOL_DOCUMENT_ROOT . '/blockedlog/class/blockedlog.class.php';
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
        global $conf;

        if ($object->element === 'facture' && $object->status == 1) {
            $invoiceref = dol_sanitizeFileName($object->ref);
            $dir = $conf->facture->multidir_output[$object->entity ?? $conf->entity] . '/' . $invoiceref;
            $file = $dir . '/' . $invoiceref . '-verifactu.xml';
            $hidden = $dir . '/.verifactu.xml';

            if (!is_file($hidden)) {
                dol_syslog('Immutable xml copy not found for invoice #' . $object->id, LOG_ERR);
                return -1;
            } elseif (!is_file($file)) {
                $result = file_put_contents($file, file_get_contents($hidden));

                if (!$result) {
                    dol_syslog('Unable to recreate verifactu xml from immutable copy for invoice #' . $object->id, LOG_ERR);
                    return -1;
                }
            }
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

    public function addMoreActionsButtons($parameters, &$object, $action, &$hookmanager)
    {
        global $langs;

        echo dolGetButtonAction(
            $object->status == 0 ? $langs->trans('Please, verify the invoice in order to generate the Veri*Factu document') : $langs->trans('Check verifactu validity'),
            $langs->trans('Verifactu'),
            'default',
            DOL_URL_ROOT . '/custom/verifactu/ajax/verify.php?facid=' . $object->id . '&token=' . newToken(),
            '',
            $object->status > 0,
            array(
                'attr' => array(
                    'class' => 'classfortooltip',
                    'title' => ''
                ),
            )
        );
    }

    public function dolGetButtonAction(&$parameters, $object, $action)
    {
        global $langs;

        $url = parse_url($parameters['url']);
        parse_str($url['query'] ?? '', $query);

        $action = $query['action'] ?? null;

        if (
        $object->status > 0
        && in_array($action, array('modif', 'reopen', 'delete'), true)
        ) {
            $label = $langs->trans('Disabled by Veri*Factu');

            $button = dolGetButtonAction(
                $label,
                $parameters['html'],
                $parameters['actionType'],
                '',
                $parameters['id'],
                0,
                $parameters['params']
            );

                $this->resprints = $button;
                return 1;
        } elseif ($object->status == 0 && $action === 'valid') {
            $object->fetch_thirdparty();
            $thirdparty = $object->thirdparty;
            $valid_id = $thirdparty->id_prof_check(1, $thirdparty);

            if (!$valid_id) {
                $label = $langs->trans('Veri*Factu requires invoice third parties to have a valid professional ID');
                $button = dolGetButtonAction(
                    $label,
                    $parameters['html'],
                    $parameters['actionType'],
                    '',
                    $parameters['id'],
                    0,
                    $parameters['params']
                );

                $this->resprints = $button;
                return 1;
            }
        }
    }
}

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
 * \file    htdocs/custom/autoverifactu/class/actions_autoverifactu.class.php
 * \ingroup autoverifactu
 * \brief   Autoverifactu action hooks
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT . '/blockedlog/class/blockedlog.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';
require_once dirname(__DIR__) . '/lib/validation.lib.php';

/**
 * Class ActionsAutoverifactu
 */
class ActionsAutoverifactu extends CommonHookActions
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
     * @var string[] Errors.
     */
    public $errors = array();


    /**
     * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse.
     */
    public $results = array();

    /**
     * @var ?string String displayed by executeHook() immediately after return.
     */
    public $resprints;

    /**
     * @var int     Priority of hook (50 is used if value is not defined).
     */
    public $priority;


    /**
     * Constructor
     *
     *  @param  DoliDB  $db      Database handler.
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overload the doActions function : replacing the parent's function with the one below
     *
     * @param   array<string,mixed> $parameters     Hook metadata (context, etc...)
     * @param   CommonObject        $object         The object to process (an invoice if you are
     *                                              in invoice module, a propale in propale's module, etc...)
     * @param   ?string             $action         Current action (if set). Generally create or edit or null
     *
     * @return  int                                 Return integer < 0 on error, 0 on success, 1 to replace
     *                                              standard code
     */
    public function doActions($parameters, &$object, &$action)
    {
        global $langs, $mysoc;

        if ($parameters['currentcontext'] === 'invoicecard') {
            switch ($action) {
                case 'verifactu':
                    $result = autoverifactuIntegrityCheck($object);

                    if (!$result) {
                        $this->errors[] = $langs->trans('Unable to find invoice entry on the immutable log');
                    } elseif ($result < 0) {
                        $this->errors[] = $langs->trans('It seems your invoice data is not consistent');
                    }

                    $base_url = VERIFACTU_BASE_URL;
                    $endpoint = '/wlpl/TIKE-CONT/ValidarQR';
                    $query = http_build_query(array(
                        'nif' => $mysoc->idprof1,
                        'numserie' => $object->ref,
                        'fecha' => date('d-m-Y', $object->date),
                        'importe' => number_format($object->total_ttc, 2, '.', ''),
                        'formato' => 'json',
                    ));

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint . '?' . $query);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_FAILONERROR, 1);

                    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
                    $certPath = DOL_DATA_ROOT . '/' . getDolGlobalString('AUTOVERIFACTU_CERT');
                    curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
                    $certPass = getDolGlobalString('AUTOVERIFACTU_PASSWORD');
                    curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $certPass);

                    $res = curl_exec($ch);

                    if ($res === false) {
                        $this->errors[] = $langs->trans('Invoice collation API request error');
                    } else {
                        $data = json_decode($res);

                        if ($data->status !== 'OK') {
                            $this->errors[] = $langs->trans('Invoice collaction API response error');

                            if ($data->mensaje) {
                                $this->errors[] = $data->mensaje;
                            }
                        } elseif ($data->mensaje !== 'Factura encontrada') {
                            $this->errors[] = $langs->trans('The invoice is not publicly registered');
                        }
                    }

                    if (empty($this->errors)) {
                        $this->results[] = $langs->trans('Invoice integrity check and validation succeed');
                    }
            }
        } elseif ($parameters['currentcontext'] === 'admincompany') {
            if ($action === 'update' && autoverifactuEnabled()) {
                $forbidden = $mysoc->nom !== GETPOST('name')
                    || $mysoc->idprof1 !== GETPOST('siren');

                if ($forbidden) {
                    $_POST['name'] = $mysoc->nom;
                    $_POST['siren'] = $mysoc->idprof1;

                    $this->errors[] = $langs->trans('Update disabled by Veri*Factu');

                    $action = 'skip';
                }
            }
        }

        if (count($this->errors)) {
            return -1;
        } elseif (count($this->results)) {
            setEventMessages($this->resprints ?? '', $this->results, 'mesgs');
            return 1;
        }
    }

    /**
     * Execute action before PDF (document) creation
     *
     * @param   array<string,mixed> $parameters Array of parameters.
     * @param   CommonObject        $object     Object output on PDF.
     * @param   string              $action     'add', 'update', 'view'.
     *
     * @return  int                             Return integer <0 if KO,
     *                                          =0 if OK but we want to process standard actions too,
     *                                          >0 if OK and we want to replace standard actions.
     */
    public function beforePDFCreation($parameters, &$object, &$action)
    {
        if (
            $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
        ) {
            $result = autoverifactuCheckInvoiceImmutableXML($object, 'alta');

            if ($result < 0) {
                return $result;
            }

            if ($object->status >= Facture::STATUS_CLOSED) {
                $result = autoverifactuCheckInvoiceImmutableXML($object, 'anulacion');

                if (!$result < 0) {
                    return $resutl;
                }
            }
        }

        return 0;
    }

    /**
     * Execute action after PDF (document) header creation. Writes the QR code before the
     * invoice body is opened.
     *
     * @param   array<string,mixed> $parameters     Array of parameters.
     * @param   PDFCT               &$pdfhandler    Object output on PDF.
     * @param   string              $action         'add', 'update', 'view'.
     *
     * @return  int                                 Return always 0.
     *                                              Overwrites the hookmanager results array
     */
    public function printUnderHeaderPDFline($parameters, &$pdfhandler)
    {
        global $mysoc;

        $object = $parameters['object'];

        if (
            $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
        ) {
            $pdf = &$parameters['pdf'];

            $base_url = VERIFACTU_BASE_URL;
            $endpoint = '/wlpl/TIKE-CONT/ValidarQR';
            $query = http_build_query(array(
                'nif' => $mysoc->idprof1,
                'numserie' => $object->ref,
                'fecha' => date('d-m-Y', $object->date),
                'importe' => number_format($object->total_ttc, 2, '.', ''),
            ));

            $pdf->write2DBarcode(
                $base_url . $endpoint . '?' . $query,
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

            $pdf->setTopMargin($pdfhandler->tab_top + 21);
            $pdf->MultiCell(25, 5, 'Veri*Factu', 0, 'C', 0, 1);

            $this->results = array('extra_under_address_shift' => 27);
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

    /**
     * Execute action on card page buttons render. If it is a facture page,
     * it adds a "verifactu" button to the row.
     *
     * @param  array<string,mixed>  $parameters  Array of parameters.
     * @param  CommonObect          &$object     Instance of the owner object of the page.
     * @param  string               $action      Global action.
     *
     * @return null                              Empty response. The button
     *                                           html is echoed to the output
     *                                           buffer.
     */
    public function addMoreActionsButtons($parameters, &$object, $action)
    {
        global $langs;

        if (
            $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
        ) {
            echo dolGetButtonAction(
                $langs->trans('Check verifactu integrity'),
                $langs->trans('Veri*Factu'),
                'default',
                $_SERVER['PHP_SELF'] . '?action=verifactu&token=' . newToken() . '&id=' . $object->id,
                '',
                1,
                array(
                'attr' => array(
                'class' => 'classfortooltip',
                'title' => ''
                ),
                )
            );
        }
    }

    /**
     * Execute action on each card page buttons render. If it is a facture page,
     * then check userRights for each button based on the button action and
     * the state of the invoice.
     *
     * @param  array<string,mixed>  $parameters  Array of parameters.
     * @param  CommonObect          &$object     Instance of the owner object of
     *                                           the page.
     * @param  string               $action      Global action.
     *
     * @return int<0,1>                          1 if button has been overwrited,
     *                                           0 otherwise.
     */
    public function dolGetButtonAction(&$parameters, $object, $action)
    {
        global $langs;

        if (
            $object->element === 'facture'
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
        ) {
            $url = parse_url($parameters['url']);
            parse_str($url['query'] ?? '', $query);

            $action = $query['action'] ?? null;

            if (
                $object->status > Facture::STATUS_DRAFT
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
            } elseif ($object->status == Facture::STATUS_DRAFT && $action === 'valid') {
                $object->fetch_thirdparty();
                $thirdparty = $object->thirdparty;
                $valid_id = $thirdparty->id_prof_check(1, $thirdparty);

                if (!$thirdparty->idprof1 || !$valid_id) {
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
}

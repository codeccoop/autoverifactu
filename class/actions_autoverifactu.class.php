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
                        $this->errors[] = $langs->trans('BlockedLogNotFound');
                    } elseif ($result < 0) {
                        $this->errors[] = $langs->trans('InconsistentInvoiceData');
                    }
                    //url de verificacion en casp de test ou production
                    $testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
                    $base_url = $testMode ? VERIFACTU_TEST_VERIFICACION_BASE_URL : VERIFACTU_BASE_URL;
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
                        $this->errors[] = $langs->trans('CollationRequestError');
                    } else {
                        $data = json_decode($res);

                        if ($data->status !== 'OK') {
                            $this->errors[] = $langs->trans('CollationResponseError');

                            if ($data->mensaje) {
                                $this->errors[] = $data->mensaje;
                            }
                        } elseif ( !($data->mensaje === 'Factura encontrada' || 
                                    $data->mensaje ==="Encontrada" )
                            ) {
                            $this->errors[] = $langs->trans('NotPubliclyRegistered');
                        }
                    }

                    if (empty($this->errors)) {
                        $this->results[] = $langs->trans('IntegrityCheckOK');
                    }
            }
        } elseif ($parameters['currentcontext'] === 'admincompany') {
            if ($action === 'update' && autoverifactuEnabled()) {
                $forbidden = $mysoc->nom !== GETPOST('name')
                    || $mysoc->idprof1 !== GETPOST('siren');

                if ($forbidden) {
                    $_POST['name'] = $mysoc->nom;
                    $_POST['siren'] = $mysoc->idprof1;

                    $this->errors[] = $langs->trans('UpdateDisabledBy');

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

    
    
        // 2. Si el objeto no lo tiene, lo buscamos en el request o configuración


       
   
        $object = $parameters['object'];


        $modelpdf = $object->model_pdf;

        if (
            $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled() 
            && $modelpdf !== "Autoverifactu"
        ) {
            $pdf = &$parameters['pdf'];

            //url de verificacion en casp de test ou production
            $testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');

            $base_url = $testMode ? VERIFACTU_TEST_VERIFICACION_BASE_URL : VERIFACTU_BASE_URL;
                    
   
            $endpoint = '/wlpl/TIKE-CONT/ValidarQR';
            $query = http_build_query(array(
                'nif' => $mysoc->idprof1,
                'numserie' => $object->ref,
                'fecha' => date('d-m-Y', $object->date),
                'importe' => number_format($object->total_ttc, 2, '.', ''),
            ));
            //El código «QR» deberá tener un tamaño entre 30x30 y 40x40 milímetros y seguir las especificaciones de la norma ISO/IEC 18004:2015
            //A este respecto, se deben mantener como mínimo 2 milímetros de espacio vacío (en blanco) alrededor de los cuatro lados del código «QR», recomendándose que sean 6 milímetros.
            //La presentación del código «QR» incluirá también un texto que siempre deberá ir precediéndolo: «QR tributario:», y que se situará encima del propio código «QR» 
            // (preferiblemente centrado con respecto a este), de manera que sirva para identificarlo y distinguirlo de otros posibles códigos «QR» que pudiera contener la factura para otros cometidos.
           
           
            $pdf->setTopMargin($pdfhandler->tab_top -5);           
            $pdf->MultiCell(30, 10, 'QR tributario:', 0, 'C', 0, 1);

            $pdf->write2DBarcode(
                $base_url . $endpoint . '?' . $query,
                'QRCODE,M',
                $pdfhandler->marge_gauche,
                $pdfhandler->tab_top-1 ,
                32,
                32,
                array(
                    'border' => false,
                    'padding' => 2,
                    'fgcolor' => array(25, 25, 25),
                     'bgcolor' => array(255, 255, 255), //margen color blanco con padding 2mm
                    'module_width' => 1,
                    'module_height' => 1,
                ),
                30,
            );

            $pdf->setTopMargin($pdfhandler->tab_top + 32);
            $pdf->MultiCell(30, 10, 'VERI*FACTU', 0, 'C', 0, 1);

            $this->results = array('extra_under_address_shift' => 40);
        }

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
                $langs->trans('CheckIntegrity'),
                'Veri*Factu',
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
                && !empty($parameters['userRight'])
            ) {
                $label = $langs->trans('DisabledBy');

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
                $valid_id = $thirdparty->idprof1 && $thirdparty->id_prof_check(1, $thirdparty);

                if (
                    !$valid_id
                    && !$thirdparty->tva_intra
                    && !autoverifactuIsPosInvoice($object)
                    && !empty($parameters['userRight'])
                ) {
                    $label = $langs->trans('ThirdpartyIdProfRequired');

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

                $object->fetch_lines();

                if (count($object->lines) > 12 && !empty($parameters['userRight'])) {
                    $label = $langs->trans('MaxInvoiceLines');
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

    public function formObjectOptions($parameters, $object, $action)
    {
        global $extrafields;
        if (
            $parameters['currentcontext'] === 'invoicecard'
            && $object->element === 'facture'
            && $action === 'edit_extras'
            && !in_array(
                $object->type,
                array(
                    Facture::TYPE_REPLACEMENT,
                    Facture::TYPE_CREDIT_NOTE
                ),
            )
        ) {
            $extrafields->attributes['facture']['list']['verifactu_rectification_type'] = '0';
        }
    }
}

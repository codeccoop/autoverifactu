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
    
        global $langs, $mysoc, $dolibarr_main_url_root;

       
        if($parameters['currentcontext'] ==='invoicelist'){
            //añade estilo a los estados de verifactu.
           echo '<link rel="stylesheet" type="text/css" href="'.$dolibarr_main_url_root.'/custom/autoverifactu/css/selector_status.css.php">';
        }

        if ($parameters['currentcontext'] === 'invoicecard') {
            switch ($action) {
                case 'verifactu':
                    //verificacion de factura enviada a verifactu
                    $result = autoverifactuIntegrityCheck($object);
                    if (!$result) {
                        $this->errors[] = $langs->trans('BlockedLogNotFound');
                    } elseif ($result < 0) {
                        $this->errors[] = $langs->trans('InconsistentInvoiceData');
                    }
                    // url de verificacion en caso de test o production.
                    $testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
                    $base_url = $testMode ? VERIFACTU_TEST_COLLATION_BASE_URL : VERIFACTU_COLLATION_BASE_URL;
                    $endpoint = '/wlpl/TIKE-CONT/ValidarQR';
                    //en caso de tener IRPF hay que quitarselo ya que en verifactu no hay que tenerlo en cuenta
                    // por ello le sumo el irpf al total
                    $query = http_build_query(array(
                        'nif' => $mysoc->idprof1,
                        'numserie' => $object->ref,
                        'fecha' => date('d-m-Y', $object->date),
                        'importe' => number_format($object->total_ttc - $object->total_localtax2 , 2, '.', ''),
                        'formato' => 'json',
                    ));
                    $ch = curl_init();
                    echo $base_url . $endpoint . '?' . $query;
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
                        } elseif (!in_array($data->mensaje, array('Factura encontrada', 'Encontrada'), true)) {
                            $this->errors[] = $langs->trans('NotPubliclyRegistered');
                        }
                    }

                    if (empty($this->errors)) {
                        $this->results[] = $langs->trans('IntegrityCheckOK');
                    }
                    break;
                case 'edit_extras':
                    $attribute=GETPOST("attribute","alpha");
                    //evito que se editen estos campos
                    if(
                        $attribute==="verifactu_status" || 
                        $attribute==="verifactu_hash" ||
                        $attribute === "verifactu_error" ||
                        $attribute === "verifactu_error_code" ||
                        $attribute ==="verifactu_pdfLegalText" ||
                        $attribute==="VerifactuTimeStamp"
                        ){
                            $this->errors[] = $langs->trans('NotEdit');
                            $action = '';
                            
                    }
                    break;
                case 'add':
                      if(in_array($object->type,array(Facture::TYPE_REPLACEMENT,Facture::TYPE_CREDIT_NOTE),true)){
                       $rectificationType=GETPOST("options_verifactu_rectification_type","alpha");
                        
                        if(empty($rectificationType)){
                            $this->errors[] = $langs->trans('RectificationTypeRequired');
                            header('Location: '.$_SERVER['PHP_SELF'].'?action=create');
                        }
                      }
                  
                    break; 
                case 'verifactuResend':
                    //esta accion se da cuando una factura ha tenido un error y se quiere reenviar unavez subsanado el error 
                    $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid'));
                    //compruebo que el a pasado el tiempo de espera par la proxima peticion de la api
                    if($now->getTimestamp()<getDolGlobalString('VERIFACTU_NEXT_DELIVERY_ALLOWED', '0')){
                        //en caso de que no se pueda enviar lo indico 
                        $langs->load('autoverifactu@autoverifactu');
                        $this->errors[] = $langs->trans('notToDoList',getDolGlobalString('VERIFACTU_NEXT_DELIVERY_ALLOWED')-$now->getTimestamp());
                        return 0;
                    }else if(in_array($object->array_options['options_verifactu_status'],array("2","4","5"),true)){
                        //en caso de poder enviarla lo envio
                        $result = autoverifactuRegisterInvoice($object, $action);
                        if ($result <= 0) {
                            if (!empty($object->errors)) {
                                $this->errors = array_merge($this->errors, (array) $object->errors);
                            }else{
                                $this->errors[] = $langs->trans('RecordCreationFail');
                            }
                        }
                        return $result;
                    }
                    break;
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
        //?No entiendo por que regeneras el archivo xml. Una vez enviado y guardado es mejor no volver a generar.
        
       /* if (
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
                    return $result;
                }
            }
        }*/

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
        $modelpdf = $object->model_pdf;
        if (
            $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled() 
            && $modelpdf !== "Autoverifactu" //en caso de que no tenga la plantilla autoverifactu que ya incluye el QR
        ) {
            $pdf = &$parameters['pdf'];

            // url de verificacion en caso de test o production.
            $testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
            $base_url = $testMode ? VERIFACTU_TEST_COLLATION_BASE_URL : VERIFACTU_COLLATION_BASE_URL;
            $endpoint = '/wlpl/TIKE-CONT/ValidarQR';
            $query = http_build_query(array(
                'nif' => $mysoc->idprof1,
                'numserie' => $object->ref,
                'fecha' => date('d-m-Y', $object->date),
                'importe' => number_format($object->total_ttc - $object->total_localtax2, 2, '.', ''),
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
            &&  $object->array_options['options_verifactu_status'] === '1'
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
        //Boton para mostrar los errores de factura
        if( $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
            && $object->array_options['options_verifactu_status'] === '4'
            ){
                             echo dolGetButtonAction(
                $langs->trans('fixErrors'),
                $langs->trans('fixErrors'),
                'default',
                $_SERVER['PHP_SELF'] . '?action=fixErrors&token=' . newToken() . '&id=' . $object->id,
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
        //permitir reenviar la facturas con errores
        if( $object->element === 'facture'
            && $object->status > Facture::STATUS_DRAFT
            && $object->type <= Facture::TYPE_DEPOSIT
            && autoverifactuEnabled()
            && in_array($object->array_options['options_verifactu_status'], array("2","4","5"),true)
            ){
                echo dolGetButtonAction(
                $langs->trans('VerifactuResend'),
                $langs->trans('VerifactuResend'),
                'default',
                $_SERVER['PHP_SELF'] . '?action=verifactuResend&token=' . newToken() . '&id=' . $object->id,
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

                //$object->fetch_lines();

               /* Lo que limita es el numero de diferentes tipos de taxas
               las lineas con las mismas tipos de taxas se deben de sumar 
               
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
                } */
            }
        }
    }

    public function formObjectOptions($parameters, $object, $action)
    {
       
        global $extrafields, $langs, $db ;


        if ($parameters['currentcontext'] !== 'invoicecard' || $object->element !== 'facture') {
            return;
        }

        if($parameters['currentcontext'] === 'invoicecard' && $action==="fixErrors" && $object->id>0) {
            //accion de mostrar los erroes y una explicacion
            require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';            
            $langs->load('autoverifactu@autoverifactu');
            $form = new Form($db);
            $formquestion;

            if($object->array_options['options_verifactu_error_code'] === "2001" ){
                //El NIF del bloque Destinatarios no está identificado en el censo de la AEAT.
               $message = $langs->trans("Errorcode2001",$object->thirdparty->idprof1 ) ;
               $parametros = $_GET; 
               $parametros['id'] = $object->id;
               $url= $_SERVER["PHP_SELF"] . "?id=" . $object->id;
               $this->formAlert($message,$url);
            }else{
                //los demas errores en teoria no deberian producirse porque la aplicación
                //daria error y no enviaria la petición  
                $message= $langs->trans("ErrorCodeErrorAplication");
                $url= $_SERVER["PHP_SELF"] . "?id=" . $object->id;
                $this->formAlert($message,$url);
            }
            return 0;
        }

        if (
            !in_array(
                $object->type,
                array(
                    Facture::TYPE_REPLACEMENT,
                    Facture::TYPE_CREDIT_NOTE
                ),
                true
            ) && $object->id !== null // never hide the field for invoice creation forms
            // && $action === 'edit_extras'
        ) {
            //$extrafields->attributes['facture']['list']['verifactu_rectification_type'] = '0';
        }
        //css para ocultar de los campos adjuntos el icono de editar
        print '
        <style>
            tr:has(#facture_extras_verifactu_error_' . $object->id . ') .editfielda {
                display: none !important;
            }
            tr:has(#facture_extras_verifactu_hash_' . $object->id . ') .editfielda {
                display: none !important;
            }
            tr:has(#facture_extras_verifactu_status_' . $object->id . ') .editfielda {
                display: none !important;
            }
            .field_options_verifactu_rectification_type span.valignmiddle{
                font-weight: bold;
            }
        </style>';


    }

    //funcion que genera una ventana emergente con el mensaje parado por parametro con un btn de aceptar, 
    // que te lleva a la url pasada por parametro.
    private function formAlert($message,$url){
        ?>
            <div id="dialog-veri-factu-alert" title="Corregir Errores de Veri*Factu" style="display: none;">
                <div class="error" style="text-align: left; padding: 15px; margin-top: 15px;">
                    <span class="fa fa-exclamation-triangle" style="color: #bd2130; font-size: 1.5em; margin-right: 10px; vertical-align: middle;"></span>
                    <span style="vertical-align: middle; font-size: 1.1em; color: #333;">
                        <?php echo $message?>
                    </span>
                </div>
            </div>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery("#dialog-veri-factu-alert").dialog({
                        modal: true,
                        resizable: false,
                        closeOnEscape: true,
                        width: 550,  
                        height: "auto",
                        buttons: [
                            {
                                text: "Aceptar",
                                class: "button", // Clase CSS nativa de los botones de Dolibarr
                                click: function() {
                                    jQuery(this).dialog("close");
                          
                                    window.location.href = '<?php echo $url ?>';
                                }
                            }
                        ]
                    });
                });
            </script>
        <?php
    }
}

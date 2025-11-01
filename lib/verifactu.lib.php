<?php

/* Copyright (C) 2025       Lucas García            <lucas@codeccoop.org>
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
 * \file    htdocs/custom/autoverifactu/lib/verifactu.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with functions to interface with the Veri*Factu API
 */

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

/* Veri*Factu API URLs */
define('VERIFACTU_BASE_URL', 'https://www1.agenciatributaria.gob.es'); // Production environment
define('VERIFACTU_TEST_BASE_URL', 'https://prewww1.aeat.es'); // Test environment

/* XML namespaces */
define('AUTOVERIFACTU_SOAPENV_NS', 'http://schemas.xmlsoap.org/soap/envelope/');
define(
    'AUTOVERIFACTU_SUM_NS',
    'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd',
);
define(
    'AUTOVERIFACTU_SUM1_NS',
    'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd',
);
define('AUTOVERIFACTU_XD_NS', 'http://www.w3.org/2000/09/xmldsig');

/**
 * Verifactu invoice record registration.
 *
 * @param  Facture $invoice Target invoice. Invoice should not be validated, or
 *                               action should be BILL_CANCEL.
 * @param  string  $action  Current action.
 *
 * @return int              Return <0 if KO, 0 if skipped, >0 if OK.
*/
function autoverifactuRegisterInvoice($invoice, $action)
{
    global $db, $conf,$hookmanager;

    if ($invoice->type > Facture::TYPE_DEPOSIT) {
        // Skip non recordable invoice types.
        return 0;
    }

    if (
        $invoice->status == Facture::STATUS_DRAFT &&
        !in_array(
            $action,
            array(
                'BILL_VALIDATE',
                // 'DON_VALIDATE',
                // 'CASHCONTROL_VALIDATE',
            ),
            true,
        )
    ) {
        return 0;
    } elseif (
        $invoice->status == Facture::STATUS_VALIDATED &&
        $action !== 'BILL_CANCEL'
    ) {
        return 0;
    }

    if (empty($conf->facture->multidir_output[$conf->entity])) {
        dol_syslog('Constant $conf->facture->multidir_output not defined', LOG_ERR);
        return -1;
    }

    $invoice->fetch_thirdparty();
    $thirdparty = $invoice->thirdparty;
    $valid_id = $thirdparty->id_prof_check(1, $thirdparty);
    if ($valid_id <= 0 && !$thirdparty->tva_intra) {
        dol_syslog('Skip invoice verifactu record registration due to thirdparty without a vaid idprof1');
        return -1;
    }

    $invoice->fetch_lines();
    if (!count($invoice->lines)) {
        dol_syslog('Skip invoice verifactu record registration to an invoice without lines');
        return -1;
    }

    $invoiceref = dol_sanitizeFileName($invoice->ref);
    $dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;

    if ($action === 'BILL_VALIDATE') {
        $file = $dir . '/' . $invoiceref . '-alta.xml';
        $hidden = $dir . '/.verifactu-alta.xml';
    } else {
        $file = $dir . '/' . $invoiceref . '-anulacion.xml';
        $hidden = $dir . '/.verifactu-anulacion.xml';
    }

    if (!file_exists($dir)) {
        if (dol_mkdir($dir) < 0) {
            dol_syslog('Unable to create verifactu files directory ' . $dir, LOG_ERR);
            return -1;
        }
    }

    if (!file_exists($dir)) {
        dol_syslog('Unable to create verifactu files directory ' . $dir, LOG_ERR);
        return -1;
    }

    if (!is_object($hookmanager)) {
        include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
        $hookmanager = new HookManager($db);
    }

    $hookmanager->initHooks(array('autoverifactu'));

    $parameters = array(
        'file' => $file,
        'invoice' => $invoice,
        'action' => $action,
    );

    $reshook = $hookmanager->executeHooks(
        'beforeAutoverifactuRecord',
        $parameters,
        $invoice,
    );

    if ($reshook < 0) {
        dol_syslog('Skip verfiactu record registry for invoice #' . $invoice->id);
        return $reshook;
    } elseif ($reshook) {
        dol_syslog(
            'Verfiactu record registry interception on "beforeAutoverifactuRecord" for invoice #'
            . $invoice->id
        );

        return $reshook;
    }

    try {
        $record = autoverifactuSendInvoice($invoice, $action, $xml);

        // Skip document generation if send does not succeed.
        if (!$record) {
            return 0;
        }

        $result = file_put_contents($file, $xml);
        $result = $result && file_put_contents($hidden, $xml);

        if (!$result) {
            dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
            return -1;
        } else {
            // Store invoice verifactu extrafields after record registration
            $invoice->array_options['options_verifactu_hash'] = $record->hash;
            $invoice->array_options['options_verifactu_tms'] = $record->hashedAt->getTimestamp();

            if ($error = $record->error ?? null) {
                $invoice->array_options['options_verifactu_error'] = $error->message;
            }

            $result = $invoice->insertExtraFields();

            if ($result <= 0) {
                dol_syslog('Unable to update invoice extra fields', LOG_ERR);
                return -1;
            }

            $parameters['record'] = $record;
            $parameters['xml'] = $xml;

            $reshook = $hookmanager->executeHooks(
                'afterAutoverifactuRecord',
                $parameters,
                $invoice,
            );
        }
    } catch (Error | Exception $e) {
        if (isset($xml) && $xml) {
            file_put_contents($file, $xml);
        }

        dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
        return -1;
    }

    return 0;
}

/**
 * Send an invoice as a record to the Veri*Factu SOAP endpoints.
 *
 * @param  Facture $invoice Target invoice. Invoice should not be published before
 * @param  string  $action  Triggered action. Can be BILL_VALIDATE or BILL_CANCEL.
 * @param  string  &$xml    Response body as an XML string.
 *
 * @return stdClass|null    Registered record, null if skipped.
 *
 * @throws Exception
 */
function autoverifactuSendInvoice($invoice, $action, &$xml)
{
    if (!autoverifactuSystemCheck()) {
        dol_syslog('Veri*Factu bridge does not pass system checks');
        return;
    }

    $enabled = getDolGlobalString('AUTOVERIFACTU_ENABLED') == '1';

    if (!$enabled) {
        dol_syslog('Veri*Factu bridge is not enabled');
        return;
    }

    $recordType = $action === 'BILL_VALIDATE' ? 'alta' : 'anulacion';

    if ('anulacion' !== $recordType && autoverifactuIsInvoiceRecorded($invoice)) {
        dol_syslog(
            'Skip verifactu invoice registration because invoice #'
            . $invoice->id .
            'is already registered',
        );

        return;
    }

    $record = autoverifactuInvoiceToRecord($invoice, $recordType);
    if (!$record) {
        throw new Exception('Inconsistent invoice data');
    }

    global $hookmanager;
    $parameters = array('record' => &$record, 'invoice' => $invoice, 'action' => $action);
    $reshook = $hookmanager->executeHooks(
        'autoverifactuRecord',
        $parameters,
        $record,
    );

    if ($reshook < 0) {
        dol_syslog('Skip verifactu record registry for invoice #' . $invoice->id);
        return $reshook;
    } elseif ($reshook) {
        dol_syslog(
            'Verifactu record registry interception on "autoverifactuRecord" for invoice #'
            . $invoice->id,
        );

        return $reshook;
    }

    global $mysoc;
    $envelope = autoverifactuSoapEnvelope(
        $record,
        array(
            'name' => $mysoc->nom,
            'idprof1' => $mysoc->idprof1,
        ),
    );

    $testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
    $base_url = $testMode ? VERIFACTU_TEST_BASE_URL : VERIFACTU_BASE_URL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);

    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
    $certPath = DOL_DATA_ROOT . '/' . getDolGlobalString('AUTOVERIFACTU_CERT');
    curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
    $certPass = getDolGlobalString('AUTOVERIFACTU_PASSWORD');
    curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $certPass);

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: text/xml',
            'User-Agent: Mozilla/5.0 (compatible; Módulo Auto-Veri*Factu de Dolibarr/0.0.1',
        ),
    );

    curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);
    $res = curl_exec($ch);
    error_log('# RESPUESTA ALTA REGISTRO');
    error_log($res);

    if ($res === false) {
        $error = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);

        throw new Exception('cURL error: ' . $error, $code);
    } else {
        $xml = $envelope;
    }

    curl_close($ch);

    $doc = new DOMDocument();
    $doc->loadXML($res . "\n");
    $faults = $doc->getElementsByTagName('Fault');

    if ($faults->count() > 0) {
        error_log($envelope);
        throw new Exception($res, 400);
    }

    $status = $doc->getElementsByTagName('EstadoRegistro')[0];

    if ($status->nodeValue === 'Incorrecto') {
        error_log($envelope);
        throw new Exception($res, 400);
    } elseif ($status->nodeValue === 'AceptadoConErrores') {
        $errCode = $doc->getElementsByTagName('CodigoErrorRegistro')[0] ?? null;
        $errMessage = $doc->getElementsByTagName('DescripcionErrorRegistro')[0] ?? null;

        if (!$errMessage || !$errCode) {
            error_log($envelope);
            throw new Exception($res, 500);
        }

        $record->error = new stdClass();
        $record->error->code = $errCode->nodeValue;
        $record->error->message = $errMessage->nodeValue;
    }

    return $record;
}

/**
 * Gets an verifactu invoice record and returns it inside a SOAP envelope.
 *
 * @param stdClass    $record         Invoice record.
 * @param array       $issuer         Issuer data with name and id keys.
 * @param array|null  $representative Representative data with name and id keys.
 *
 * @return string                     SOAP XML enveloped record.
 */
function autoverifactuSoapEnvelope($record, $issuer, $representative = null)
{
    $xml = new DOMDocument();

    $envelope = $xml->createElement('soapenv:Envelope');
    $envelope->setAttribute('xmlns:soapenv', AUTOVERIFACTU_SOAPENV_NS);
    $envelope->setAttribute('xmlns:sum', AUTOVERIFACTU_SUM_NS);
    $envelope->setAttribute('xmlns:sum1', AUTOVERIFACTU_SUM1_NS);
    $envelope->setAttribute('xmlns:xd', AUTOVERIFACTU_XD_NS);

    $headerEl = $xml->createElement('soapenv:Header');
    $envelope->appendChild($headerEl);

    $body = $xml->createElement('soapenv:Body');
    $envelope->appendChild($body);

    $root = $xml->createElement('sum:RegFactuSistemaFacturacion');
    $body->appendChild($root);

    $regHeaderEl = $xml->createElement('sum:Cabecera');
    $root->appendChild($regHeaderEl);

    $issuerEl = $xml->createElement('sum1:ObligadoEmision');
    $regHeaderEl->appendChild($issuerEl);

    $issuerNameEl = $xml->createElement('sum1:NombreRazon', $issuer['name']);
    $issuerEl->appendChild($issuerNameEl);

    $issuerNifEl = $xml->createElement('sum1:NIF', $issuer['idprof1']);
    $issuerEl->appendChild($issuerNifEl);

    if ($representative) {
        $representativeEl = $xml->createElement('sum1:Representante');
        $regHeaderEl->appendChild($representativeEl);

        $reprNameEl = $xml->createElement('sum1:NombreRazon', $representative['name']);
        $representativeEl->appendChild($reprNameEl);

        $reprNifEl = $xml->createElement('sum1:NIF', $representative['idprof1']);
        $representativeEl->appendChild($reprNifEl);
    }

    $recordEl = autoverifactuRecordToXML($record, $xml);
    $root->appendChild($recordEl);

    $xml->appendChild($envelope);
    return $xml->saveXML($envelope);
}

/**
* Return the invoice as a Veri*Factu record object.
*
* @param  Facture$invoice Target invoice.
* @param  streing         Record type. Can be 'alta' or 'anulacion'.
*
* @return stdClass|null   Record representation.
*/
function autoverifactuInvoiceToRecord($invoice, $recordType = 'alta')
{
    global $mysoc;

    $now = dol_now();
    $invoiceRef = $invoice->status > 0 ? $invoice->ref : $invoice->newref;

    $invoice->fetch_optionals();
    $invoice->fetch_thirdparty();
    $thirdparty = $invoice->thirdparty;

    switch ($invoice->type) {
        /* Standard invoice */
        case 0:
        /* Downpayment invoice */
        case 3:
            if ($invoice->module_source === 'takepos') {
                // Factura simplificada y facturas sin identificación del destinatario (Art. 6.1.D del R.D. 1619/2012).
                $invoiceType = 'F2';
            } else {
                // Factura (Art. 6, 7.2 y 7.3 del R.D. 1619/2012).
                $invoiceType = 'F1';
            }

            // Factura emitida en sustitución de facturas simplificadas facturadas y declaradas.
            // $invoiceType = 'F3';
            break;
        /* Replacement invoice */
        case 1:
        /* Credit notes */
        case 2:
            // Factura rectificativa (Art 80.1 y 80.2 de la Ley 37/1992)
            // $invoiceType = 'R1';
            // Factura rectificativa por impago (Art 80.3 de la Ley 37/1992)
            // $invoiceType = 'R2';
            // Factura rectificativa (Art 80.4 de la Ley 37/1992)
            // $invoiceType = 'R3';
            if ($invoice->module_source === 'takepos') {
                // Factura rectificativa simplificada
                $invoiceType = 'R5';
            } else {
                // Factura rectificativa corriente.
                // $invoiceType = 'R4';
                $invoiceType = $invoice->array_options['options_verifactu_rectification_type'] ?: 'R4';
            }

            break;
        default:
            $invoiceType = 'F1';
    }

    $record = new stdClass();
    $record->type = $recordType;

    $record->issuerName = $mysoc->nom;
    $record->invoiceType = $invoiceType;
    $record->description = 'Factura ' . $invoiceRef;

    $record->invoiceId = new stdClass();
    $record->invoiceId->issuerId = $mysoc->idprof1;
    $record->invoiceId->invoiceNumber = $invoiceRef;
    $record->invoiceId->issueDate = new DateTimeImmutable(
        date('Y-m-d H:i:s', $invoice->array_options['options_verifactu_tms'] ?: $now),
        new DateTimeZone('Europe/Madrid'),
    );

    $record->recipients = array();

    // If is not simplified, add third party data to the record
    if (!in_array($record->invoiceType, array('F2', 'R5'), true)) {
        $recipient = new stdClass();

        if ($thirdparty->country_code && $thirdparty->country_code !== 'ES') {
            $recipient->name = $thirdparty->nom;
            $recipient->country = $thirdparty->country_code;

            if ($thirdparty->tva_intra) {
                $recipient->type = '02';
                $recipient->value = $thirdparty->tva_intra;
            } elseif ($thirdparty->idprof1) {
                $recipient->type = '04';
                $recipient->value = $thirdparty->idprof1;
            } /* else {
                 NOTE: Autoverifactu requires thirdparty to has the idprof1 setted. Other ID type codes are:
                  * 03 Passport
                  * 05 Residence
                  * 06 Others
                  * 07 Unregistered
            } */
        } else {
            $recipient->name = $thirdparty->nom;
            $recipient->nif = $thirdparty->idprof1;
        }

        $record->recipients[0] = $recipient;
    }

    if (
        in_array(
            $record->invoiceType,
            array('R1', 'R2', 'R3', 'R4', 'R5'),
            true
        )
    ) {
        if ($invoice->type == 1) {
            // Fix by substitution
            $record->correctiveType = 'S';
        } else {
            // Fix by differences
            $record->correctiveType = 'I';
        }
    } else {
        $record->correctiveType = null;
    }

    $record->correctedInvoices = array();
    $record->correctedBaseAmount = null;
    $record->correctedTaxAmount = null;

    // If is corrective, then add correctiveInvoices data to the record.
    if ($record->correctiveType !== null) {
        $sourceInvoice = autoverifactuGetSourceInvoice($invoice);

        if (!$sourceInvoice) {
            dol_syslog('Can not find the source invoice of the corrective invoice #' . $invoice->id, LOG_ERR);
            return -1;
        } else {
            $sourceInvoice->fetch_thirdparty();
        }

        $sourceId = new stdClass();
        $sourceId->issuerId = $sourceInvoice->thirdparty->idprof1;
        $sourceId->invoiceNumber = $sourceInvoice->ref;
        $sourceId->issueDate = new DateTimeImmutable(
            date('Y-m-d H:i:s', $sourceInvoice->array_options['options_verifactu_tms'] ?: $now),
            new DateTimeZone('Europe/Madrid'),
        );

        $record->correctedInvoices[0] = $sourceId;

        if ($record->correctiveType === 'S') {
            $record->correctedBaseAmount = number_format($sourceInvoice->total_ht, 2, '.', '');
            $record->correctedTaxAmount = number_format($sourceInvoice->total_tva, 2, '.', '');
        }
    }

    $record->replacedInvoices = array();

    $record->breakdown = autoverifactuLinesToBreakdown($invoice);

    $tax_total = 0;
    $base_total = 0;
    foreach ($record->breakdown as $line) {
        $tax_total += (float) $line->taxAmount;
        $base_total += (float) $line->baseAmount;
    }

    $record->totalTaxAmount = number_format($tax_total, 2, '.', '');
    $record->totalAmount = number_format(
        $base_total + $tax_total,
        2,
        '.',
        '',
    );

    $previous = autoverifactuGetPreviousValidInvoice($invoice, $now);
    if ($previous) {
        $record->previousInvoiceId = new stdClass();
        $record->previousInvoiceId->issuerId = $mysoc->idprof1;
        $record->previousInvoiceId->invoiceNumber = $invoiceRef;
        $record->previousInvoiceId->issueDate = new DateTimeImmutable(
            date('Y-m-d H:i:s', $previous->array_options['options_verifactu_tms']),
            new DateTimeZone('Europe/Madrid'),
        );

        $previous->fetch_optionals();
        $record->previousHash = substr($previous->array_options['options_verifactu_hash'], 0, 64);
    } else {
        $record->previousInvoiceId = null;
        $record->previousHash = null;
    }

    $record->system = autoverifactuGetRecordComputerSystem();

    $record->hashedAt = new DateTimeImmutable(
        date('Y-m-d H:i:s', $invoice->array_options['options_verifactu_tms'] ?: $now),
        new DateTimeZone('Europe/Madrid'),
    );

    $record->hash = autoverifactuCalculateRecordHash($record);

    global $hookmanager;
    if (!is_object($hookmanager)) {
        include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
        $hookmanager = new HookManager($db);
    }

    $parameters = array(
        'record' => $record,
        'invoice' => $invoice,
    );

    $reshook = $hookmanager->executeHooks(
        'autoverifactuRecord',
        $parameters,
        $invoice,
    );

    if (!empty($reshook)) {
        return $reshook;
    }

    if (autoverifactuValidateRecord($record)) {
        return $record;
    }
}

/**
 * Serializes a record as a valid Vri*Factu XML record.
 *
 * @param  stdClass          $record  Invoice Veri*Factu record object.
 * @param  DOMDocument|null  $xml     Inherited document. If null, node will be created
 *                                    on a new DOMDocument instance.
 *
 * @return DOMElement                 XML record representation.
 *
 * @throws Exception                  If record type is not anulacion or alta.
 */
function autoverifactuRecordToXML($record, $xml = null)
{
    $xml = $xml ?: new DOMDocument();

    $recordElementName = $record->type === 'alta'
        ? 'RegistroAlta'
        : 'RegistroAnulacion';

    $root = $xml->createElement('sum:RegistroFactura');

    $recordEl = $xml->createElement('sum1:' . $recordElementName);
    $root->appendChild($recordEl);

    $recordEl->appendChild($xml->createElement('sum1:IDVersion', '1.0'));

    if ($record->type === 'alta') {
        $invoiceId = $xml->createElement('sum1:IDFactura');
        $recordEl->appendChild($invoiceId);

        $invoiceId->appendChild($xml->createElement('sum1:IDEmisorFactura', $record->invoiceId->issuerId));
        $invoiceId->appendChild($xml->createElement('sum1:NumSerieFactura', $record->invoiceId->invoiceNumber));
        $invoiceId->appendChild($xml->createElement(
            'sum1:FechaExpedicionFactura',
            $record->invoiceId->issueDate->format('d-m-Y')
        ));

        $recordEl->appendChild($xml->createElement('sum1:NombreRazonEmisor', $record->issuerName));
        $recordEl->appendChild($xml->createElement('sum1:TipoFactura', $record->invoiceType));

        if (($record->correctiveType ?? null) !== null) {
            $recordEl->appendChild($xml->createElement('sum1:TipoRectificativa', $record->correctiveType));
        }

        if (count($record->correctedInvoices ?? [])) {
            $correctedInvoices = $xml->createElement('sum1:FacturasRectificadas');
            $recordEl->appendChild($correctedInvoices);

            foreach ($record->correctedInvoices as $correctedInvoice) {
                $fixId = $xml->createElement('sum1:IDFacturaRectificada');
                $correctedInvoices->appendChild($fixId);

                $fixId->appendChild($xml->createElement('sum1:IDEmisorFactura', $correctedInvoice->issuerId));
                $fixId->appendChild($xml->createElement('sum1:NumSerieFactura', $correctedInvoice->invoiceNumber));
                $fixId->appendChild($xml->createElement(
                    'sum1:FechaExpedicionFactura',
                    $correctedInvoice->issueDate->format('d-m-Y')
                ));
            }
        }

        if (count($record->replacedInvoices ?? [])) {
            $replacedInvoices = $xml->createElement('sum1:FacturasSustituidas');
            $recordEl->appendChild($replacedInvoices);

            foreach ($record->replacedInvoices as $replacedInvoice) {
                $replId = $xml->createElement('sum1:IDFacturaSustituida');
                $replacedInvoices->appendChild($replId);

                $replId->appendChild($xml->createElement('sum1:IDEmisorFactura', $replacedInvoice->issuerId));
                $replId->appendChild($xml->createElement('sum1:NumSerieFactura', $replacedInvoice->invoiceNumber));
                $replId->appendChild($xml->createElement(
                    'sum1:FechaExpedicionFactura',
                    $replacedInvoice->issueDate->format('d-m-Y')
                ));
            }
        }

        if (
            ($record->correctedBaseAmount ?? null) !== null
            && ($record->correctedTaxAmount ?? null) !== null
        ) {
            $importEl = $xml->createElement('sum1:ImporteRectificacion');
            $recordEl->appendChild($importEl);

            $recordEl->appendChild($xml->createElement('sum1:BaseRectificada', $record->correctedBaseAmount));
            $recordEl->appendChild($xml->createElement('sum1:CuotaRectificada', $record->correctedTaxAmount));
        }

        $recordEl->appendChild($xml->createElement('sum1:DescripcionOperacion', $record->description));

        if (count($record->recipients ?? [])) {
            $recipients = $xml->createElement('sum1:Destinatarios');
            $recordEl->appendChild($recipients);

            foreach ($record->recipients as $recipient) {
                $recipientEl = $xml->createElement('sum1:IDDestinatario');
                $recipients->appendChild($recipientEl);

                $recipientEl->appendChild($xml->createElement('sum1:NombreRazon', $recipient->name));

                if (isset($recipient->country, $recipient->type)) {
                    $foreignId = $xml->createElement('sum1:IDOtro');
                    $recipientEl->appendChild($foreignId);

                    $foreignId->appendChild($xml->createElement('sum1:CodigoPais', $recipient->country));
                    $foreignId->appendChild($xml->createElement('sum1:IDType', $recipient->type));
                    $foreignId->appendChild($xml->createElement('sum1:ID', $recipient->value));
                } else {
                    $recipientEl->appendChild($xml->createElement('sum1:NIF', $recipient->nif));
                }
            }
        }

        $breakdown = $xml->createElement('sum1:Desglose');
        $recordEl->appendChild($breakdown);
        foreach ($record->breakdown ?? [] as $details) {
            $dEl = $xml->createElement('sum1:DetalleDesglose');
            $breakdown->appendChild($dEl);

            $dEl->appendChild($xml->createElement('sum1:Impuesto', $details->taxType));
            if (in_array($details->taxType, array('01', '03'), true)) {
                $dEl->appendChild($xml->createElement('sum1:ClaveRegimen', $details->regimeType));
            }
            $dEl->appendChild($xml->createElement('sum1:CalificacionOperacion', $details->operationType));
            $dEl->appendChild($xml->createElement('sum1:TipoImpositivo', $details->taxRate));
            $dEl->appendChild($xml->createElement('sum1:BaseImponibleOimporteNoSujeto', $details->baseAmount));
            $dEl->appendChild($xml->createElement('sum1:CuotaRepercutida', $details->taxAmount));
        }

        $recordEl->appendChild($xml->createElement('sum1:CuotaTotal', $record->totalTaxAmount));
        $recordEl->appendChild($xml->createElement('sum1:ImporteTotal', $record->totalAmount));
    } elseif ($record->type === 'anulacion') {
        $invoiceId = $xml->createElement('sum1:IDFactura');
        $recordEl->appendChild($invoiceId);

        $invoiceId->appendChild($xml->createElement('sum1:IDEmisorFacturaAnulada', $record->invoiceId->issuerId));
        $invoiceId->appendChild($xml->createElement('sum1:NumSerieFacturaAnulada', $record->invoiceId->invoiceNumber));
        $invoiceId->appendChild(
            $xml->createElement('sum1:FechaExpedicionFacturaAnulada', $record->invoiceId->issueDate->format('d-m-Y'))
        );
    } else {
        throw new Exception('Invalid record type: ' . $record->type);
    }

    $chainEl = $xml->createElement('sum1:Encadenamiento');
    $recordEl->appendChild($chainEl);

    if ($record->previousInvoiceId === null) {
        $chainEl->appendChild($xml->createElement('sum1:PrimerRegistro', 'S'));
    } else {
        $prevEl = $xml->createElement('sum1:RegistroAnterior');
        $chainEl->appendChild($prevEl);

        $prevEl->appendChild($xml->createElement('sum1:IDEmisorFactura', $record->previousInvoiceId->issuerId));
        $prevEl->appendChild($xml->createElement('sum1:NumSerieFactura', $record->previousInvoiceId->invoiceNumber));
        $prevEl->appendChild($xml->createElement(
            'sum1:FechaExpedicionFactura',
            $record->previousInvoiceId->issueDate->format('d-m-Y')
        ));
        $prevEl->appendChild($xml->createElement('sum1:Huella', $record->previousHash));
    }

    $systemEl = $xml->createElement('sum1:SistemaInformatico');
    $recordEl->appendChild($systemEl);

    $systemEl->appendChild($xml->createElement('sum1:NombreRazon', $record->system->vendorName));
    $systemEl->appendChild($xml->createElement('sum1:NIF', $record->system->vendorNif));
    $systemEl->appendChild($xml->createElement('sum1:NombreSistemaInformatico', $record->system->name));
    $systemEl->appendChild($xml->createElement('sum1:IdSistemaInformatico', $record->system->id));
    $systemEl->appendChild($xml->createElement('sum1:Version', $record->system->version));
    $systemEl->appendChild($xml->createElement('sum1:NumeroInstalacion', $record->system->installationNumber));
    $systemEl->appendChild($xml->createElement(
        'sum1:TipoUsoPosibleSoloVerifactu',
        $record->system->onlySupportsVerifactu ? 'S' : 'N'
    ));
    $systemEl->appendChild($xml->createElement(
        'sum1:TipoUsoPosibleMultiOT',
        $record->system->supportsMultipleTaxpayers ? 'S' : 'N'
    ));
    $systemEl->appendChild($xml->createElement(
        'sum1:IndicadorMultiplesOT',
        $record->system->hasMultipleTaxpayers ? 'S' : 'N'
    ));

    $recordEl->appendChild($xml->createElement(
        'sum1:FechaHoraHusoGenRegistro',
        $record->hashedAt->format('c'),
    ));
    $recordEl->appendChild($xml->createElement('sum1:TipoHuella', '01')); // SHA-256
    $recordEl->appendChild($xml->createElement('sum1:Huella', $record->hash));

    return $root;
}

/**
* Get an invoice and returns its lines as a breakdown details array.
*
* @param  Facture    $invoice Target invoice.
*
* @return stdClass[]
*/
function autoverifactuLinesToBreakdown($invoice)
{
    $breakdown = [];

    foreach ($invoice->lines as $line) {
        $details = new stdClass();
        $details->taxType = getDolGlobalString('AUTOVERIFACTU_TAX') ?: '01';
        $details->regimeType = getDolGlobalString('AUTOVERIFACTU_REGIME') ?: '01';
        // TODO: Handle operation types (S1, S2, S3, S4)
        // NOTE: At this moment, autoverifactu only supports S1 operations (Operaciones sujetas y no exentas)
        // NOTE: To modify this constant autoverifactu offers the `autoverifactu` hook.
        $details->operationType = 'S1';
        $details->taxRate = number_format((float) $line->tva_tx, 2, '.', '');
        $details->baseAmount = number_format((float) $line->total_ht, 2, '.', '');
        $details->taxAmount = number_format((float) $line->total_tva, 2, '.', '');
        $breakdown[] = $details;
    }

    return $breakdown;
}

function autoverifactuGetRecordComputerSystem()
{
    if (!autoverifactuSystemCheck()) {
        return;
    }

    global $mysoc;

    $system = new stdClass();
    $system->vendorName = $mysoc->nom;
    $system->vendorNif = $mysoc->idprof1;
    $system->name = 'Auto-Veri*Factu Dolibarr';
    $system->id = 'AV';
    $system->version = '0.0.1';
    $system->installationNumber = '001';
    $system->onlySupportsVerifactu = true;
    // TODO: Handle muti company
    $system->supportsMultipleTaxpayers = false;
    $system->hasMultipleTaxpayers = false;

    return $system;
}

/**
* Calculate the record hash.
*
* @param  stdClass $record Invoice record object.
*
* @return string           Record sha256 hash.
*/
function autoverifactuCalculateRecordHash($record)
{
    if ($record->type == 'alta') {
        $payload  = 'IDEmisorFactura=' . $record->invoiceId->issuerId;
        $payload .= '&NumSerieFactura=' . $record->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFactura=' . $record->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&TipoFactura=' . $record->invoiceType;
        $payload .= '&CuotaTotal=' . $record->totalTaxAmount;
        $payload .= '&ImporteTotal=' . $record->totalAmount;
        $payload .= '&Huella=' . ($record->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
    } elseif ($record->type === 'anulacion') {
        // Otherwise, it's a validated invoice in process to be canceled.
        $payload  = 'IDEmisorFacturaAnulada=' . $record->invoiceId->issuerId;
        $payload .= '&NumSerieFacturaAnulada=' . $record->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFacturaAnulada=' . $record->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&Huella=' . ($record->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
    } else {
        $payload = '';
    }

    return strtoupper(hash('sha256', $payload));
}

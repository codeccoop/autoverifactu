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

use GuzzleHttp\Client;
use UXML\UXML;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

/* Veri*Factu API URLs */
// define('VERIFACTU_BASE_URL', 'https://www1.agenciatributaria.gob.es'); // Production environment
define('VERIFACTU_BASE_URL', 'https://prewww1.aeat.es'); // Test environment

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

    if ($invoice->type > 2) {
        // Skip non recordable invoice types.
        // NOTE: Deposit invoices (3) should be recorded?
        return 0;
    }

    if (
        $invoice->status == 0 &&
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
        $invoice->status == 1 &&
        $action !== 'BILL_CANCEL'
    ) {
        return 0;
    }

    if (empty($conf->facture->multidir_output[$conf->entity])) {
        dol_syslog('Constant $conf->facture->multidir_output not defined', LOG_ERR);
        return -1;
    }

    $invoice->fetch_thirdparty();
    $invoice->fetch_lines();

    $invoiceref = dol_sanitizeFileName($invoice->ref);
    $dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;
    $file = $dir . '/' . $invoiceref . '-verifactu.xml';
    $hidden = $dir . '/.verifactu.xml';

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
        $result = autoverifactuSendInvoice($invoice, $xml);

        // Skip document generation if send does not succed.
        if ($result <= 0) {
            return $result;
        }

        $uxml = UXML::fromString($xml);

        $body = $uxml->get('env:Body');
        $faults = $body ? $body->getAll('env:Fault') : [];

        if (!$body || count($faults) > 0) {
            dol_syslog('Invalid SOAP response with error ' . $fault, LOG_ERR);
            return -1;
        }

        $result = file_put_contents($file, $xml);
        $result = $result && file_put_contents($hidden, $xml);

        if (!$result) {
            dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
            return -1;
        } else {
            $parameters['xml'] = $xml;

            $reshook = $hookmanager->executeHooks(
                'afterAutoverifactuRecord',
                $parameters,
                $invoice,
            );
        }
    } catch (Error | Exception $e) {
        dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
        return -1;
    }

    return 0;
}

/**
 * Send an invoice as a record to the Veri*Factu SOAP endpoints.
 *
 * @param  Facture $invoice Target invoice. Invoice should not be published before
 * @param  string  &$xml    Response body as an XML string.
 *
 * @return int              Return <1 if KO, 0 if skip, 1 if OK.
 */
function autoverifactuSendInvoice($invoice, &$xml)
{
    if (!autoverifactuSystemCheck()) {
        dol_syslog('Veri*Factu bridge does not pass system checks');
        return 0;
    }

    $enabled = getDolGlobalString('AUTOVERIFACTU_ENABLED') == '1';

    if (!$enabled) {
        dol_syslog('Veri*Factu bridge is not enabled');
        return 0;
    }

    if (autoverifactuIsInvoiceRecorded($invoice)) {
        dol_syslog(
            'Skip verifactu invoice registration because invoice #'
            . $invoice->id .
            'is already registered',
        );

        return 0;
    }

    try {
        $record = autoverifactuInvoiceToRecord($invoice);

        if (!$record) {
            throw new Exception('Inconsistent invoice data');
        }
    } catch (Exception $e) {
        dol_syslog('Invoice to record error: ' . $e->getMessage(), LOG_ERR);
        return -1;
    }

    $certPath = DOL_DATA_ROOT . '/' . (getDolGlobalString('AUTOVERIFACTU_CERT') ?: '');
    $certPass = getDolGlobalString('AUTOVERIFACTU_PASSWORD') ?: null;

    if ($certPass) {
        $cert = array($certPath, $certPass);
    } else {
        $cert  = $certPath;
    }

    $envelope = autoverifactuSoapEnvelope(
        $record,
        array(
            'name' => $invoice->thirdparty->nom,
            'id' => $invoice->thirdparty->idprof1,
        ),
    );

    $client = new Client(array('cert' => $cert));

    $res = $client->post(
        '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        array(
            'base_uri' => VERIFACTU_BASE_URL,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; Módulo Auto-Veri*Factu de Dolibarr/0.0.1',
                'Content-Type' => 'text/xml',
            ),
            'body' => $envelope,
        ),
    );

    $xml = $res->getBody()->getContents() . "\n";

    return 1;
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
    $envelope = UXML::newInstance(
        'soapenv:Envelope',
        null,
        array(
            'xmlns:sopaenv' => AUTOVERIFACTU_SOAPENV_NS,
            'xmlns:sum' => AUTOVERIFACTU_SUM_NS,
            'xmlns:sum1' => AUTOVERIFACTU_SUM1_NS,
        ),
    );

    $envelope->add('soapenv:Header');
    $sifRec = $envelope->add('soapenv:Body')->add('sum:RegFactuSIstemaFacturacion');

    $header = $sifRec->add('sum:Cabecera');
    $issuerNode = $header->add('sum1:ObligadoEmision');
    $issuerNode->add('sum1:NombreRazon', $issuer['name']);
    $issuerNode->add('sum1:NIF', $issuer['idprof']);

    if ($representative) {
        $representativeNode = $header->add('sum1:Representante');
        $representativeNode->add('sum1:NombreRazon', $representative['name']);
        $representativeNode->add('sum1:NIF', $representative['idprof']);
    }

    $recordNode = autoverifactuRecordToUXML($record);
    $sifRec->add($recordNode);

    return $envelope->asXML();
}

/**
* Return the invoice as a Veri*Factu record object.
*
* @param  Facture$invoice Target invoice.
*
* @return stdClass|null        Record representation.
*/
function autoverifactuInvoiceToRecord($invoice)
{
    global $mysoc;
    $thirdparty = $invoice->thirdparty;

    switch ($invoice->type) {
        /* Standard invoice */
        case 0:
            if ($invoice->module_source === 'takepos') {
                // Factura simplificada y facturas sin identificación del destinatario (Art. 6.1.D del R.D. 1619/2012).
                $invoiceType = 'F2';
            } else {
                // Factura (Art. 6, 7.2 y 7.3 del R.D. 1619/2012).
                $invoiceType = 'F1';
            }

            break;
        /* Replacement invoice */
        case 1:
        /* Credit notes */
        case 2:
            // Factura rectificativa (Art 80.1 y 80.2 de la Ley 37/1992)
            // $invoice_type = 'R1';
            // Factura rectificativa por impago (Art 80.3 de la Ley 37/1992)
            // $invoice_type = 'R2';
            // Factura rectificativa (Art 80.4 de la Ley 37/1992)
            // $invoice_type = 'R3';
            // Factura rectificativa corriente.
            $invoiceType = 'R4';
            // Factura rectificativa simplificada
            // $invoice_type = 'R5';
            break;
        /* POS simplified invoices */
        default:
            $invoiceType = 'F1';
    }

    $record = new stdClass();

    $record->issuerName = $mysoc->nom;
    $record->invoiceType = $invoiceType;
    $record->description = sprintf(
        'Factura %s a %s (%s)',
        $invoice->ref,
        $thirdparty->idprof1,
        $thirdparty->nom,
    );

    $record->invoiceId = new stdClass();
    $record->invoiceId->issuerId = $mysoc->idprof1;
    $record->invoiceId->invoiceNumber = $invoice->ref;
    $record->invoiceId->issueDate = new DateTimeImmutable(date('Y-m-d', $invoice->date));

    // If is not simplified, add third party data to the record
    if ($record->invoiceType !== 'F2') {
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
            } else {
                // TODO: Where Dolibarr store passports or residence card?
                // 03 Passport, 05 Residence, 06 Others, 07 Unregistered
            }
        } else {
            $recipient->name = $thirdparty->nom;
            $recipient->nif = $thirdparty->idprof1;
        }

        $record->recipients[0] = $recipient;
    }

    if (
        in_array(
            $record->correctiveType,
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
    }

    // If is corrective, then add correctiveInvoices data to the record.
    if ($record->correctiveType !== null) {
        $source_invoice = autoverifactuGetSourceInvoice($invoice);

        if (!$source_invoice) {
            dol_syslog('Can not find the source invoice of the corrective invoice #' . $invoice->id, LOG_ERR);
            return -1;
        } else {
            $source_invoice->fetch_thirdparty();
        }

        $source_id = new stdClass();
        $source_id->issuerId = $source_invoice->thirdparty->idprof1;
        $source_id->invoiceNumber = $source_invoice->ref;
        $source_id->issueDate = new DateTimeImmutable(date('Y-m-d', $source_invoice->date));

        $record->correctedInvoices[0] = $source_id;
    }

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

    $previous = autoverifactuGetLastValidInvoice();
    if ($previous) {
        $record->previousInvoiceId = new stdClass();
        $record->previousInvoiceId->issuerId = $mysoc->idprof1;
        $record->previousInvoiceId->invoiceNumber = $invoice->ref;
        $record->previousInvoiceId->issueDate = new DateTimeImmutable(date('Y-m-d', $previous->date));

        $record->previousHash = substr($previous->hash, 0, 64);
    } else {
        $record->previousInvoiceId = null;
        $record->previousHash = null;
    }

    $record->hashedAt = new DateTimeImmutable();
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
 * @param  stdClass $record Invoice Veri*Factu record object.
 *
 * @return UXML             XML record representation.
 */
function autoverifactuRecordToUXML($record)
{
    $uxml = UXML::newInstance(
        'autoverifactu:Record',
        null,
        array(
            'xmlns:sum' => AUTOVERIFACTU_SUM_NS,
            'xmlns:sum1' => AUTOVERIFACTU_SUM1_NS
        )
    );

    $recordElementName = $record->action === 'register'
        ? 'RegistroAlta'
        : 'RegistroAnulacion';

    $recordElement = $uxml
        ->add('sum:RegistroFactura')
        ->add('sum1:' . $recordElementName);

    $recordElement->add('sum1:IDVersion', '1.0');

    if ($record->action === 'register') {
        $invoice_id = $recordElement->add('sum1:IDFactura');
        $invoice_id->add('sum1:IDEmisorFactura', $record->invoiceId->issuerId);
        $invoice_id->add('sum1:NumSerieFactura', $record->invoiceId->invoiceNumber);
        $invoice_id->add('sum1:FechaExpedicionFactura', $record->invoiceId->issueDate->format('d-m-Y'));

        $recordElement->add('sum1:NombreRazonEmisor', $record->issuerName);
        $recordElement->add('sum1:TipoFactura', $record->invoiceType);

        if ($record->correctiveType !== null) {
            $recordElement->add('sum1:TipoRectificativa', $record->correctiveType);
        }

        if (count($record->correctedInvoices)) {
            $correctedElements = $recordElement->add('sum1:FacturasRectificadas');
            foreach ($record->correctedInvoices as $correctedInvoice) {
                $correctedElement = $correctedElements->add('sum1:IDFacturaRectificada');
                $correctedElement->add('sum1:IDEmisorFactura', $correctedInvoice->issuerId);
                $correctedElement->add('sum1:NumSerieFactura', $correctedInvoice->invoiceNumber);
                $correctedElement->add('sum1:FechaExpedicionFactura', $correctedInvoice->issueDate->format('d-m-Y'));
            }
        }

        if (count($record->replacedInvoices)) {
            $replacedElements = $recordElement->add('sum1:FacturasSustituidas');
            foreach ($record->replacedInvoices as $replacedInvoice) {
                $replacedElement = $replacedElements->add('sum1:IDFacturaSustituida');
                $replacedElement->add('sum1:IDEmisorFactura', $replacedInvoice->issuerId);
                $replacedElement->add('sum1:NumSerieFactura', $replacedInvoice->invoiceNumber);
                $replacedElement->add('sum1:FechaExpedicionFactura', $replacedInvoice->issueDate->format('d-m-Y'));
            }
        }

        if ($record->correctedBaseAmount !== null && $record->correctedTaxAmount !== null) {
            $importElement = $recordElement->add('sum1:ImporteRectificacion');
            $importElement->add('sum1:BaseRectificada', $record->correctedBaseAmount);
            $importElement->add('sum1:CuotaRectificada', $record->correctedTaxAmount);
        }

        $recordElement->add('sum1:DescripcionOperacion', $record->description);

        if (count($record->recipients) > 0) {
            $recipientsElement = $recordElement->add('sum1:Destinatarios');
            foreach ($record->recipients as $recipient) {
                $recipientElement = $recipientsElement->add('sum1:IDDestinatario');
                $recipientElement->add('sum1:NombreRazon', $recipient->name);

                if (isset($recipient->country, $recipient->type)) {
                    $foreignID = $recipientElement->add('sum1:IDOtro');
                    $foreignID->add('sum1:CodigoPais', $recipient->country);
                    $foreignID->add('sum1:IDType', $recipient->type);
                    $foreignID->add('sum1:ID', $recipient->value);
                } else {
                    $recipientElement->add('sum1:NIF', $recipient->nif);
                }
            }
        }

        $breakdownElements = $recordElement->add('sum1:Desglose');
        foreach ($record->breakdown as $breakdownDetails) {
            $breakdownElement = $breakdownElements->add('sum1:DetalleDesglose');
            $breakdownElement->add('sum1:Impuesto', $breakdownDetails->taxType);
            $breakdownElement->add('sum1:ClaveRegimen', $breakdownDetails->regimeType);
            $breakdownElement->add('sum1:CalificacionOperacion', $breakdownDetails->operationType);
            $breakdownElement->add('sum1:TipoImpositivo', $breakdownDetails->taxRate);
            $breakdownElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
            $breakdownElement->add('sum1:CuotaRepercutida', $breakdownDetails->taxAmount);
        }

        $recordElement->add('sum1:CuotaTotal', $record->totalTaxAmount);
        $recordElement->add('sum1:ImporteTotal', $record->totalAmount);
    } else {
        $invoice_id = $recordElement->add('sum1:IDFactura');
        $invoice_id->add('sum1:IDEmisorFacturaAnulada', $record->invoiceId->issuerId);
        $invoice_id->add('sum1:NumSerieFacturaAnulada', $record->invoiceId->invoiceNumber);
        $invoice_id->add('sum1:FechaExpedicionFacturaAnulada', $record->invoiceId->issueDate->format('d-m-Y'));
    }

    $encadenamientoElement = $recordElement->add('sum1:Encadenamiento');
    if ($record->previousInvoiceId === null) {
        $encadenamientoElement->add('sum1:PrimerRegistro', 'S');
    } else {
        $registroAnteriorElement = $encadenamientoElement->add('sum1:RegistroAnterior');
        $registroAnteriorElement->add('sum1:IDEmisorFactura', $record->previousInvoiceId->issuerId);
        $registroAnteriorElement->add('sum1:NumSerieFactura', $record->previousInvoiceId->invoiceNumber);
        $registroAnteriorElement->add('sum1:FechaExpedicionFactura', $record->previousInvoiceId->issueDate->format('d-m-Y'));
        $registroAnteriorElement->add('sum1:Huella', $record->previousHash);
    }

    $sistemaInformaticoElement = $recordElement->add('sum1:SistemaInformatico');
    $sistemaInformaticoElement->add('sum1:NombreRazon', $record->system->vendorName);
    $sistemaInformaticoElement->add('sum1:NIF', $record->system->vendorNif);
    $sistemaInformaticoElement->add('sum1:NombreSistemaInformatico', $record->system->name);
    $sistemaInformaticoElement->add('sum1:IdSistemaInformatico', $record->system->id);
    $sistemaInformaticoElement->add('sum1:Version', $record->system->version);
    $sistemaInformaticoElement->add('sum1:NumeroInstalacion', $record->system->installationNumber);
    $sistemaInformaticoElement->add('sum1:TipoUsoPosibleSoloVerifactu', $record->system->onlySupportsVerifactu ? 'S' : 'N');
    $sistemaInformaticoElement->add('sum1:TipoUsoPosibleMultiOT', $record->system->supportsMultipleTaxpayers ? 'S' : 'N');
    $sistemaInformaticoElement->add('sum1:IndicadorMultiplesOT', $record->system->hasMultipleTaxpayers ? 'S' : 'N');

    $recordElement->add('sum1:FechaHoraHusoGenRegistro', $record->hashedAt->format('c'));
    $recordElement->add('sum1:TipoHuella', '01'); // SHA-256
    $recordElement->add('sum1:Huella', $record->hash);

    return $uxml->get('sum:RegistroFactura');
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
        // TODO: Handle tax types (01 IVA, 02 IPSI, 03 IGIC, 05 Otros)
        $details->taxType = '01';
        // TODO: Handle regime types (01..20)
        $details->regimeType = '01';
        // TODO: Handle operation types (S1, S2, S3, S4)
        $details->operationType = 'S1';
        $details->taxRate = number_format((float) $line->tva_tax, 2, '.', '');
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
    $system->name = 'Módulo Auto-Veri*Factu de Dolibarr';
    $system->id = 'AV';
    $system->version = '0.0.1';
    $system->installationNumber = '001';
    $system->onlySupportsVerifactu = true;
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
    if (!isset($record->status)) {
        return '';
    }

    // It's a draft invoice in process to be validated
    if ($record->status == 0) {
        $payload  = 'IDEmisorFactura=' . $record->invoiceId->issuerId;
        $payload .= '&NumSerieFactura=' . $record->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFactura=' . $record->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&TipoFactura=' . $record->invoiceType;
        $payload .= '&CuotaTotal=' . $record->totalTaxAmount;
        $payload .= '&ImporteTotal=' . $record->totalAmount;
        $payload .= '&Huella=' . ($record->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
    // Otherwise, it's a validated invoice in process to be canceled.
    } else {
        $payload  = 'IDEmisorFacturaAnulada=' . $record->invoiceId->issuerId;
        $payload .= '&NumSerieFacturaAnulada=' . $record->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFacturaAnulada=' . $record->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&Huella=' . ($record->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
    }

    return strtoupper(hash('sha256', $payload));
}

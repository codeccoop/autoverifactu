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

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Services\AeatClient;
use UXML\UXML;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

function autoverifactuRegisterInvoice($invoice, $action)
{
    global $db, $conf, $hookmanager;

    $enabled = getDolGlobalString('AUTOVERIFACTU_ENABLED') == '1';
    if (!$enabled) {
        dol_syslog('Veri*Factu bridge is not enabled');
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
        return -1;
    } elseif (
        $invoice->status == 1 &&
        $action !== 'BILL_CANCEL'
    ) {
        return -1;
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
        return -1;
    }

    try {
        $xml = autoverifactuSendInvoice($invoice);
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

function autoverifactuSendInvoice($invoice)
{
    global $mysoc;

    $record = autoverifactuInvoiceToRecord($invoice);

    // Define los datos del SIF
    $system = autoverifactuGetComputerSystem();

    // Crea un cliente para el webservice de la AEAT
    $taxpayer = new FiscalIdentifier($mysoc->nom, $mysoc->idprof1);
    $client = new AeatClient(
        $system,
        $taxpayer,
        DOL_DATA_ROOT . '/' . (getDolGlobalString('AUTOVERIFACTU_CERT') ?: ''),
        getDolGlobalString('AUTOVERIFACTU_PASSWORD') ?: null,
    );

    $client->setProduction(false); // <-- para usar el entorno de preproducción
    $res = $client->send([$record]);

    // Obtiene la respuesta
    return $res->asXML() . "\n";
}

function autoverifactuInvoiceToRecord($invoice)
{
    global $mysoc;
    $thirdparty = $invoice->thirdparty;

    switch ($invoice->type) {
        case 0:
            $invoice_type = InvoiceType::Factura;
            break;
        // case 1:
        //     $invoice_type = InvoiceType::Sustitutiva;
        //     break;
        case 1:
            $invoice_type = InvoiceType::R1;
            break;
        case 2:
            $invoice_type = InvoiceType::R2;
            break;
        default:
            $invoice_type = InvoiceType::Factura;
            break;
    }

    // Genera un registro de facturación
    $record = new RegistrationRecord();
    $record->invoiceId = new InvoiceIdentifier();
    $record->invoiceId->issuerId = $mysoc->idprof1;
    $record->invoiceId->invoiceNumber = $invoice->ref;
    $record->invoiceId->issueDate = new DateTimeImmutable(date('Y-m-d', $invoice->date));
    $record->issuerName = $mysoc->nom;
    $record->invoiceType = $invoice_type;
    $record->description = sprintf(
        'Factura %s a %s (%s)',
        $invoice->ref,
        $thirdparty->idprof1,
        $thirdparty->nom,
    );

    if ($record->invoiceType !== InvoiceType::Simplificada) {
        $record->recipients[0] = new FiscalIdentifier($thirdparty->nom, $thirdparty->idprof1);
    }

    if ($record->invoiceType === InvoiceType::R1) {
        $record->correctiveType = 'S';
    } elseif ($record->invoiceType === InvoiceType::R2) {
        $record->correctiveType = 'I';
    }

    if ($record->correctiveType !== null) {
        $source_invoice = autoverifactuGetSourceInvoice($invoice);
        $source_invoice->fetch_thirdparty();

        $source_id = new InvoiceIdentifier();
        $source_id->issuerId = $source_invoice->thirdparty->idprof1;
        $source_id->invoiceNumber = $source_invoice->ref;
        $source_id->issueDate = new DateTimeImmutable(date('Y-m-d', $source_invoice->date));

        $record->correctedInvoices[0] = $source_id;
    }

    $record->breakdown = autoverifactuGetInvoiceBreakdown($invoice);

    $tax_total = 0;
    $base_total = 0;
    foreach ($record->breakdown as $line) {
        $tax_total += (float) $line->taxAmount;
        $base_total += (float) $line->baseAmount;
    }

    $record->totalTaxAmount = number_format((float) $tax_total, 2, '.', '');
    $record->totalAmount = number_format(
        $base_total + $tax_total,
        2,
        '.',
        '',
    );

    $record->hashedAt = new DateTimeImmutable();
    $record->hash = $record->calculateHash();

    $previous = autoverifactuGetLastValidInvoice();
    if ($previous) {
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = $mysoc->idprof1;
        $record->previousInvoiceId->invoiceNumber = $invoice->ref;
        $record->previousInvoiceId->issueDate = new DateTimeImmutable(date('Y-m-d', $previous->date));

        $record->previousHash = substr($previous->hash, 0, 64);
    } else {
        $record->previousInvoiceId = null;
        $record->previousHash = null;
    }

    $record->validate();
    return $record;
}

function autoverifactuGetInvoiceBreakdown($invoice)
{
    $lines = [];

    foreach ($invoice->lines as $line) {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::S1;
        $details->taxRate = '21.00';
        $details->baseAmount = number_format((float) $line->total_ht, 2, '.', '');
        $details->taxAmount = number_format((float) $line->total_tva + $line->total_localtax1 + $line->total_localtax2, 2, '.', '');
        $lines[] = $details;
    }

    return $lines;
}

function autoverifactuGetComputerSystem()
{
    global $mysoc;

    $system = new ComputerSystem();
    $system->vendorName = $mysoc->nom;
    $system->vendorNif = $mysoc->idprof1;
    $system->name = 'Módulo Auto-Veri*Factu de Dolibarr';
    $system->id = 'AV';
    $system->version = '0.0.1';
    $system->installationNumber = '001';
    $system->onlySupportsVerifactu = true;
    $system->supportsMultipleTaxpayers = false;
    $system->hasMultipleTaxpayers = false;
    $system->validate();

    return $system;
}

function autoverifactuValidation($invoice)
{
}

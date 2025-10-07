<?php

/* Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
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
 * \file    htdocs/custom/verifactu/lib/verifactu.lib.php
 * \ingroup verifactu
 * \brief   Library files with common functions for Verifactu
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CorrectiveType;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Services\AeatClient;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

function verifactuAdminPrepareHead()
{
    global $langs, $conf;
    $langs->load('verifactu@verifactu');

    $h = 0;
    $head = array();

    $head[$h][0] = DOL_URL_ROOT . '/custom/verifactu/admin/setup.php';
    $head[$h][1] = $langs->trans('Settings');
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/verifactu/admin/about.php';
    $head[$h][1] = $langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu', 'remove');

    return $head;
}

function verifactuRegisterInvoice($invoice, $action)
{
    global $db, $conf, $hookmanager;

    if (
        $invoice->status == 0 &&
        !in_array(
            $action,
            array(
                'BILL_VALIDATE',
                'DON_VALIDATE',
                'CASHCONTROL_VALIDATE',
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

    $invoiceref = dol_sanitizeFileName($invoice->ref);
    $dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;
    $file = $dir . '/' . $invoiceref . '.xml';

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

    $hookmanager->initHooks(array('verifactu'));

    $parameters = array(
        'file' => $file,
        'invoice' => $invoice,
    );

    $reshook = $hookmanager->executeHooks(
        'beforeVerifactoRecord',
        $parameters,
        $object,
    );

    if ($reshook < 0) {
        dol_syslog('Skip verfiactu record registry for invoice #' . $invoice->id);
        return -1;
    }

    try {
        $xml = verifactuSendInvoice($invoice);
        $result = file_put_contents($file, $xml);

        if (!$result) {
            dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
            return -1;
        }
    } catch (Exception $e) {
        dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
        return -1;
    }

    return 0;
}

function verifactuSendInvoice($invoice)
{
    global $mysoc;

    $record = verifactuInvoiceToRecord($invoice);

    // Define los datos del SIF
    $system = verifactuGetComputerSystem();

    // Crea un cliente para el webservice de la AEAT
    $taxpayer = new FiscalIdentifier($mysoc->nom, $mysoc->idprof1);
    $client = new AeatClient(
        $system,
        $taxpayer,
        DOL_DATA_ROOT . '/' . (getDolGlobalString('VERIFACTU_CERT') ?: ''),
        getDolGlobalString('VERIFACTU_PASSWORD') ?: null,
    );

    $client->setProduction(false); // <-- para usar el entorno de preproducción
    $res = $client->send([$record]);

    // Obtiene la respuesta
    return $res->asXML() . "\n";
}

function verifactuInvoiceToRecord($invoice)
{
    global $mysoc;
    $thirdparty = $invoice->thirdparty;

    switch ($invoice->type) {
        case 0:
            $invoice_type = InvoiceType::Factura;
            break;
        case 1:
            $invoice_type = InvoiceType::Sustitutiva;
            break;
        case 2:
            $invoice_type = InvoiceType::R1;
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

    if ($record->invoiceType === InvoiceType::Sustitutiva) {
        $record->correctiveType = 'S';

        $record->
    } elseif ($record->invoiceType === InvoiceType::R1) {
        $record->correctiveType = 'I';
    }

    if ($record->correctiveType !== null) {
        $source_invoice = verifactuGetSourceInvoice($invoice);

        $source_id = new InvoiceIdentifier();
        $source_id->issuerId = $source_invoice->thirdparty->idprof1;
        $source_id->invoiceNumber = $source_invoice->ref;
        $source_id->issueDate = new DateTimeImmutable(date('Y-m-d', $source_invoice->date));

        $record->correctedInvoices[0] = $source_id;
    }

    $record->breakdown = verifactuGetInvoiceBreakdown($invoice);

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

    $previous = verifactuGetLastValidInvoice();
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

function verifactuGetInvoiceBreakdown($invoice)
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

function verifactuGetComputerSystem()
{
    $system = new ComputerSystem();
    $system->vendorName = 'Còdec Solucions Digitals, SCCL';
    $system->vendorNif = 'F13976543';
    $system->name = 'Módulo Verifactu de Dolibarr';
    $system->id = 'DV';
    $system->version = '0.0.1';
    $system->installationNumber = '001';
    $system->onlySupportsVerifactu = false;
    $system->supportsMultipleTaxpayers = false;
    $system->hasMultipleTaxpayers = false;
    $system->validate();

    return $system;
}

function verifactuUploadCert()
{
    global $conf;
    $upload_dir = $conf->verifactu->dir_output . '/';

    if (!is_dir($upload_dir)) {
        dol_mkdir($upload_dir);
    }

    if (!empty($_FILES['userfile']['tmp_name'])) {
        $file = $_FILES['userfile'];
        $filename = dol_sanitizeFileName($file['name']);
        $dest = $upload_dir . $filename;

        if (dol_move_uploaded_file($file['tmp_name'], $dest, 1, 0, $file['error'])) {
            // $file_id = dol_add_file($dest, $filename, 'verifactu');
        } else {
            return;
        }
    }

    return $dest;
}

function verifactuPrepareSetupPost()
{
    $fields = ['COMPANY_NAME', 'VAT', 'CERT', 'PASSWORD'];

    foreach ($fields as $field) {
        $field = 'VERIFACTU_' . $field;
        $value = verifactuGetPost($field);
        $_POST[$field] = $value;
    }

    return $_POST;
}

function verifactuGetLastValidInvoice()
{
    global $db;

    $sql = 'SELECT rowid FROM ' . $db->prefix() . 'factures';
    $sql .= ' WHERE fk_statut > 0';
    $sql .= ' ORDER BY rowid DESC LIMIT 1';
    $result = $db->query($sql);

    if ($result && $db->num_rows($result)) {
        $obj = db->fetch_object($resql);
        $invoice = new Facutre($db);
        $invoice->fetch($obj->rowid);
        return $invoice;
    }
}

function verifactuGetSourceInvoice($invoice)
{
    $prev_id = $invoice->fk_facture_source;

    global $db;
    $invoice = new Facture($db);
    $found = $invoice->fetch($prev_id);

    if (!$found) {
        return;
    }

    return new RegistrationRecord($invoice);
}

function verifactuGetPost($field)
{
    return GETPOST($field) ?: getDolGlobalString($field);
}

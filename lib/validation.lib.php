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
 * \file    htdocs/custom/autoverifactu/lib/validation.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with functions to interface with the Veri*Factu API
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

function autoverifactuIntegrityValidation($invoice)
{
    $blockedlog = autoverifactuFetchBlockedLog($invoice);

    if (!$blockedlog) {
        return 0;
    }

    $signatrueCheck = $blockedlog->checkSignature();
    if (!$signatrueCheck) {
        return -1;
    }

    $record = autoverifactuInvoiceToRecord($invoice);
    $immutable = autoverifactuRecordFromLog($blockedlog);

    $error = $record->hash !== $immutable->hash;
    if ($error) {
        return -1;
    }
}

function autoverifactuFetchBlockedLog($invoice)
{
    global $db, $confg;

    $sql = 'SELECT rowid FROM ' . $db->prefix() . 'blockedlog';
    $sql . ' WHERE element = \'facture\'';
    $sql . ' AND entity = ' . $confg->entity;
    $sql . ' AND action = \'BILL_VALIDATION\'';
    $sql . ' AND fk_object = ' . $invoice->rowid;

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $blockedlog = new BlockedLog($db);
        $blockedlog->fetch($obj->rowid);
        return $blockedlog;
    }
}

/**
 * Gets the source invoice from the fk_facture_source value.
 *
 * @param Facture Invoice object.
 *
 * @return Facture|null
 */
function autoverifactuGetLastValidInvoice()
{
    global $db;

    $sql = 'SELECT rowid FROM ' . $db->prefix() . 'facture';
    $sql .= ' WHERE fk_statut > 0';
    $sql .= ' ORDER BY date_valid DESC';
    $result = $db->query($sql);

    if ($result && $db->num_rows($result)) {
        $obj = $db->fetch_object($result);
        $invoice = new Facutre($db);
        $invoice->fetch($obj->rowid);
        return $invoice;
    }
}

/**
 * Gets the source invoice from the fk_facture_source value.
 *
 * @param Facture Invoice object.
 *
 * @return Facture|null
 */
function autoverifactuGetSourceInvoice($invoice)
{
    $prev_id = $invoice->fk_facture_source;
    if (!$prev_id) {
        return;
    }

    global $db;
    $invoice = new Facture($db);
    $found = $invoice->fetch($prev_id);

    if (!$found) {
        return;
    }

    return $invoice;
}

function autoverifactuRecordFromLog($blockedlog)
{
    // TODO: Implement this for validation
}

/**
* Performs a verifactu system requirements check.
*
* @return int 1 if OK, 0 if KO.
*/
function autoverifactuSystemCheck()
{
    global $mysoc;

    if (empty($mysoc->nom) || empty($mysoc->idprof1)) {
        return 0;
    }

    require_once DOL_DOCUMENT_ROOT . '/core/lib/profid.lib.php';
    $check = isValidTinForES($mysoc->idprof1);
    if ($check < 0) {
        return 0;
    }

    $certpath = getDolGlobalString('AUTOVERIFACTU_CERT');
    if (!($certpath && is_file($certpath))) {
        return 0;
    }

    $docpath = getDolGlobalString('AUTOVERIFACTU_REPONSABILITY_DOC');
    if (!($docpath && is_file($docpath))) {
        return 0;
    }

    return 1;
}

function autoverifactuValidateRecord($record)
{
    if (!isset($record->breakdown, $record->totalTaxAmount, $record->totalAmount)) {
        return 0;
    }

    $expectedTax = 0;
    $expectedBase = 0;
    foreach ($record->breakdown as $details) {
        if (!isset($details->taxAmount, $details->baseAmount, $details->taxRate)) {
            return 0;
        }

        $validTaxAmount = false;
        $expectedTax = $details->baseAmount * $details->taxRate / 100;
        for ($t = -0.02; $t <= 0.02; $t += 0.01) {
            $taxAmount = number_format($expectedTax + $t, 2, '.', '');
            if ($details->taxAmount === $taxAmount) {
                $validTaxAmount = true;
                break;
            }
        }

        if (!$validTaxAmount) {
            return 0;
        }

        $expectedTax += $details->taxAmount;
        $expectedBase += $details->baseAmount;
    }

    $expectedTax = number_format($expectedTax, 2, '.', '');
    $expectedBase = number_format($expectedBase, 2, '.', '');
    $expectedTotal = number_format($expectedTax + $expectedBase, 2, '.', '');

    $isTotalValid = false;
    for ($t = -0.02; $t <= 0.02; $t += 0.01) {
        $total = number_format($expectedTotal + $t, 2, '.', '');
        if ($record->totalAmount === $total) {
            $isTotalValid = true;
            break;
        }
    }

    return (int) $isTotalValid;
}

function autoverifactuIsInvoiceRecorded($invoice)
{
    $invoice->fetch_optionals($invoice->id);
    return !!($invoice->array_options['verifactued'] ?? false);
}

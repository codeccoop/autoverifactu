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

function autoverifactuValidation($invoice)
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
    if (emptu($prev_id)) {
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
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

    $record = autoverifactuInvoiceRecord($invoice);
    $immutable = autoverifactuInvoiceRecordFromLog($blockedlog);

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

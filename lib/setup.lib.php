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
 * \file    htdocs/custom/autoverifactu/lib/setup.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with setup page util functions for AutoVerifactu
 */

/* Libraries */
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

function autoverifactuPrepareSetupPost()
{
    $fields = ['CERT', 'PASSWORD'];

    foreach ($fields as $field) {
        $field = 'AUTOVERIFACTU_' . $field;
        $value = autoverifactuGetPost($field);
        $_POST[$field] = $value;
    }

    return $_POST;
}

function autoverifactuSetupPost()
{
    global $db, $mysoc, $conf;

    $certpath = autoverifactuGetPost('AUTOVERIFACTU_CERT');
    $fullcertpath = DOL_DATA_ROOT . '/' . $certpath;
    dolibarr_set_const($db, 'AUTOVERIFACTU_CERT', (string) $certpath);

    $password = autoverifactuGetPost('AUTOVERIFACTU_PASSWORD');
    dolibarr_set_const($db, 'AUTOVERIFACTU_PASSWORD', $password);

    $enabled = autoverifactuGetPost('AUTOVERIFACTU_ENABLED');

    $enabled = $enabled && !empty($mysoc->idprof1) && !empty($mysoc->nom);
    $enabled = $enabled && is_file($fullcertpath);
    $enabled = $enabled && getDolGlobalString('FAC_FORCE_DATE_VALIDATION');
    $enabled = $enabled && !empty($conf->modules['blockedlog']);

    dolibarr_set_const($db, 'AUTOVERIFACTU_ENABLED', (string) $enabled);
}

function autoverifactuGetPost($field)
{
    return GETPOST($field) ?: getDolGlobalString($field);
}

function autoverifactuUploadCert()
{
    global $conf;
    $upload_dir = $conf->autoverifactu->dir_output . '/';

    if (!is_dir($upload_dir)) {
        dol_mkdir($upload_dir);
    }

    if (!empty($_FILES['userfile']['tmp_name'])) {
        $file = $_FILES['userfile'];
        $filename = dol_sanitizeFileName($file['name']);
        $dest = $upload_dir . $filename;

        if (dol_move_uploaded_file($file['tmp_name'], $dest, 1, 0, $file['error'])) {
            // $file_id = dol_add_file($dest, $filename, 'autoverifactu');
        } else {
            return;
        }
    }

    return $dest;
}

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

function autoverifactuGetSourceInvoice($invoice)
{
    $prev_id = $invoice->fk_facture_source;

    global $db;
    $invoice = new Facture($db);
    $found = $invoice->fetch($prev_id);

    if (!$found) {
        return;
    }

    return $invoice;
}

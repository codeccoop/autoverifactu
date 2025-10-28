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

require_once __DIR__ . '/validation.lib.php';

function autoverifactuGetPost($field)
{
    return GETPOST($field) ?: getDolGlobalString($field);
}

function autoverifactuSetupPost()
{
    global $db;

    $certpath = autoverifactuGetPost('AUTOVERIFACTU_CERT');
    dolibarr_set_const($db, 'AUTOVERIFACTU_CERT', (string) $certpath);

    $password = autoverifactuGetPost('AUTOVERIFACTU_PASSWORD');
    dolibarr_set_const($db, 'AUTOVERIFACTU_PASSWORD', $password);

    $tax = autoverifactuGetPost('AUTOVERIFACTU_TAX') ?: '01';
    dolibarr_set_const($db, 'AUTOVERIFACTU_TAX', $tax);

    $regime = autoverifactuGetPost('AUTOVERIFACTU_REGIME') ?: '01';
    dolibarr_set_const($db, 'AUTOVERIFACTU_REGIME', $regime);

    $enabled = autoverifactuGetPost('AUTOVERIFACTU_ENABLED');
    $enabled = $enabled && autoverifactuSystemCheck();
    dolibarr_set_const($db, 'AUTOVERIFACTU_ENABLED', (string) $enabled);
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

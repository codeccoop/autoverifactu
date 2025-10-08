<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
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
 * \file    htdocs/custom/verifactu/admin/setup.php
 * \ingroup verifactu
 * \brief   Verifactu setup page.
 */

require_once dirname(__DIR__) . '/env.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dirname(__DIR__) . '/lib/verifactu.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

global $db, $langs, $conf;

// Translations
$langs->loadLangs(array('admin', 'verifactu@verifactu'));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('verifactusetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
}

if (!class_exists('FormFile')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
}

$formSetup = new FormSetup($db);
$formfile = new FormFile($db);

// Enter here all parameters in your setup page
$invalid = false;
$toggle = $formSetup->newItem('VERIFACTU_ENABLED')->setAsYesNo();
$toggle->fieldValue = '1';

$formSetup->newItem('COMPANY_SECTION_TITLE')->setAsTitle();

$name_field = $formSetup->newItem('VERIFACTU_COMPANY_NAME');
$name_field->fieldValue = verifactuGetPost('VERIFACTU_COMPANY_NAME') ?: $mysoc->nom;
$name_field->fieldParams['isMandatory'] = 1;
$name_field->fieldAttr['placeholder'] = $langs->trans('Your company name');
$name_field->fieldAttr['disabled'] = true;
$name_field->fieldAttr['error'] = empty($name_field->fieldValue);
$invalid = $invalid || $name_field->fieldAttr['error'];

$vat_field = $formSetup->newItem('VERIFACTU_VAT');
$vat_field->fieldValue = verifactuGetPost('VERIFACTU_VAT') ?: $mysoc->idprof1;
$vat_field->fieldParams['isMandatory'] = 1;
$vat_field->fieldAttr['placeholder'] = $langs->trans('Your company VAT number');
$vat_field->fieldAttr['disabled'] = true;
$vat_field->fieldAttr['error'] = empty($vat_field->fieldValue);
$invalid = $invalid || $vat_field->fieldAttr['error'];

$cert_field = $formSetup->newItem('VERIFACTU_CERT');
$cert_field->fieldParams['isMandatory'] = 1;
$cert_field->fieldAttr['placeholder'] = $langs->trans('path/to/your/certificate.pem');
$cert_field->fieldAttr['disabled'] = true;
$cert_field->fieldAttr['error'] = empty($cert_field->fieldValue);
$invalid = $invalid || $cert_field->fieldAttr['error'];

$pass_field = $formSetup->newItem('VERIFACTU_PASSWORD')->setAsGenericPassword();

$formSetup->newItem('SYSTEM_SECTION_TITLE')->setAsTitle();

$date_valid_field = $formSetup->newItem('VERIFACTU_DATE_VALIDATION');
$date_valid_field->fieldValue = $langs->trans('Active');
$date_valid_field->fieldParams['isMandatory'] = 1;
$date_valid_field->fieldAttr['disabled'] = true;
$date_valid_field->fieldAttr['error'] = empty(getDolGlobalInt('FAC_FORCE_DATE_VALIDATION'));
$invalid = $invalid || $date_valid_field->fieldAttr['error'];

$blocklog_field = $formSetup->newItem('VERIFACTU_BLOCKEDLOG_ENABLED');
$blocklog_field->fieldValue = $langs->trans('Active');
$blocklog_field->fieldParams['isMandatory'] = 1;
$blocklog_field->fieldAttr['disabled'] = true;
$blocklog_field->fieldAttr['error'] = empty($conf->modules['blockedlog']);
$invalid = $invalid || $blocklog_field->fieldAttr['error'];

if ($invalid) {
    $toggle->fieldAttr['disabled'] = true;
    $toggle->fieldAttr['error'] = true;

    ob_start();

    ?>
    <div style="opacity:0.4">
        <div id="confirm_VERIFACTU_ENABLED" title="" style="display: none;"></div>
        <span id="set_VERIFACTU_ENABLED" class="valignmiddle inline-block linkobject" style="cursor: default;">
            <span class="fas fa-toggle-off" style=" color: #999;" title="Disabled"></span>
        </span>
        <span id="del_VERIFACTU_ENABLED" class="valignmiddle inline-block linkobject hideobject" style="cursor: default;">
            <span class="fas fa-toggle-on font-status4" style="" title="Enabled"></span>
        </span>
    </div>
    <?php

    $toggle->fieldOverride = ob_get_clean();
}

$setupnotempty += count($formSetup->items);

/*
 * Actions
 */

if ($action === 'update' && !empty($user->admin)) {
    verifactuSetupPost();

    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'upload' && !empty($user->admin)) {
    $filepath = verifactuUploadCert();

    if ($filepath) {
        $filepath = str_replace(DOL_DATA_ROOT . '/', '', $filepath);
        dolibarr_set_const($db, 'VERIFACTU_CERT', $filepath);
        $cert_field->fieldValue = $filepath;
        header('Location: ' . $_SERVER['PHP_SELF']);
    } else {
        dol_syslog('Unable to upload the user cert file', LOG_ERR);
        dolibarr_set_const($db, 'VERIFACTU_CERT', null);

        http_response_code(400);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?uploaderror=1');
    }
}

$action = 'edit';

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = 'VerifactuSetup';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-verifactu page-admin');

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

echo load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = verifactuAdminPrepareHead();
echo dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "verifactu@verifactu");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("VerifactuSetupPage").'</span><br><br>';

if (!empty($formSetup->items)) {
    echo '<div id="verifactuSetupForm">';
    echo $formSetup->generateOutput(true);
    echo '</div>';
}

if (empty($setupnotempty)) {
    echo '<br>'.$langs->trans("NothingToSetup");
}

$formfile->form_attach_new_file(
    $_SERVER["PHP_SELF"] . '?action=upload',
    $langs->trans('Upload your certificate'),
    0,
    0,
    1,
    50,
    null,
    '',
    1,
    '',
    0,
    'verifactu-certupload',
    '.pem,.p12',
    '',
    0,
    0,
    1,
    0
);

// Page end
echo dol_get_fiche_end();

llxFooter();
$db->close();

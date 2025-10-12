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
 * \file    htdocs/custom/autoverifactu/admin/setup.php
 * \ingroup autoverifactu
 * \brief   Autoverifactu setup page.
 */

require_once dirname(__DIR__) . '/env.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/profid.lib.php';

require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';
require_once dirname(__DIR__) . '/lib/setup.lib.php';
require_once dirname(__DIR__) . '/lib/validation.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

global $db, $langs, $conf;

// Translations
$langs->loadLangs(array('admin', 'autoverifactu@autoverifactu'));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('autoverifactusetup', 'globalsetup'));

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
$toggle = $formSetup->newItem('AUTOVERIFACTU_ENABLED')->setAsYesNo();
$toggle->fieldValue = '1';

$responsability = $formSetup->newItem('AUTOVERIFACTU_RESPONSABILITY')->setAsYesNo();
$is_responsible = !!getDolGlobalString('AUTOVERIFACTU_RESPONSABILITY');

ob_start();
?>
<div style="opacity:0.4">
    <div
        id="confirm_AUTOVERIFACTU_ENABLED"
        title=""
        style="display: none"
        aria-hidden="true"
    ></div>
    <span
        id="set_AUTOVERIFACTU_ENABLED"
        class="valignmiddle inline-block linkobject"
        style="cursor: default; display: <?php echo ($is_responsible ? 'none' : 'inline') ?>"
        aria-hidden="<?php echo ($is_responsible ? 'false' : 'true') ?>"
    >
        <span class="fas fa-toggle-off" style=" color: #999;" title="Disabled"></span>
    </span>
    <span
        id="del_AUTOVERIFACTU_ENABLED"
        class="valignmiddle inline-block linkobject hideobject"
        style="cursor: default; display: <?php echo ($is_responsible ? 'inline' : 'none') ?>"
        aria-hidden="<?php echo ($is_responsible ? 'true' : 'false') ?>"
    >
        <span class="fas fa-toggle-on font-status4" style="" title="Enabled"></span>
    </span>
</div>
<?php

$responsability->fieldOverride = ob_get_clean();
$invalid = $invalid || !$is_responsible;

$formSetup->newItem('COMPANY_SECTION_TITLE')->setAsTitle();

$name_field = $formSetup->newItem('AUTOVERIFACTU_COMPANY_NAME');
$name_field->fieldValue = autoverifactuGetPost('AUTOVERIFACTU_COMPANY_NAME') ?: $mysoc->nom;
$name_field->fieldParams['isMandatory'] = 1;
$name_field->fieldAttr['placeholder'] = $langs->trans('Your company name');
$name_field->fieldAttr['disabled'] = true;
$name_field->fieldAttr['error'] = empty($name_field->fieldValue);
$invalid = $invalid || $name_field->fieldAttr['error'];

$vat_field = $formSetup->newItem('AUTOVERIFACTU_VAT');
$vat_field->fieldValue = autoverifactuGetPost('AUTOVERIFACTU_VAT') ?: $mysoc->idprof1;
$vat_field->fieldParams['isMandatory'] = 1;
$vat_field->fieldAttr['placeholder'] = $langs->trans('Your company VAT number');
$vat_field->fieldAttr['disabled'] = true;
$vat_field->fieldAttr['error'] = !isValidTinForES($vat_field->fieldValue);
$invalid = $invalid || $vat_field->fieldAttr['error'];

$cert_field = $formSetup->newItem('AUTOVERIFACTU_CERT');
$cert_field->fieldParams['isMandatory'] = 1;
$cert_field->fieldAttr['placeholder'] = $langs->trans('path/to/your/certificate.pem');
$cert_field->fieldAttr['disabled'] = true;
$cert_field->fieldAttr['error'] = !is_file(DOL_DATA_ROOT . '/' . $cert_field->fieldValue);
$invalid = $invalid || $cert_field->fieldAttr['error'];

$pass_field = $formSetup->newItem('AUTOVERIFACTU_PASSWORD')->setAsGenericPassword();
$pass_field->fieldParams['isMandatory'] = 1;
$pass_field->fieldAttr['error'] = empty(getDolGlobalString('AUTOVERIFACTU_PASSWORD'));
$invalid = $invalid || $pass_field->fieldAttr['error'];

$formSetup->newItem('FISCAL_SECTION_TITLE')->setAsTitle();

$regimes = array(
    '01' => 'Operación de régimen general',
    '02' => 'Exportación',
    '03' => 'Operaciones a las que se aplique el régimen especial de bienes usados',
    '04' => 'Régimen especial del oro de inversión',
    '05' => 'Régimen especial de las agencias de viajes',
    '06' => 'Régimen especial grupo de entidades en IVA',
    '07' => 'Régimen especial del criterio de caja',
    '08' => 'Operaciones sujetas al IPSI / IGIC',
    '09' => 'Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras',
    '10' => 'Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial, de autor u otros por cuenta',
    '11' => 'Operaciones de arrendamiento de local de negocio',
    '14' => 'Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública',
    '15' => 'Factura con IVA pendiente de devengo en operaciones de tracto sucesivo',
    '17' => 'Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX',
    '18' => 'Recargo de equivalencia',
    '19' => 'Operaciones de actividades incluidas en el Régimen Especial de Agricultura, Ganadería y Pesca',
    '20' => 'Régimen simplificado',
);

$regimeField = $formSetup->newItem('AUTOVERIFACTU_REGIME')->setAsSelect($regimes);
$regimeField->fieldParams['isMandatory'] = 1;
$regimeField->defaultFieldValue = '01';

$taxes = array(
    '01' => 'IVA',
    '02' => 'IPSI',
    '03' => 'IGIC',
    '05' => 'Otros',
);

$regimeField = $formSetup->newItem('AUTOVERIFACTU_TAX')->setAsSelect($taxes);
$regimeField->fieldParams['isMandatory'] = 1;
$regimeField->defaultFieldValue = '01';

$formSetup->newItem('SYSTEM_SECTION_TITLE')->setAsTitle();

$date_valid_field = $formSetup->newItem('AUTOVERIFACTU_DATE_VALIDATION');
$date_valid_field->fieldValue = $langs->trans('Active');
$date_valid_field->fieldParams['isMandatory'] = 1;
$date_valid_field->fieldAttr['disabled'] = true;
$date_valid_field->fieldAttr['error'] = empty(getDolGlobalInt('FAC_FORCE_DATE_VALIDATION'));
$invalid = $invalid || $date_valid_field->fieldAttr['error'];

$blocklog_field = $formSetup->newItem('AUTOVERIFACTU_BLOCKEDLOG_ENABLED');
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
        <div id="confirm_AUTOVERIFACTU_ENABLED" title="" style="display: none;"></div>
        <span id="set_AUTOVERIFACTU_ENABLED" class="valignmiddle inline-block linkobject" style="cursor: default;">
            <span class="fas fa-toggle-off" style=" color: #999;" title="Disabled"></span>
        </span>
        <span id="del_AUTOVERIFACTU_ENABLED" class="valignmiddle inline-block linkobject hideobject" style="cursor: default;">
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
    autoverifactuSetupPost();

    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'upload' && !empty($user->admin)) {
    $filepath = autoverifactuUploadCert();

    if ($filepath) {
        $filepath = str_replace(DOL_DATA_ROOT . '/', '', $filepath);
        dolibarr_set_const($db, 'AUTOVERIFACTU_CERT', $filepath);
        $cert_field->fieldValue = $filepath;
        header('Location: ' . $_SERVER['PHP_SELF']);
    } else {
        dol_syslog('Unable to upload the user cert file', LOG_ERR);
        dolibarr_set_const($db, 'AUTOVERIFACTU_CERT', null);

        http_response_code(400);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?uploaderror=1');
    }
}

$certpath = getDolGlobalString('AUTOVERIFACTU_CERT');
if (!is_file(DOL_DATA_ROOT . '/' . $certpath)) {
    dolibarr_set_const($db, 'AUTOVERIFACTU_CERT', '');
    $cert_field->fieldValue = '';
    $cert_field->fieldAttr['error'] = true;
}

$action = 'edit';

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = 'AutoverifactuSetup';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-autoverifactu page-admin');

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

echo load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = autoverifactuAdminPrepareHead();

echo dol_get_fiche_head(
    $head,
    'settings',
    $langs->trans($title),
    -1,
    'autoverifactu@autoverifactu',
);

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans('AutoverifactuSetupPage') . '</span><br><br>';

if (!empty($formSetup->items)) {
    echo '<div id="autoverifactuSetupForm">';
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
    'autoverifactu-certupload',
    '.pem,.p12',
    '',
    0,
    0,
    1,
    0
);

// Page end
echo dol_get_fiche_end();

?>
    <script id="harry-potter">
    document.addEventListener("DOMContentLoaded", function () {
        const input = document.getElementById("AUTOVERIFACTU_PASSWORD");
        input.removeAttribute("required");
        input.removeAttribute("aria-required");
    });
    </script>
<?php
llxFooter();
$db->close();

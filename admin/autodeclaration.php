<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
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
 * \file        htdocs/custom/autoverifactu/admin/autodeclaration.php
 * \ingroup     autoverifactu
 * \brief       Reponsability autodeclaration page of module Autoverifactu.
 */

require_once dirname(__DIR__) . '/env.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';

global $langs, $user;

// Translations
$langs->loadLangs(array('errors', 'admin', 'autoverifactu@autoverifactu'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = $_GET['action'] ?? null;
$backtopage = GETPOST('backtopage', 'alpha');
// $autodeclaration = $_POST['autodeclaration'] ?? null;
$autodeclaration = GETPOST('autodeclaration', 'restricthtml');

/*
 * Actions
 */
if ($action === 'create') {
    autoverifactu_set_const('AUTOVERIFACTU_RESPONSABILITY', $autodeclaration);
    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'delete') {
    autoverifactu_set_const('AUTOVERIFACTU_RESPONSABILITY', '');
    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'download') {
    ob_clean();
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="autodeclaracion.html"');
    echo $autodeclaration;
    die();
} elseif ($action) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

$action = null;

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = 'AutoVerifactuAutodeclaration';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-autoverifactu page-admin_autodeclaration');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

echo load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = autoverifactuAdminPrepareHead();
echo dol_get_fiche_head($head, 'autodeclaration', $langs->trans($title), 0, 'autoverifactu@autoverifactu');

$responsability = getDolGlobalString('AUTOVERIFACTU_RESPONSABILITY');
?>
<div>
<?php if ($responsability) { ?>
    <div class="autodeclaration-preview">
        <?php echo $responsability ?>
    </div>
<?php } elseif ($action === 'create') { ?>
    <div class="autodeclaration-preview">
        <?php echo $autodeclaration ?>
    </div>
<?php } else {
    echo autoverifactuDeclarationTemplate();
} ?>
</div>
<div style="margin-top: 1rem">
    <form id="autodeclarationForm" action="/custom/autoverifactu/admin/autodeclaration.php?token=<?php echo newToken() ?>" method="POST">
        <input type="hidden" name="autodeclaration" />
        <div class="form-setup-button-container">
            <?php if ($responsability) : ?>
                <input class="button button-save" type="submit" value="Download" data-action="download">
                <input class="button button-delete butActionDelete" type="submit" value="Delete" data-action="delete">
            <?php else : ?>
                <input class="button button-save" type="submit" value="Save" data-action="create">
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
window.addEventListener("DOMContentLoaded", function () {
    const content = document.querySelector(".autodeclaration-preview").cloneNode(true);

    const watermark = content.querySelector(".autodeclaration-watermark");
    if (watermark) {
        watermark.parentElement.removeChild(watermark);
    }

    const form = document.getElementById("autodeclarationForm");

    const autodeclarationField = form.querySelector("input[name=\"autodeclaration\"]");
    autodeclarationField.value = content.innerHTML;

    for (let button of form.querySelectorAll("input[type=\"submit\"]")) {
        button.setAttribute("formaction", form.getAttribute("action") + "&action=" + button.dataset.action);
    }
});
</script>
<?php

// Page end
echo dol_get_fiche_end();
llxFooter();
$db->close();

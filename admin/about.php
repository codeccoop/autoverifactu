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
 * \file        htdocs/custom/autoverifactu/admin/about.php
 * \ingroup     autoverifactu
 * \brief       About page of module Autoverifactu.
 */

require_once dirname(__DIR__) . '/env.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';

// Translations
$langs->loadLangs(array('errors', 'admin', 'autoverifactu@autoverifactu'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');


/*
 * Actions
 */

// TODO: Declaración responsable

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = 'AutoverifactuSetup';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-autoverifactu page-admin_about');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

echo load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = autoverifactuAdminPrepareHead();
echo dol_get_fiche_head($head, 'about', $langs->trans($title), 0, 'autoverifactu@autoverifactu');

dol_include_once('/autoverifactu/core/modules/modAutoverifactu.class.php');
$tmpmodule = new modAutoverifactu($db);
echo $tmpmodule->getDescLong();

// Page end
echo dol_get_fiche_end();
llxFooter();
$db->close();

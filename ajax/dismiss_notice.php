<?php
/* Copyright (C) 2022       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025       Lucas García            <lucas@codeccoop.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/mymodule/ajax/myobject.php
 *       \brief      File to return Ajax response on myobject list request
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
// if (!defined('NOREQUIRESOC')) {
//     define('NOREQUIRESOC', '1');
// }
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}


require_once dirname(__DIR__) . '/env.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$tag = trim(GETPOST('tag', 'alpha') ?: '');
$entity = GETPOSTINT('entity') ?: 1;

// Security check
if (!$user->admin) {
    accessforbidden();
}

/*
 * View
 */

dol_syslog("Call ajax autoverifactu/ajax/dismiss_notice.php");

top_httphead();

$dismissed = getDolGlobalString('AUTOVERIFACTU_DISMISSED_NOTICES', '');
$dismissed = array_filter(array_map('trim', explode(',', $dismissed)), function ($t) use ($tag) {
    return $t && $t !== $tag;
});

if (!in_array($tag, $dismissed, true)) {
    $dismissed[] = $tag;
    autoverifactu_set_const('AUTOVERIFACTU_DISMISSED_NOTICES', implode(',', $dismissed), $entity);
}

$db->close();

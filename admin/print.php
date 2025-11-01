<?php

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
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

require_once dirname(__DIR__) . '/env.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';

global $langs, $user;

// Translations
$langs->loadLangs(array('errors', 'admin', 'autoverifactu@autoverifactu'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

echo getDolGlobalString('AUTOVERIFACTU_RESPONSABILITY');

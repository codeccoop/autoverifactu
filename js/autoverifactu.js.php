<?php
/* Copyright (C) 2025 Lucas García <lucas@codeccoop.org>
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
 *
 * Library javascript to enable Browser notifications
 */

// if (!defined('NOREQUIREUSER')) {
//     define('NOREQUIREUSER', 1);
// }
// if (!defined('NOREQUIREDB')) {
//     define('NOREQUIREDB', 0);
// }
// if (!defined('NOREQUIRESOC')) {
//     define('NOREQUIRESOC', 1);
// }
// if (!defined('NOREQUIRETRAN')) {
//     define('NOREQUIRETRAN', 0);
// }
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
// if (!defined('NOLOGIN')) {
//     define('NOLOGIN', 1);
// }
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}


/**
 * \file    htdocs/autoverifactu/js/autoverifactu.js.php
 * \ingroup autoverifactu
 * \brief   JavaScript file for module Auto-Veri*Factu.
 */

require_once dirname(__DIR__) . '/env.php';

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';

global $langs, $user, $mysoc;

$langs->loadLangs(array('admin', 'autoverifactu@autoverifactu'));

$enabled = (bool) getDolGlobalString('AUTOVERIFACTU_ENABLED');
$testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
$dismissed = array_filter(array_map('trim', explode(',', getDolGlobalString('AUTOVERIFACTU_DISMISSED_NOTICES', ''))));

$drop = [];

if ($enabled && ($index = array_search('DISABLED', $dismissed, true)) !== false) {
    $drop = array_merge($drop, array_splice($dismissed, $index, 1));
}

if (!$testMode && ($index = array_search('TESTMODE', $dismissed, true)) !== false) {
    $drop = array_merge($drop, array_splice($dismissed, $index, 1));
}

if (count($drop)) {
    autoverifactu_set_const(
        'AUTOVERIFACTU_DISMISSED_NOTICES',
        implode(',', array_filter(array_map('trim', $dismissed))),
        $mysoc->entity,
    );
}

$messages = array();

$is_admin = $user->admin;

if ($is_admin && !$enabled && !in_array('DISABLED', $dismissed, true)) {
    $messages[] = array(
        'warning',
        '<b>' . $langs->trans('AutoVerifactuNotEnabled') . '</b>, '
            . $langs->trans('InvoicesNotSent') . '.',
        true,
        'DISABLED',
    );
}

if ($is_admin && $testMode && !in_array('TESTMODE', $dismissed, true)) {
    $messages[] = array(
        'info',
        $langs->trans('AutoVerifactuInTestMode'),
        true,
        'TESTMODE'
    );
}
?>

/* Javascript library of module Auto-Veri*Factu */
document.addEventListener("DOMContentLoaded", function () {
    const entity = <?php echo $mysoc->entity ?: 1 ?>;
    const messages = <?php echo json_encode($messages); ?>;
    messages.forEach(function (msg) {
        const [type, message, sticky, tag] = msg;
        $.jnotify(message, {
            type,
            sticky,
            beforeRemove: () => {
                fetch("<?php echo DOL_URL_ROOT ?>/custom/autoverifactu/ajax/dismiss_notice.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `tag=${tag}&entity=${+entity}`,
                });
            }
        });
    });
});

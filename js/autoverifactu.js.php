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

$defaultRegime = getDolGlobalString('AUTOVERIFACTU_DEFAULT_REGIME') ?: '01';
$enabled = (bool) getDolGlobalString('AUTOVERIFACTU_ENABLED');
$testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
$dismissed = array_filter(array_map('trim', explode(',', getDolGlobalString('AUTOVERIFACTU_DISMISSED_NOTICES', ''))));

$drop = array();

if ($enabled && ($index = array_search('DISABLED', $dismissed, true)) !== false) {
    $drop = array_merge($drop, array_splice($dismissed, $index, 1));
}

if (!$testMode && ($index = array_search('TESTMODE', $dismissed, true)) !== false) {
    $drop = array_merge($drop, array_splice($dismissed, $index, 1));
}

if (count($drop)) {
    $dismissed = array_filter($dismissed, function ($tag) use ($drop) {
        return !in_array($tag, $drop, true);
    });

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
    // handle ui messages
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

    autoverifactuHandleInvoiceCardReactivity();
    autoverifactuHandleInvoiceDetailsReactivity();
});

function autoverifactuHandleInvoiceCardReactivity() {
    const form = document.querySelector("#formtocreate");
    if (!form) return;

    const rectificationTypeField = form.querySelector(".field_options_verifactu_rectification_type");
    if (!rectificationTypeField) return;

    let invoiceType = "0";
    function setInvoiceType(value) {
        invoiceType = value;

        // 0 = 'Factura estándard'
        // 1 = 'Factura rectificativa'
        // 2 = 'Abono'
        if (value === "1" || value === "2") {
            rectificationTypeField.style.display = "table-row";
        } else {
            rectificationTypeField.style.display = "none";
            rectificationTypeField.querySelector("select").value = "";
        }
    }

    const radioButtons = form.querySelectorAll('input[type="radio"]');
    radioButtons.forEach((input) => {
        if (input.checked) {
            setInvoiceType(input.value);
        }

        input.addEventListener("change", () => {
            if (input.checked) {
                setInvoiceType(input.value);
            }
        });
    });
}

function autoverifactuHandleInvoiceDetailsReactivity() {
    const form = document.querySelector("form#addproduct");
    if (!form) return;

    const regimeField = form.querySelector(".fieldline_options_verifactu_regime_type select");
    const optTypeField = form.querySelector(".fieldline_options_verifactu_operation_type select");
    const excemptionField = form.querySelector(".fieldline_options_verifactu_tax_excemption select");
    if (!(regimeField && optTypeField && excemptionField)) return;

    $(regimeField).val("<?php echo $defaultRegime ?>").trigger("change");
    $(optTypeField).val("S1").trigger("change");

    $(optTypeField).on("change", function () {
        if (this.value) {
            $(excemptionField).val("").trigger("change");
        }
    });

    $(excemptionField).on("change", function () {
        if (this.value) {
            $(optTypeField).val("").trigger("change");
        }
    });
}

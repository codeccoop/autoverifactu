<?php
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}

if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}

if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

header('Content-type: text/css');

if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Error: No se pudo encontrar el archivo main.inc.php de Dolibarr.");
}

$langs->load("autoverifactu@autoverifactu");

?>

td[data-key="facture.verifactu_status"] {
    font-family: sans-serif;
    font-size: 0.85em;
    font-weight: bold !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    margin: 5px;
    min-height: 28px;
    box-sizing: border-box;
    border-radius: 4px;
    color: #ffffff !important;
    height: auto !important;
}

/* --- VARIACIONES DE COLOR SEGÚN EL ESTADO DE VERI*FACTU --- */
/* Pendiente de envío */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv("verifactuStatusSelect0"), ENT_QUOTES, 'UTF-8');?>"] {
    background-color: #2b82a4 !important;
    border-color: #1f617a !important;
    color: #ffffff !important;
}
/* Registrado Correctamente */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv("verifactuStatusSelect1"),ENT_QUOTES, 'UTF-8');?>"] {
    background-color: #28a745 !important;
    border-color: #1e7e34 !important;
    color: #ffffff !important;
}
/* Rechazado */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv("verifactuStatusSelect2"),ENT_QUOTES, 'UTF-8');?>"] {
    background-color: #dc3545 !important;
    border-color: #bd2130 !important;
    color: #ffffff !important;
}
/* En cola (Espera Temporal) */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv("verifactuStatusSelect3"),ENT_QUOTES, 'UTF-8');?>"] {
    background-color: #ffc107 !important;
    border-color: #d39e00 !important;
    color: #212529 !important;
}
/* Registrado Correctamente pero con Errores */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv("verifactuStatusSelect4"),ENT_QUOTES, 'UTF-8');?>"] {
    background-color: #fd7e14 !important;
    /* Naranja corporativo de advertencia */
    border-color: #cf6206 !important;
    color: #ffffff !important;
}
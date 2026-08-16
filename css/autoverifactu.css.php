<?php
/* Copyright (C) 2025       Lucas García        <lucas@codeccoop.org>
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
 * \file    htdocs/custom/verifactu/css/admin.css.php
 * \ingroup verifactu
 * \brief   CSS file for module verifactu setup page.
 */

if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}

if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}

if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

require_once dirname(__DIR__) . '/env.php';

header('Content-type: text/css');

if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}

global $langs;
$langs->load('autoverifactu@autoverifactu');

?>

/* SETUP FORM */
#autoverifactuSetupForm input[error="1"] {
 border: indianred 1px solid;
 color: indianred;
}

.autodeclaration-preview {
	position: relative;
	max-width: 65rem;
	padding: 6rem 4.5rem 4.5rem;
	border: 1px solid;
	box-sizing: border-box;
}

.autodeclaration-preview h1,
.autodeclaration-preview h2 {
	line-height: 1.2;
}

.autodeclaration-preview h1 {
	margin-bottom: 1.5em;
}

.autodeclaration-preview ol,
.autodeclaration-preview ul {
	padding-left: 1rem;
}

.autodeclaration-watermark {
	position: absolute;
	z-index: 10;
	font-weight: 800;
	font-size: 8rem;
	color: red;
	top: 15%;
	left: 50%;
	transform: translate(-50%, -50%) rotate(45deg);
	opacity: 0.3;
	text-transform: uppercase;
}

/* SELECTOR STATUS */
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
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv('verifactuStatusSelect0'), ENT_QUOTES, 'UTF-8');?>"] {
	background-color: #2b82a4 !important;
	border-color: #1f617a !important;
	color: #ffffff !important;
}

/* Registrado Correctamente */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv('verifactuStatusSelect1'), ENT_QUOTES, 'UTF-8');?>"] {
	background-color: #28a745 !important;
	border-color: #1e7e34 !important;
	color: #ffffff !important;
}

/* Rechazado */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv('verifactuStatusSelect2'), ENT_QUOTES, 'UTF-8');?>"] {
	background-color: #dc3545 !important;
	border-color: #bd2130 !important;
	color: #ffffff !important;
}

/* En cola (Espera Temporal) */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv('verifactuStatusSelect3'), ENT_QUOTES, 'UTF-8');?>"] {
	background-color: #ffc107 !important;
	border-color: #d39e00 !important;
	color: #212529 !important;
}

/* Registrado Correctamente pero con Errores */
td[data-key="facture.verifactu_status"][title="<?php echo html_entity_decode($langs->transnoentitiesnoconv('verifactuStatusSelect4'), ENT_QUOTES, 'UTF-8');?>"] {
	background-color: #fd7e14 !important;
	/* Naranja corporativo de advertencia */
	border-color: #cf6206 !important;
	color: #ffffff !important;
}

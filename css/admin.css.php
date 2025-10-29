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
 * \file    htdocs/custom/verifactu/css/mymodule.css.php
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

/**
 * \file    htdocs/autoverifactu/css/autoverifactu.css.php
 * \ingroup autoverifactu
 * \brief   Cascading Style Sheet file for module Auto-Veri*Factu.
 */

require_once dirname(__DIR__) . '/env.php';

header('Content-type: text/css');

if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}

?>

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

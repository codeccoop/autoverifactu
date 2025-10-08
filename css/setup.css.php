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

require_once dirname(__DIR__) . '/env.php';

header('Content-type: text/css');

if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}

?>

#verifactuSetupForm input[error="1"] {
 border: indianred 1px solid;
 color: indianred;
}


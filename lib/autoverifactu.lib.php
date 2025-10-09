<?php

/* Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
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
 * \file    htdocs/custom/autoverifactu/lib/autoverifactu.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with common functions for Autoverifactu
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function autoverifactuAdminPrepareHead()
{
    global $langs, $conf;
    $langs->load('autoverifactu@autoverifactu');

    $h = 0;
    $head = array();

    $head[$h][0] = DOL_URL_ROOT . '/custom/autoverifactu/admin/setup.php';
    $head[$h][1] = $langs->trans('Settings');
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = DOL_URL_ROOT . '/custom/autoverifactu/admin/about.php';
    $head[$h][1] = $langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'autoverifactu@autoverifactu');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'autoverifactu@autoverifactu', 'remove');

    return $head;
}

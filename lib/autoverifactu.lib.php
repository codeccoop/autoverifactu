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

    $head[$h][0] = DOL_URL_ROOT . '/custom/autoverifactu/admin/autodeclaration.php';
    $head[$h][1] = $langs->trans('Autodeclaration');
    $head[$h][2] = 'autodeclaration';
    $h++;


    $head[$h][0] = DOL_URL_ROOT . '/custom/autoverifactu/admin/about.php';
    $head[$h][1] = $langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'autoverifactu@autoverifactu');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'autoverifactu@autoverifactu', 'remove');

    return $head;
}

function autoverifactu_set_const($name, $value, $entity_id = null)
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

    global $db, $mysoc;
    dolibarr_set_const($db, $name, $value, 'chaine', 0, '', is_int($entity_id) ? $entity_id : $mysoc->entity);
}

function autoverifactuDeclarationTemplate()
{
    global $mysoc, $langs;

    ob_start();
    ?>
<div class="autodeclaration-preview autodeclaration-draft">
    <p class="autodeclaration-watermark"><?php echo $langs->trans('Draft') ?></p>
    <h1 style="text-align: center">
        <?php echo $langs->trans('DeclarationTitle'); ?>
    </h1>
    <ol>
        <li>
        <ol style="list-style: lower-alpha">
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSifNameLabel'); ?>:</b>
            </p>
            <p>Auto-Veri*Factu</p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSifCodeLabel'); ?>:</b>
            </p>
            <p>AV</p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSifVersionLabel'); ?>:</b>
            </p>
            <p>1.0.0</p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSoftwareDescriptionLabel'); ?>:</b>
            </p>
            <p>
                <?php echo $langs->trans('DeclarationSoftwareDescription'); ?>:
            </p>
            <p><?php echo $langs->trans('DeclarationFeaturesListLabel') ?>:</p>
            <ul>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList1'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList2'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList3'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList4'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList5'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationFeaturesList6'); ?>
                </li>
            </ul>
            <p>
                <?php echo $langs->trans('DeclarationRequirements1'); ?>
            </p>
            <p>
                <?php echo $langs->trans('DeclarationRequirements2'); ?>
            </p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationOnlyVerifactuLabel'); ?>:</b>
            </p>
            <p><?php echo $langs->trans('DeclarationBoolYes'); ?></p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationMultiCompanyLabel'); ?>:</b>
            </p>
            <p><?php echo $langs->trans('DeclarationBoolNo'); ?></p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSignModeLabel'); ?>:</b>
            </p>
            <p>
                <?php echo $langs->trans('DeclarationSignMode'); ?>
            </p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationCompanyName') ?>:</b>
            </p>
            <p><?php echo $mysoc->nom ?></p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationTaxID'); ?>:</b>
            </p>
            <p><?php echo $mysoc->idprof1 ?></p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationAddress') ?>:</b>
            </p>
            <p>
                <?php echo $mysoc->address ?><br />
                <?php echo $mysoc->zip ?> - <?php echo $mysoc->town ?> (<?php echo
                $mysoc->state ?>)<br />
                <?php echo $mysoc->country ?>
            </p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationCompliance'); ?></b>
            </p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSignDateLabel'); ?>:</b>
            </p>
            <p><?php echo date('d F, Y', time()) ?></p>
            <p>
                <b><?php echo $langs->trans('DeclarationSignLocationLabel'); ?>:</b>
            </p>
            <p>
                <?php echo $mysoc->town ?> (<?php echo $mysoc->state ?>)<br />
                <?php echo $mysoc->country ?>
            </p>
            </li>
        </ol>
        </li>
        <h2 style="text-align: center; margin-left: -1rem"><?php echo $langs->trans('DeclarationAnnex'); ?></h2>
        <li>
        <ol style="list-style: lower-alpha">
            <li>
            <p><b><?php echo $langs->trans('DeclarationLinksLabel'); ?></b></p>
            <ul>
                <li>
                <a href="https://gitlab.com/codeccoop/dolibarr/autoverifactu"
                    >https://gitlab.com/codeccoop/dolibarr/autoverifactu</a
                >
                </li>
                <li>
                <a href="https://github.com/codeccoop/dolibarr-autoverifactu"
                    >https://github.com/codeccoop/dolibarr-autoverifactu</a
                >
                </li>
            </ul>
            <p>
                *
                <em><?php echo $langs->trans('DeclarationGPLNote'); ?></em>
            </p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('DeclarationSpecsLabel'); ?>:</b>
            </p>
            <p>
                <?php echo $langs->trans('DeclarationSpecsListLabel'); ?>:
            </p>
            <ul>
                <li>
                <?php echo $langs->trans('DeclarationSpecs1'); ?>
                </li>
                <li>
                <?php echo $langs->trans('DeclarationSpecs2'); ?>
                </li>
            </ul>
            <p>
                <?php echo $langs->trans('DeclarationDischarge'); ?>
            </p>
            </li>
        </ol>
        </li>
    </ol>
</div>
    <?php
    return ob_get_clean();
}

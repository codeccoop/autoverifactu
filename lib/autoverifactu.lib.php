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
        <?php echo $langs->trans('DECLARACIÓN RESPONSABLE DEL SISTEMA INFORMÁTICO DE FACTURACIÓN'); ?>
    </h1>
    <ol>
        <li>
        <ol style="list-style: lower-alpha">
            <li>
            <p>
                <b><?php echo $langs->trans('Nombre del sistema informático a que se refiere está declaación responsable:'); ?></b>
            </p>
            <p>Auto-Veri*Factu</p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('Código identificador del sistema informático a que se refiere el apartado a) de esta declaración responsable:'); ?></b>
            </p>
            <p>AV</p>
            </li>
            <li>
            <p>
                <b><?php echo $langs->trans('Identificador completo de la versión concreta del sistema informático a que se refiere esta delcaración responsable:'); ?></b>
            </p>
            <p>1.0.0</p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Componentes, hardware y software, de que consta el sistema '
					. 'informático a que se refiere esta declaración responsable, junto '
					. 'con una breve descripción de lo que hace dicho sistema informático '
					. 'y de sus principales funcionalidades:'
				); ?></b>
            </p>
            <p>
				<?php echo $langs->trans(
					'Se trada de un módulo de Dolibarr que, integrado en el sistema de '
					. 'facturación nativo de Dolibarr, implementa las funcionalidades de '
					. 'generación de registros de facturación y su envío a la agencia '
					. 'tributaria, y la inmutabilidad y la tracabilidad de las acciones de '
					. 'facturación.'
				); ?>
            </p>
			<p><?php echo $langs->trans('En concreto') ?>:</p>
            <ul>
                <li>
				<?php echo $langs->trans(
					'Genera automaticamente registros de facturación vinculados a las '
					. 'acciones de validación, corrección y anulación de facturas.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'Remite instantaneamente los registros de facturación a la agencia '
					. 'tributaria y guarda una copia inmutable de los mismos.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'Inserta el código QR en la generación de facturas validadas en '
					. 'formato PDF.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'Bloquea las opciones de edición de facturas una vez estas han sido '
					. 'validadas.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'Define los campos de "Razón social" y "Número de identificación '
					. 'fiscal" del emisor y del destinatario de la factura como '
					. 'obligatorios para la validación de las mismas.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'Guarda un registro inmutable en base de datos de las facturas y '
					. 'las acciones del sistema de facturación que permite detectar '
					. 'modificaciones en facturas y la reconstrucción de los datos '
					. 'originales.'
				); ?>
                </li>
            </ul>
            <p>
				<?php echo $langs->trans(
					'Para su correcto funcionamiento, el programa ha de ser instalado '
					. 'como módulo externo en una instancia de Dolibarr y disponer de '
					. 'acceso a Internet.'
				); ?>
            </p>
            <p>
				<?php echo $langs->trans(
					'Para su debida activación, el programa requiere de la previa '
					. 'generación de la auto declaración responsable desde el panel '
					. 'de configuración del módulo.'
				); ?>
            </p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Indicación de si el sistema informático a que se refiere esta '
					. 'declaración responsable se ha producido de tal manera que, a los '
					. 'efectos de cumplir con el Reglamento, solo pueda funcionar '
					. 'exclusivamente como «VERI*FACTU»:'
				); ?></b>
            </p>
			<p><?php echo $langs->trans('S - Sí'); ?></p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Indicación de si el sistema informático a que se refiere la '
					. 'declaración responsable permite ser usado por varios obligados '
					. 'tributarios o por un mismo usuario para dar soporte a la '
					. 'facturación de varios obligados tributarios:'
				); ?></b>
            </p>
			<p><?php echo $langs->trans('N - No'); ?></p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Tipos de firma utilizados para firmar los registros de '
					. 'facturación y de evento en el caso de que el sistema informático a '
					. 'que se refiere esta declaración responsable no sea utilizado como '
					. '«VERI*FACTU».'
				); ?></b>
            </p>
            <p>
				<?php echo $langs->trans(
					'Dado que se trata de un producto de facturación que solo puede ser '
					. 'utilizado exclusivamente en la modalidad de «VERI*FACTU», no se '
					 . 'realiza una firma electrónica expresa de los registros de '
					. 'facturación generados, ya que la normativa considera que quedan '
					. 'firmados al ser remitidos correctamente a los servicios electrónicos '
					. 'de la Agencia Tributaria con la debida autenticación mediante el '
					. 'adecuado certificado electrónico cualificado.'
				); ?>
            </p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Razón social de la entidad productora del sistema informático a '
					. 'que se refiere esta declaración responsable:'
				) ?></b>
            </p>
            <p><?php echo $mysoc->nom ?></p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Número de identificación fiscal (NIF) español de la entidad '
					. 'productora del sistema informático a que se refiere esta '
					. 'declaración responsable:'
				); ?></b>
            </p>
            <p><?php echo $mysoc->idprof1 ?></p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Dirección postal completa de contacto de la entidad productora '
					. 'del sistema informático a que se refiere esta declaración '
					. 'responsable:'
				) ?></b>
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
                <b><?php echo $langs->trans(
					'La entidad productora del sistema informático a que se refiere '
					. 'esta declaración responsable hace constar que dicho sistema '
					. 'informático, en la versión indicada en ella, cumple con lo '
					. 'dispuesto en el artículo 29.2.j) de la Ley 58/2003, de 17 de '
					. 'diciembre, General Tributaria, en el Reglamento que establece los '
					. 'requisitos que deben adoptar los sistemas y programas informáticos '
					. 'o electrónicos que soporten los procesos de facturación de '
					. 'empresarios y profesionales, y la estandarización de formatos de '
					. 'los registros de facturación, aprobado por el Real Decreto '
					. '1007/2023, de 5 de diciembre, en la Orden HAC/1177/2024, de 17 de '
					. 'octubre, y en la sede electrónica de la Agencia Estatal de '
					. 'Administración Tributaria para todo aquello que complete las '
					. 'especificaciones de dicha orden.'
				); ?></b>
            </p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'Fecha en que la entidad productora de este sistema informático '
					. 'suscribe esta declaración responsable del mismo:'
				); ?></b>
            </p>
            <p><?php echo date('d F, Y', time()) ?></p>
            <p>
				<b><?php echo $langs->trans(
					'Lugar en que la entidad productora de este sistema informático '
					. 'suscribe esta declaración responsable del mismo:'
				); ?></b>
            </p>
            <p>
                <?php echo $mysoc->town ?> (<?php echo $mysoc->state ?>)<br />
                <?php echo $mysoc->country ?>
            </p>
            </li>
        </ol>
        </li>
		<h2 style="text-align: center; margin-left: -1rem"><?php echo $langs->trans('ANEXO'); ?></h2>
        <li>
        <ol style="list-style: lower-alpha">
            <li>
			<p><b><?php echo $langs->trans('Enlaces al codigo fuente del módulo Auto-Veri*Factu'); ?></b></p>
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
				<em><?php echo $langs->trans(
					'Auto-Veri*Factu esta licenciado bajo una licencia GPL y '
					. 'distribuido libremente a través de Internet para su libre '
					. 'consulta, copia y uso.'
				); ?></em>
            </p>
            </li>
            <li>
            <p>
				<b><?php echo $langs->trans(
					'El sistema informático a que se refiere esta declaración '
					. 'responsable cumple las diferentes especificaciones técnicas y '
					. 'funcionales contenidas en la Orden HAC/1177/2024, de 17 de '
					. 'octubre, y en la sede electrónica de la Agencia Estatal de '
					. 'Administración Tributaria para todo aquello que complete las '
					. 'especificaciones de dicha orden, de la siguiente manera:'
				); ?></b>
            </p>
            <p>
				<?php echo $langs->trans(
					'El componente informático denominado Auto-Veri*Factu Dolibarr, en su '
					. 'versión actual cumple con los requisitos establecidos en la Ley '
					. '58/2003, General Tributaria, el Real Decreto 1007/2023 y la Orden '
					. 'HAC/1177/2024, incluyendo: '
				); ?>
            </p>
            <ul>
                <li>
				<?php echo $langs->trans(
					'La integridad, conservación, accesibilidad, legibilidad, '
					. 'trazabilidad e inalterabilidad de los registros de facturación '
					. 'generados por los sistemas informáticos de facturación de Dolibarr '
					. 'con el que se integra.'
				); ?>
                </li>
                <li>
				<?php echo $langs->trans(
					'El cumplimiento de las especificaciones técnicas y funcionales '
					. 'previstas para la remisión de registros de facturación a la '
					. 'Agencia Estatal de Administración Tributaria cuando se utilice '
					. 'como sistema VERI*FACTU.'
				); ?>
                </li>
            </ul>
            <p>
				<?php echo $langs->trans(
					'Auto-Veri*Factu no constituye un sistema de facturación autónomo, '
					. 'sino que actúa como componente integrado en el módlo de facturación '
					. 'de Dolibarr, sistema encargado de ofrecer las funcionalidades '
					. 'habituales de este tipo de aplicación: capturar información de facturación, '
					. 'expedir facturas, consultar facturas, estadísticas de facturación, '
					. 'exportación de datos de facturacion...'
				); ?>
            </p>
            </li>
        </ol>
        </li>
    </ol>
</div>
    <?php
    return ob_get_clean();
}

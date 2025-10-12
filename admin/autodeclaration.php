<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
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
 * \file        htdocs/custom/autoverifactu/admin/autodeclaration.php
 * \ingroup     autoverifactu
 * \brief       Reponsability autodeclaration page of module Autoverifactu.
 */

require_once dirname(__DIR__) . '/env.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once dirname(__DIR__) . '/lib/autoverifactu.lib.php';

global $mysoc, $langs, $user;

// Translations
$langs->loadLangs(array('errors', 'admin', 'autoverifactu@autoverifactu'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = $_GET['action'] ?? null;
$backtopage = GETPOST('backtopage', 'alpha');
// $autodeclaration = $_POST['autodeclaration'] ?? null;
$autodeclaration = GETPOST('autodeclaration', 'restricthtml');

/*
 * Actions
 */
if ($action === 'create') {
    dolibarr_set_const($db, 'AUTOVERIFACTU_RESPONSABILITY', $autodeclaration);
    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'delete') {
    dolibarr_set_const($db, 'AUTOVERIFACTU_RESPONSABILITY', '');
    header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action === 'download') {
    ob_clean();
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="autodeclaracion.html"');
    echo $autodeclaration;
    die();
} elseif ($action) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

$action = null;

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = 'AutoVerifactuAutodeclaration';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-autoverifactu page-admin_autodeclaration');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

echo load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = autoverifactuAdminPrepareHead();
echo dol_get_fiche_head($head, 'autodeclaration', $langs->trans($title), 0, 'autoverifactu@autoverifactu');

$responsability = getDolGlobalString('AUTOVERIFACTU_RESPONSABILITY');
?>
<div>
<?php if ($responsability) : ?>
    <div class="autodeclaration-preview">
        <?php echo $responsability ?>
    </div>
<?php elseif ($action === 'create') : ?>
    <div class="autodeclaration-preview">
        <?php echo $autodeclaration ?>
    </div>
<?php else : ?>
    <div class="autodeclaration-preview autodeclaration-draft">
        <p class="autodeclaration-watermark"><?php echo $langs->trans('Draft') ?></p>
        <h1 style="text-align: center">DECLARACIÓN RESPONSABLE DEL SISTEMA INFORMÁTICO DE FACTURACIÓN</h1>
        <ol>
            <li>
                <ol style="list-style: lower-alpha;">
                    <li>
                        <p><b>Nombre del sistema informático a que se refiere está declaación responsable:</b></p>
                        <p>Auto-Veri*Factu Dolibarr</p>
                    </li>
                    <li>
                        <p><b>Código identificador del sistema informático a que se refiere el apartado a) de esta declaración responsable:</b></p>
                        <p>AV</p>
                    </li>
                    <li>
                        <p><b>Identificador completo de la versión concreta del sistema informático a que se refiere esta delcaración responsable:</b></p>
                        <p>0.0.1</p>
                    </li>
                    <li>
                        <p><b>Componentes, hardware y software, de que consta el sistema informático a que se refiere esta declaración responsable, junto con una breve descripción de lo que hace dicho sistema informático y de sus principales funcionalidades:</b></p>
                        <p>Se trata únicamente de un software que permite facturar y gestionar la facturación realizada, y que se puede instalar en cualquier equipo hardware que tenga las características mínimas requeridas por dicho software y un sistema operativo compatible con él.</p>
                        <p>Este producto está pensado para ser instalado en las máquinas propias del usuario situadas en sus dependencias (on-premise), pudiendo elegir el tipo de instalación que mejor se adapta a sus necesidades:</p>
                        <ul>
                            <li>Puede instalarse en máquinas individuales aisladas (stand-alone) para ser utilizado autónomamente en cada una de ellas.</li>
                            <li>Puede instalarse en diferentes tipos de máquinas interconectadas (cliente-servidor) que actúan de forma especializada solicitando y/o proporcionando servicios.</li>
                        </ul>
                        <p>Este software cuenta con las funcionalidades habituales en este tipo de aplicaciones: capturar información de facturación, expedir facturas, consultar facturas, estadísticas de facturación, exportación de datos de facturación…</p>
                        <p>Este software permite gestionar de forma independiente varias facturaciones dentro de él, cumpliendo separadamente con la normativa mencionada en el apartado 1.k) de esta declaración responsable para cada una de ellas, como si, en la práctica, se tratara de sistemas informáticos de facturación distintos.</p>
                    </li>
                    <li>
                        <p><b>Indicación de si el sistema informático a que se refiere esta declaración responsable se ha producido de tal manera que, a los efectos de cumplir con el Reglamento, solo pueda funcionar exclusivamente como «VERI*FACTU»:</b></p>
                        <p>S - Sí</p>
                    </li>
                    <li>
                        <p><b>Indicación de si el sistema informático a que se refiere la declaración responsable permite ser usado por varios obligados tributarios o por un mismo usuario para dar soporte a la facturación de varios obligados tributarios:</b></p>
                        <p>S - Sí</p>
                    </li>
                    <li>
                        <p><b>Tipos de firma utilizados para firmar los registros de facturación y de evento en el caso de que el sistema informático a que se refiere esta declaración responsable no sea utilizado como «VERI*FACTU».</b></p>
                        <p>Dado que se trata de un producto de facturación que solo puede ser utilizado exclusivamente en la modalidad de «VERI*FACTU», no se realiza una firma electrónica expresa de los registros de facturación generados, ya que la normativa considera que quedan firmados al ser remitidos correctamente a los servicios electrónicos de la Agencia Tributaria con la debida autenticación mediante el adecuado certificado electrónico cualificado.</p>
                    </li>
                    <li>
                        <p><b>Razón social de la entidad productora del sistema informático a que se refiere esta declaración responsable:</b></p>
                        <p><?php echo $mysoc->nom ?></p>
                    </li>
                    <li>
                        <p><b>Número de identificación fiscal (NIF) español de la entidad productora del sistema informático a que se refiere esta declaración responsable:</b></p>
                        <p><?php echo $mysoc->idprof1 ?></p>
                    </li>
                    <li>
                        <p><b>Dirección postal completa de contacto de la entidad productora del sistema informático a que se refiere esta declaración responsable:</b></p>
                        <p>
                            <?php echo $mysoc->address ?><br/>
                            <?php echo $mysoc->zip ?> - <?php echo $mysoc->town ?> (<?php echo $mysoc->state ?>)<br/>
                            <?php echo $mysoc->country ?>
                        </p>
                    </li>
                    <li>
                        <p><b>La entidad productora del sistema informático a que se refiere esta declaración responsable hace constar que dicho sistema informático, en la versión indicada en ella, cumple con lo dispuesto en el artículo 29.2.j) de la Ley 58/2003, de 17 de diciembre, General Tributaria, en el Reglamento que establece los requisitos que deben adoptar los sistemas y programas informáticos o electrónicos que soporten los procesos de facturación de empresarios y profesionales, y la estandarización de formatos de los registros de facturación, aprobado por el Real Decreto 1007/2023, de 5 de diciembre, en la Orden HAC/1177/2024, de 17 de octubre, y en la sede electrónica de la Agencia Estatal de Administración Tributaria para todo aquello que complete las especificaciones de dicha orden.</b></p>
                    </li>
                    <li>
                        <p><b>Fecha en que la entidad productora de este sistema informático suscribe esta declaración responsable del mismo:</b></p>
                        <p><?php echo date('d F, Y', time()) ?></p>
                        <p><b>Lugar en que la entidad productora de este sistema informático suscribe esta declaración responsable del mismo:</b></p>
                        <p>
                            <?php echo $mysoc->town ?> (<?php echo $mysoc->state ?>)<br/>
                            <?php echo $mysoc->country ?>
                        </p>
                    </li>
                </ol>
            </li>
            <h2 style="text-align: center; margin-left: -1rem">ANEXO</h2>
            <li>
                <ol style="list-style: lower-alpha;">
                <li>
                    <p><b>El sistema informático a que se refiere esta declaración responsable cumple las diferentes especificaciones técnicas y funcionales contenidas en la Orden HAC/1177/2024, de 17 de octubre, y en la sede electrónica de la Agencia Estatal de Administración Tributaria para todo aquello que complete las especificaciones de dicha orden, de la siguiente manera:</b></p>
                    <p>Además del modo que es de obligado cumplimiento en ciertos casos (como el algoritmo de huella a emplear), otras implementaciones utilizadas son:</p>
                    <ul>
                        <li>Empleo de la tecnología transaccional del sistema gestor de base de datos utilizado para lograr la consolidación, en una sola unidad transaccional, de la expedición de la factura y la generación del registro de facturación correspondiente a la factura.</li>
                        <li>Existe la posibilidad de elegir una forma especial que permite entrar en la aplicación de facturación de tal manera que solo se da acceso a la información con trascendencia tributaria, ocultando o impidiendo el acceso a la posible información confidencial de carácter no patrimonial, de forma que la Administración tributaria pueda acceder directamente a la consulta y al resto de funcionalidades exigidas sobre la información de los registros de facturación y de eventos. En este caso se ha implementado mediante un control tipo “check” que puede ser seleccionado (por defecto no lo está) antes de entrar en la aplicación.</li>
                    </ul>
                </li>
                </ol>
            </li>
        </ol>
    </div>
<?php endif; ?>
</div>
<div style="margin-top: 1rem">
    <form id="autodeclarationForm" action="/custom/autoverifactu/admin/autodeclaration.php?token=<?php echo newToken() ?>" method="POST">
        <input type="hidden" name="autodeclaration" />
        <div class="form-setup-button-container">
            <?php if ($responsability) : ?>
                <input class="button button-save" type="submit" value="Download" data-action="download">
                <input class="button button-delete butActionDelete" type="submit" value="Delete" data-action="delete">
            <?php else : ?>
                <input class="button button-save" type="submit" value="Save" data-action="create">
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
window.addEventListener("DOMContentLoaded", function () {
    const content = document.querySelector(".autodeclaration-preview").cloneNode(true);

    const watermark = content.querySelector(".autodeclaration-watermark");
    if (watermark) {
        watermark.parentElement.removeChild(watermark);
    }

    const form = document.getElementById("autodeclarationForm");

    const autodeclarationField = form.querySelector("input[name=\"autodeclaration\"]");
    autodeclarationField.value = content.innerHTML;

    for (let button of form.querySelectorAll("input[type=\"submit\"]")) {
        button.setAttribute("formaction", form.getAttribute("action") + "&action=" + button.dataset.action);
    }
});
</script>
<?php

// Page end
echo dol_get_fiche_end();
llxFooter();
$db->close();

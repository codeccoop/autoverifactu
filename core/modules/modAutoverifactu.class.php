<?php

/* Copyright (C) 2004-2018  Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2025 Lucas Garcia						<lucas@codeccoop.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   autoverifactu     Module Autoverifactu
 *  \brief      Module with triggers to bridge Dolibarr bills to the verifactu system
 *
 *  \file       htdocs/custom/autoverifactu/core/modules/modAutoverifactu.class.php
 *  \ingroup    autoverifactu
 *  \brief      Autoverifactu module definition
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Autoverifactu
 */
class modAutoverifactu extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 409904;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'autoverifactu';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        // It is used to group modules by family in module setup page
        $this->family = 'financial';

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        //$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
        // Module label (no space allowed), used if translation string 'ModuleAutoverifactuName' not found (Autoverifactu is name of module).
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // DESCRIPTION_FLAG
        // Module description, used if translation string 'ModuleAutoverifactuDesc' not found (Autoverifactu is name of module).
        $this->description = 'Bridge Dolibarr bills to the vVeri*Factu system';
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = 'With this module activated, each validated bill will be immediatly sent to the verifactu system and freezed';

        // Author
        $this->editor_name = 'Còdec';
        $this->editor_url = 'https://www.codeccoop.org';      // Must be an external online web site
        $this->editor_squarred_logo = 'logo-codec.png@autoverifactu';                   // Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@autoverifactu'

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
        $this->version = '0.0.5';
        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where AUTOVERIFACTU is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        // To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
        $this->picto = 'fa-receipt';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = array(
            // Set this to 1 if module has its own trigger directory (core/triggers)
            'triggers' => 1,
            // Set this to 1 if module has its own login method file (core/login)
            'login' => 0,
            // Set this to 1 if module has its own substitution function file (core/substitutions)
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus)
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl)
            'tpl' => 0,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode)
            'barcode' => 0,
            // Set this to 1 if module has its own models directory (core/modules/xxx)
            'models' => 0,
            // Set this to 1 if module has its own printing directory (core/modules/printing)
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme)
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file
            'css' => array(
                '/autoverifactu/css/admin.css.php',
            ),
            // Set this to relative path of js file if module must load a js on all pages
            'js' => array(
                '/autoverifactu/js/autoverifactu.js.php',
            ),
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
            /* BEGIN MODULEBUILDER HOOKSCONTEXTS */
            'hooks' => array(
                'admincompany',
                'invoicecard',
                'pdfgeneration',
            ),
            /* END MODULEBUILDER HOOKSCONTEXTS */
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
            // Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
            'websitetemplates' => 0,
            // Set this to 1 if the module provides a captcha driver
            'captcha' => 0
        );

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/autoverifactu/temp","/autoverifactu/subdir");
        $this->dirs = array('/autoverifactu/temp');

        // Config pages. Put here list of php page, stored into autoverifactu/admin directory, to use to setup module.
        $this->config_page_url = array('setup.php@autoverifactu');

        // Dependencies
        // A condition to hide module
        $this->hidden = getDolGlobalInt('MODULE_AUTOVERIFACTU_DISABLED'); // A condition to disable module;
        // List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
        $this->depends = array('modFacture', 'modBlockedLog');
        // List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->requiredby = array();
        // List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = array();

        // The language file dedicated to your module
        $this->langfiles = array('autoverifactu@autoverifactu');

        // Prerequisites
        $this->phpmin = array(8, 0); // Minimum version of PHP required by module
        // $this->phpmax = array(8, 0); // Maximum version of PHP required by module
        $this->need_dolibarr_version = array(20, -3); // Minimum version of Dolibarr required by module
        // $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
        $this->need_javascript_ajax = 1;

        // Messages at activation
        $this->warnings_activation = array();       // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_activation_ext = array();   // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_unactivation = array();     // array('ES' => 'BlockedLogAreRequiredByYourCountryLegislation');

        // $this->automatic_activation = array('ES'=>'AutoverifactuWasAutomaticallyActivatedBecauseOfYourCountryChoice');

        // $this->always_enabled = (isModEnabled('autoverifactu')
        //     && getDolGlobalString('AUTOVERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY')
        //     && in_array((empty($mysoc->country_code) ? '' : $mysoc->country_code), explode(',', getDolGlobalString('AUTOVERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY')))
        //     && $this->alreadyUsed());

        /* Constants */
        // List of particular constants to add when module is enabled (key, 'chaine',
        // value, desc, visible, 'current' or 'allentities', deleteonunactive).
        // $this->const = array(
        //     1 => array(
        //         'AUTOVERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY',
        //         'chaine',
        //         'ES',
        //         'This is list of country code where the module may be mandatory',
        //         0,
        //         'current',
        //         0,
        //     )
        // );

        // Some keys to add into the overwriting translation tables
        /*$this->overwrite_translation = array(
            'en_US:ParentCompany'=>'Parent company or reseller',
            'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
        )*/

        if (!isModEnabled('autoverifactu')) {
            $conf->autoverifactu = new stdClass();
            $conf->autoverifactu->enabled = 0;
        }

        // Array to add new pages in new tabs
        /* BEGIN MODULEBUILDER TABS */
        // Don't forget to deactivate/reactivate your module to test your changes
        $this->tabs = array();

        $this->dictionaries = array();

        // Boxes/Widgets
        // Add here list of php file(s) stored in autoverifactu/core/boxes that contains a class to show a widget.
        $this->boxes = array();

        // Cronjobs (List of cron jobs entries to add when module is enabled)
        // unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
        $this->cronjobs = array();

        // Permissions provided by this module
        $this->rights = array();

        // Main menu entries to add
        $this->menu = array();
        // $r = 0;
        // Add here entries to declare new menus
        // $this->menu[$r++] = array(
        //     'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
        //     'type' => 'top', // This is a Top menu entry
        //     'titre' => 'Autoverifactu',
        //     'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
        //     'mainmenu' => 'autoverifactu',
        //     'leftmenu' => '',
        //     'url' => '/autoverifactu/autoverifactuindex.php',
        //     'langs' => 'autoverifactu@autoverifactu', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
        //     'position' => 1000 + $r,
        //     'enabled' => 'isModEnabled("autoverifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("autoverifactu")' if entry must be visible if module is enabled.
        //     'perms' => '1', // Use 'perms'=>'$user->hasRight("autoverifactu", "myobject", "read")' if you want your menu with a permission rules
        //     'target' => '',
        //     'user' => 0, // 0=Menu for internal users, 1=external users, 2=both
        // );
    }

    /**
     *  Function called when module is enabled.
     *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *  It also creates data directories.
     *
     *  @param      string  $options    Options when enabling module ('', 'noboxes').
     *
     *  @return     int<-1,1>           1 if OK, <=0 if KO.
     */
    public function init($options = '')
    {
        global $db, $conf, $langs;

        dolibarr_set_const($db, 'FAC_FORCE_DATE_VALIDATION', '1', 'chaine', 0, '', 0);

        // Create tables of module at module activation
        // $result = $this->_load_tables('/autoverifactu/sql/');
        // if ($result < 0) {
        //     // Do not activate module if error 'not allowed' returned when loading module SQL queries
        //     // (the _load_table run sql with run_sql with the error allowed parameter set to 'default').
        //     return -1;
        // }

        // Create extrafields during init
        include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        $extrafields->addExtraField(
            'verifactu_rectification_type',
            'VerifactuRectificationType',
            'select',
            1,
            2,
            'facture',
            0,
            0,
            '',
            array(
                'options' => array(
                    'R1' => 'Factura Rectificativa (Error fundado en derecho y Art. 80 Uno Dos y Seis LIVA)',
                    'R2' => 'Factura Rectificativa (Art. 80.3)',
                    'R3' => 'Factura Rectificativa (Art. 80.4)',
                    'R4' => 'Factura Rectificativa (Resto)',
                    // 'R5' => 'Factura Rectificativa en facturas simplificadas',
                ),
            ),
            0,
            '',
            '3',
            'En caso de ser esta una factura recificativa, indica de que tipo es',
            '',
            '',
            'autoverifactu@autoverifactu',
            'isModEnabled("autoverifactu")',
        );

        $extrafields->addExtraField(
            'verifactu_hash',
            'VerifactuHash',
            'varchar',
            1,
            255,
            'facture',
            0,
            0,
            '',
            '',
            0,
            '',
            0,
            '',
            '',
            '',
            'autoverifactu@autoverifactu',
            'isModEnabled("autoverifactu")',
        );

        $extrafields->addExtraField(
            'verifactu_error',
            'VerifactuError',
            'text',
            1,
            510,
            'facture',
            0,
            0,
            '',
            '',
            0,
            '',
            0,
            '',
            '',
            '',
            'autoverifactu@autoverifactu',
            'isModEnabled("autoverifactu")',
        );

        $extrafields->addExtraField(
            'verifactu_tms',
            'VerifactuTimeStamp',
            'int',
            1,
            15,
            'facture',
            0,
            0,
            '0',
            '',
            0,
            '',
            0,
            '',
            '',
            '',
            'autoverifactu@autoverifactu',
            'isModEnabled("autoverifactu")',
        );

        // Permissions
        $this->remove($options);

        $sql = array();
        return $this->_init($sql, $options);
    }

    /**
     *  Function called when module is disabled.
     *  Remove from database constants, boxes and permissions from Dolibarr database.
     *  Data directories are not deleted
     *
     *  @param  string      $options    Options when enabling module ('', 'noboxes')
     *
     *  @return int<-1,1>               1 if OK, <=0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}

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
 *  \defgroup   verifactu     Module Verifactu
 *  \brief      Module with triggers to bridge Dolibarr bills to the verifactu system
 *
 *  \file       htdocs/custom/verifactu/core/modules/modVerifactu.class.php
 *  \ingroup    verifactu
 *  \brief      Verifactu module definition
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Verifactu
 */
class modVerifactu extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 77088; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'verifactu';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        // It is used to group modules by family in module setup page
        $this->family = 'financial';

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        //$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
        // Module label (no space allowed), used if translation string 'ModuleVerifactuName' not found (Verifactu is name of module).
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // DESCRIPTION_FLAG
        // Module description, used if translation string 'ModuleVerifactuDesc' not found (Verifactu is name of module).
        $this->description = 'Bridge Dolibarr bills to the vVeri*Factu system';
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = 'With this module activated, each validated bill will be immediatly sent to the verifactu system and freezed';

        // Author
        $this->editor_name = 'Còdec';
        $this->editor_url = 'https://www.codeccoop.org';      // Must be an external online web site
        $this->editor_squarred_logo = 'logo-codec.png@verifactu';                   // Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@verifactu'

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
        $this->version = '1.0';
        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where VERIFACTU is value of property name of module in uppercase)
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
                '/verifactu/css/setup.css.php',
            ),
            // Set this to relative path of js file if module must load a js on all pages
            'js' => array(
                //   '/verifactu/js/verifactu.js.php',
            ),
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
            /* BEGIN MODULEBUILDER HOOKSCONTEXTS */
            'hooks' => array(
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
        // Example: this->dirs = array("/verifactu/temp","/verifactu/subdir");
        $this->dirs = array('/verifactu/temp');

        // Config pages. Put here list of php page, stored into verifactu/admin directory, to use to setup module.
        $this->config_page_url = array(
            'setup.php@verifactu',
            'about.php@verifactu'
        );

        // Dependencies
        // A condition to hide module
        $this->hidden = getDolGlobalInt('MODULE_VERIFACTU_DISABLED'); // A condition to disable module;
        // List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
        $this->depends = array('modFacture', 'modBlockedLog');
        // List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->requiredby = array();
        // List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = array();

        // The language file dedicated to your module
        $this->langfiles = array('verifactu@verifactu');

        // Prerequisites
        $this->phpmin = array(8, 0); // Minimum version of PHP required by module
        // $this->phpmax = array(8, 0); // Maximum version of PHP required by module
        $this->need_dolibarr_version = array(20, -3); // Minimum version of Dolibarr required by module
        // $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
        $this->need_javascript_ajax = 0;

        // Messages at activation
        $this->warnings_activation = array();       // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_activation_ext = array();   // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_unactivation = array('ES' => 'BlockedLogAreRequiredByYourCountryLegislation');

        // $this->automatic_activation = array('ES'=>'VerifactuWasAutomaticallyActivatedBecauseOfYourCountryChoice');

        $this->always_enabled = (isModEnabled('verifactu')
            && getDolGlobalString('VERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY')
            && in_array((empty($mysoc->country_code) ? '' : $mysoc->country_code), explode(',', getDolGlobalString('VERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY')))
            && $this->alreadyUsed());

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        $this->const = array(
            1 => array('VERIFACTU_DISABLE_NOT_ALLOWED_FOR_COUNTRY', 'chaine', 'ES', 'This is list of country code where the module may be mandatory', 0, 'current', 0)
        );

        // Some keys to add into the overwriting translation tables
        /*$this->overwrite_translation = array(
            'en_US:ParentCompany'=>'Parent company or reseller',
            'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
        )*/

        if (!isModEnabled('verifactu')) {
            $conf->verifactu = new stdClass();
            $conf->verifactu->enabled = 0;
        }

        // Array to add new pages in new tabs
        /* BEGIN MODULEBUILDER TABS */
        // Don't forget to deactivate/reactivate your module to test your changes
        $this->tabs = array();

        $this->dictionaries = array();

        // Boxes/Widgets
        // Add here list of php file(s) stored in verifactu/core/boxes that contains a class to show a widget.
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
        //     'titre' => 'Verifactu',
        //     'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
        //     'mainmenu' => 'verifactu',
        //     'leftmenu' => '',
        //     'url' => '/verifactu/verifactuindex.php',
        //     'langs' => 'verifactu@verifactu', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
        //     'position' => 1000 + $r,
        //     'enabled' => 'isModEnabled("verifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("verifactu")' if entry must be visible if module is enabled.
        //     'perms' => '1', // Use 'perms'=>'$user->hasRight("verifactu", "myobject", "read")' if you want your menu with a permission rules
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

        dolibarr_set_const($db, 'FAC_FORCE_DATE_VALIDATION', '1');

        // Create tables of module at module activation
        //$result = $this->_load_tables('/install/mysql/', 'verifactu');
        // $result = $this->_load_tables('/verifactu/sql/');
        // if ($result < 0) {
        //     return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
        // }

        // Create extrafields during init
        // include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        // $extrafields = new ExtraFields($this->db);
        //
        // $extrafields->addExtraField(
        //     'verifactu_xml',
        //     'Verifactu',
        //     'text',
        //     1,
        //     0,
        //     'facture',
        //     0,
        //     0,
        //     '',
        //     array('options' => array(1 => 1)),
        //     0,
        //     '',
        //     0,
        //     0,
        //     '',
        //     '',
        //     'verifactu@verifactu',
        //     'isModEnabled("verifactu")'
        // );

        // Permissions
        $this->remove($options);

        $sql = array();

        // $where = 'rd.module = \'facture\' AND rd.perms = \'invoice_advance\'';
        // $where .= ' AND rd.subperms IN (\'unvalidate\', \'reopen\')';

        // $i = 0;
        // $sql[$i] = 'UPDATE ' . $this->db->prefix() . 'rights_def rd';
        // $sql[$i] .= ' SET enabled = 0';
        // $sql[$i] .= ' WHERE ' . $where;
        // ++$i;
        //
        // $sql[$i] = 'DELETE FROM ' . $this->db->prefix() . 'user_rights';
        // $sql[$i] .= ' WHERE fk_id IN (SELECT id FROM ' . $this->db->prefix() . 'rights_def rd WHERE ' . $where . ')';
        // ++$i;

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

        // $where = 'rd.module = \'facture\' AND rd.perms = \'invoice_advance\'';
        // $where .= ' AND rd.subperms IN (\'unvalidate\', \'reopen\')';

        // $i = 0;
        // $sql[$i] = 'UPDATE ' . $this->db->prefix() . 'rights_def rd';
        // $sql[$i] .= ' SET enabled = 1';
        // $sql[$i] .= ' WHERE ' . $where;
        // ++$i;
        //
        // $sql[$i] = 'INSERT INTO ' . $this->db->prefix() . 'user_rights';
        // $sql[$i] .= ' (entity, fk_user, fk_id)';
        // $sql[$i] .= ' SELECT DISTINCT u.entity, u.rowid fk_user, rd.id fk_id';
        // $sql[$i] .= ' FROM ' . $this->db->prefix() . 'user u';
        // $sql[$i] .= ' LEFT JOIN ' . $this->db->prefix() . 'rights_def rd';
        // $sql[$i] .= ' ON true';
        // $sql[$i] .= ' WHERE ' . $where;
        // ++$i;

        return $this->_remove($sql, $options);
    }
}

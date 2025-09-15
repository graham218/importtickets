<?php
/**
 * Plugin setup for Ticket Import
 * @version 1.0.0
 */

define('PLUGIN_IMPORTTICKETS_VERSION', '1.0.0');
define('PLUGIN_IMPORTTICKETS_MIN_GLPI', '10.0.0');
define('PLUGIN_IMPORTTICKETS_MAX_GLPI', '11.99.99');

function plugin_init_importtickets() {
    global $PLUGIN_HOOKS;
    
    $PLUGIN_HOOKS['csrf_compliant']['importtickets'] = true;
    
    // Add menu entry
    $PLUGIN_HOOKS['menu_toadd']['importtickets'] = [
        'tools' => 'PluginImporttickets'
    ];
    
    // Add configuration page
    if (Session::haveRight('plugin_importtickets', READ)) {
        $PLUGIN_HOOKS['config_page']['importtickets'] = 'front/import.form.php';
    }
    
    // Add profile rights
    Plugin::registerClass('PluginImportticketsProfile', [
        'addtabon' => ['Profile']
    ]);
    
    // Register the main plugin class
    Plugin::registerClass('PluginImporttickets', []);
    
    // Add JavaScript and CSS
    $PLUGIN_HOOKS['add_javascript']['importtickets'] = ['js/importtickets.js'];
    $PLUGIN_HOOKS['add_css']['importtickets'] = ['css/importtickets.css'];
    
    // Add locale support
    $PLUGIN_HOOKS['init_session']['importtickets'] = 'plugin_importtickets_init_session';
    $PLUGIN_HOOKS['change_profile']['importtickets'] = 'plugin_importtickets_change_profile';
}

// Add these functions for locale support
function plugin_importtickets_init_session() {
    if (isset($_SESSION['glpilanguage'])) {
        $lang = $_SESSION['glpilanguage'];
        bindtextdomain('importtickets', Plugin::getPhpDir('importtickets') . '/locales');
        textdomain('importtickets');
    }
}

function plugin_importtickets_change_profile() {
    plugin_importtickets_init_session();
}

function plugin_version_importtickets() {
    return [
        'name'           => 'Ticket Import',
        'version'        => PLUGIN_IMPORTTICKETS_VERSION,
        'author'         => 'GLPI Plugin Developer',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_IMPORTTICKETS_MIN_GLPI,
                'max' => PLUGIN_IMPORTTICKETS_MAX_GLPI,
            ]
        ]
    ];
}

function plugin_importtickets_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_IMPORTTICKETS_MIN_GLPI, '<')) {
        echo "This plugin requires GLPI >= " . PLUGIN_IMPORTTICKETS_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_importtickets_check_config($verbose = false) {
    if ($verbose) {
        echo "Ticket Import plugin is correctly installed";
    }
    return true;
}

// Install function to call PluginImporttickets::install()
function plugin_importtickets_install() {
    return PluginImporttickets::install();
}

// Uninstall function to call PluginImporttickets::uninstall()
function plugin_importtickets_uninstall() {
    return PluginImporttickets::uninstall();
}
<?php
/**
 * Configuration class for Ticket Import plugin
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginImportticketsConfig extends CommonDBTM {
    
    static function getTypeName($nb = 0) {
        return __('Ticket Import Configuration', 'importtickets');
    }
    
    static function getConfig() {
        $config = new self();
        $config->getFromDB(1);
        return $config;
    }
    
    static function install() {
        global $DB;
        
        $default_config = [
            'id' => 1,
            'default_entity' => 0,
            'default_category' => 0,
            'default_urgency' => 3,
            'default_impact' => 2,
            'default_priority' => 3
        ];
        
        $DB->insertOrDie('glpi_plugin_importtickets_configs', $default_config);
    }
    
    static function uninstall() {
        global $DB;
        
        $DB->query("DROP TABLE IF EXISTS `glpi_plugin_importtickets_configs`");
    }
}
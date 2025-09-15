<?php
/**
 * Plugin hooks for Ticket Import
 */

function plugin_importtickets_menu_entry() {
    if (class_exists('PluginImporttickets') && Session::haveRight('plugin_importtickets', READ)) {
        return PluginImporttickets::getMenuContent();
    }
    return [];
}
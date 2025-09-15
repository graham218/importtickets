<?php
/**
 * Plugin hooks for Ticket Import
 */

function plugin_importtickets_Menu() {
    if (class_exists('PluginImporttickets')) {
        return PluginImporttickets::getMenuContent();
    }
    return [];
}
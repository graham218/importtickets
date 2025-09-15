<?php
/**
 * Profile management for Ticket Import plugin
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginImportticketsProfile extends Profile {
    
    static $rightname = "plugin_importtickets";
    
    /**
     * Get tab name for item
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            return __('Ticket Import', 'importtickets');
        }
        return '';
    }
    
    /**
     * Display tab content
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            $profile = new self();
            $profile->showForm($item->getID());
        }
        return true;
    }
    
    /**
     * Show profile form
     */
    function showForm($profiles_id = 0, $openform = true, $closeform = true) {
        global $CFG_GLPI;
        
        // Log form access
        error_log("PluginImportticketsProfile::showForm called for profile ID: $profiles_id");
        
        // Check permissions
        if (!Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            echo "<div class='center'>" . __('No permission to modify rights', 'importtickets') . "</div>";
            return false;
        }
        
        // Validate profile ID
        $profile = new Profile();
        if (!$profile->getFromDB($profiles_id)) {
            echo "<div class='center'>" . __('Invalid profile ID', 'importtickets') . "</div>";
            return false;
        }
        
        if ($openform) {
            echo "<form method='post' action='" . $this->getFormURL() . "'>";
        }
        
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . __('Ticket Import Rights', 'importtickets') . "</th></tr>";
        
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Use ticket import feature', 'importtickets') . "</td>";
        echo "<td>";
        $profileRight = new ProfileRight();
        $current_right = $profileRight->getFromDBByCrit([
            'profiles_id' => $profiles_id,
            'name' => 'plugin_importtickets'
        ]) ? $profileRight->fields['rights'] : 0;
        
        Dropdown::showYesNo('rights[plugin_importtickets]', $current_right, -1, [
            'width' => '100px'
        ]);
        echo "</td></tr>";
        
        if ($closeform) {
            echo "<tr class='tab_bg_2'>";
            echo "<td colspan='2' class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
        }
        
        // Handle form submission
        if (isset($_POST['update']) && isset($_POST['rights']['plugin_importtickets']) && Session::checkCSRF()) {
            error_log("PluginImportticketsProfile: Processing form submission for profile ID: $profiles_id, rights: " . print_r($_POST['rights'], true));
            
            $rights = [
                $profiles_id => [
                    'plugin_importtickets' => $_POST['rights']['plugin_importtickets'] ? READ : 0
                ]
            ];
            ProfileRight::updateProfileRights($profiles_id, $rights);
            Session::addMessageAfterRedirect(__('Rights updated successfully', 'importtickets'), true, INFO);
            error_log("PluginImportticketsProfile: Rights updated for profile ID: $profiles_id, redirecting to profile.form.php");
            Html::redirect($CFG_GLPI['root_doc'] . "/front/profile.form.php?id=$profiles_id");
        }
    }
    
    /**
     * Get all rights
     */
    static function getAllRights() {
        return [
            [
                'itemtype' => 'PluginImporttickets',
                'label'    => __('Use ticket import feature', 'importtickets'),
                'field'    => 'plugin_importtickets'
            ]
        ];
    }
    
    /**
     * Install profiles
     */
    static function install() {
        global $DB;
        
        $profileRight = new ProfileRight();
        foreach (self::getAllRights() as $right) {
            if (!countElementsInTable('glpi_profilerights', ['name' => $right['field']])) {
                $profileRight->add(['name' => $right['field'], 'rights' => READ]);
            }
        }
        
        // Add rights to all existing profiles
        $profiles = $DB->request(['FROM' => 'glpi_profiles']);
        foreach ($profiles as $profile) {
            self::addDefaultProfileInfos($profile['id'], ['plugin_importtickets' => READ]);
        }
    }
    
    /**
     * Uninstall profiles
     */
    static function uninstall() {
        $profileRight = new ProfileRight();
        foreach (self::getAllRights() as $right) {
            $profileRight->deleteByCriteria(['name' => $right['field']]);
        }
    }
    
    /**
     * Add default rights to a profile
     */
    static function addDefaultProfileInfos($profiles_id, $rights) {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (!countElementsInTable('glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $right])) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value
                ]);
            }
        }
    }
}
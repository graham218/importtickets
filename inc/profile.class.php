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
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        if ($canedit && $openform) {
            echo "<form method='post' action='" . $this->getFormURL() . "'>";
        }
        
        $profile = new Profile();
        $profile->getFromDB($profiles_id);
        
        $rights = $this->getAllRights();
        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('General rights')
        ]);
        
        if ($canedit && $closeform) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
    }
    
    /**
     * Get all rights
     */
    static function getAllRights() {
        $rights = [
            [
                'itemtype' => 'PluginImporttickets',
                'label'    => __('Use ticket import feature', 'importtickets'),
                'field'    => 'plugin_importtickets'
            ]
        ];
        
        return $rights;
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
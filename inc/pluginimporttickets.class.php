<?php
/**
 * Main plugin class for Ticket Import
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginImporttickets extends CommonDBTM {
    
    static $rightname = 'plugin_importtickets';
    
    static function getTable($classname = null) {
        return 'glpi_plugin_importtickets_configs';
    }
    
    /**
     * Install plugin
     */
    static function install($migration = null) {
        global $DB;
        
        // Create config table
        $default_config = [
            'id' => 1,
            'default_entity' => 0,
            'default_category' => 0,
            'default_urgency' => 3,
            'default_impact' => 2,
            'default_priority' => 3
        ];
        
        // Create config table if it doesn't exist
        if (!$DB->tableExists('glpi_plugin_importtickets_configs')) {
            $query = "CREATE TABLE `glpi_plugin_importtickets_configs` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `default_entity` INT NOT NULL DEFAULT 0,
                `default_category` INT NOT NULL DEFAULT 0,
                `default_urgency` INT NOT NULL DEFAULT 3,
                `default_impact` INT NOT NULL DEFAULT 2,
                `default_priority` INT NOT NULL DEFAULT 3,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $DB->queryOrDie($query, $DB->error());
            
            // Insert default config
            $DB->insertOrDie('glpi_plugin_importtickets_configs', $default_config);
        }
        
        // Install profiles
        PluginImportticketsProfile::install();
        
        return true;
    }
    
    /**
     * Uninstall plugin
     */
    static function uninstall() {
        global $DB;
        
        // Remove config table
        if ($DB->tableExists('glpi_plugin_importtickets_configs')) {
            $DB->queryOrDie("DROP TABLE IF EXISTS `glpi_plugin_importtickets_configs`");
        }
        
        // Uninstall profiles
        PluginImportticketsProfile::uninstall();
        
        return true;
    }
    
    /**
     * Get menu content
     */
    static function getMenuContent() {
        $menu = [];
        
        if (Session::haveRight(self::$rightname, READ)) {
            $menu['title']   = __('Ticket Import', 'importtickets');
            $menu['page']    = '/plugins/importtickets/front/import.form.php';
            $menu['icon']    = 'fas fa-file-import';
        }
        
        return $menu;
    }
    
    /**
     * Get available import formats
     */
    static function getImportFormats() {
        return [
            'csv'  => __('CSV File', 'importtickets')
        ];
    }
    
    /**
     * Get all available ticket fields for mapping
     */
    static function getAvailableFields() {
        return [
            'name' => __('Title', 'importtickets'),
            'content' => __('Description', 'importtickets'),
            'urgency' => __('Urgency', 'importtickets'),
            'impact' => __('Impact', 'importtickets'),
            'priority' => __('Priority', 'importtickets'),
            'type' => __('Type', 'importtickets'),
            'status' => __('Status', 'importtickets'),
            'itilcategories_id' => __('Category', 'importtickets'),
            'entities_id' => __('Entity', 'importtickets'),
            'locations_id' => __('Location', 'importtickets'),
            '_users_id_requester' => __('Requester', 'importtickets'),
            '_users_id_assign' => __('Technician', 'importtickets'),
            '_groups_id_assign' => __('Group', 'importtickets'),
            'date' => __('Creation date', 'importtickets'),
            'time_to_resolve' => __('Time to resolve', 'importtickets'),
            'actiontime' => __('Duration', 'importtickets'),
            'global_validation' => __('Validation', 'importtickets')
        ];
    }
    
    /**
     * Process CSV import
     */
    static function processCSVImport($file_path, $options = []) {
        $results = [
            'success' => 0,
            'errors'  => 0,
            'skipped' => 0,
            'messages' => [],
            'imported_ids' => []
        ];
        
        if (($handle = fopen($file_path, 'r')) === FALSE) {
            $results['errors']++;
            $results['messages'][] = __('Failed to open file', 'importtickets');
            return $results;
        }
        
        // Define header mapping
        $header_map = [
            'title' => 'name',
            'description' => 'content',
            'requester' => '_users_id_requester',
            'technician' => '_users_id_assign',
            'category' => 'itilcategories_id',
            'priority' => 'priority',
            'type' => 'type',
            'status' => 'status',
            'urgency' => 'urgency',
            'impact' => 'impact',
            'entity' => 'entities_id',
            'location' => 'locations_id'
        ];
        
        // Read headers
        $headers = fgetcsv($handle, 0, ',');
        if ($headers === FALSE) {
            $results['errors']++;
            $results['messages'][] = __('Failed to read headers', 'importtickets');
            fclose($handle);
            return $results;
        }
        
        $row = 0;
        while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
            $row++;
            
            // Skip empty rows
            if (empty(array_filter($data, 'strlen'))) {
                $results['skipped']++;
                $results['messages'][] = sprintf(__('Line %d: Empty row, skipped', 'importtickets'), $row);
                continue;
            }
            
            // Map data to ticket fields
            $input = [];
            if ($options['has_headers'] && !empty($headers)) {
                foreach ($headers as $col => $header) {
                    $header = trim(strtolower($header));
                    if (isset($header_map[$header]) && isset($data[$col])) {
                        $field = $header_map[$header];
                        $input[$field] = trim($data[$col]);
                    }
                }
            } else {
                // Fixed position mapping
                $ordered_fields = array_values($header_map);
                foreach ($data as $col => $value) {
                    if (isset($ordered_fields[$col])) {
                        $input[$ordered_fields[$col]] = trim($value);
                    }
                }
            }
            
            // Apply defaults and conversions
            $input = self::prepareTicketInput($input);
            
            // Validate required fields
            if (empty($input['name'])) {
                $results['skipped']++;
                $results['messages'][] = sprintf(__('Line %d: No title provided, skipped', 'importtickets'), $row);
                continue;
            }
            
            // Create ticket
            $ticket = new Ticket();
            if (Session::haveRight('ticket', CREATE)) {
                $ticket_id = $ticket->add($input);
                
                if ($ticket_id) {
                    $results['success']++;
                    $results['imported_ids'][] = $ticket_id;
                    
                    if ($options['add_followup']) {
                        $followup = new ITILFollowup();
                        $f_input = [
                            'itemtype' => Ticket::class,
                            'items_id' => $ticket_id,
                            'content' => __('Ticket imported from CSV', 'importtickets'),
                            'users_id' => Session::getLoginUserID(),
                            'requesttypes_id' => 1 // Information
                        ];
                        $followup->add($f_input);
                    }
                } else {
                    $results['errors']++;
                    $results['messages'][] = sprintf(__('Line %d: Failed to create ticket', 'importtickets'), $row);
                }
            } else {
                $results['errors']++;
                $results['messages'][] = sprintf(__('Line %d: No permission to create tickets', 'importtickets'), $row);
            }
        }
        
        fclose($handle);
        return $results;
    }
    
    /**
     * Prepare ticket input with conversions and defaults
     */
    static function prepareTicketInput($input) {
        $config = self::getConfig();
        
        // Set defaults
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? $config['default_entity'] ?? 0);
        $input['itilcategories_id'] = $input['itilcategories_id'] ?? ($config['default_category'] ?? 0);
        $input['urgency'] = $input['urgency'] ?? ($config['default_urgency'] ?? 3);
        $input['impact'] = $input['impact'] ?? ($config['default_impact'] ?? 2);
        $input['priority'] = $input['priority'] ?? ($config['default_priority'] ?? 3);
        $input['type'] = $input['type'] ?? 1; // Incident
        $input['status'] = $input['status'] ?? 1; // New
        
        // Convert strings to IDs/numbers
        if (isset($input['_users_id_requester']) && !is_numeric($input['_users_id_requester'])) {
            $input['_users_id_requester'] = self::findUser($input['_users_id_requester']);
        }
        
        if (isset($input['_users_id_assign']) && !is_numeric($input['_users_id_assign'])) {
            $input['_users_id_assign'] = self::findUser($input['_users_id_assign']);
        }
        
        if (isset($input['itilcategories_id']) && !is_numeric($input['itilcategories_id'])) {
            $input['itilcategories_id'] = self::findCategory($input['itilcategories_id']);
        }
        
        if (isset($input['entities_id']) && !is_numeric($input['entities_id'])) {
            $input['entities_id'] = self::findEntity($input['entities_id']);
        }
        
        if (isset($input['locations_id']) && !is_numeric($input['locations_id'])) {
            $input['locations_id'] = self::findLocation($input['locations_id']);
        }
        
        // Convert enums
        if (isset($input['urgency'])) {
            $input['urgency'] = self::convertUrgency($input['urgency']);
        }
        if (isset($input['impact'])) {
            $input['impact'] = self::convertImpact($input['impact']);
        }
        if (isset($input['priority'])) {
            $input['priority'] = self::convertPriority($input['priority']);
        }
        if (isset($input['type'])) {
            $input['type'] = self::convertType($input['type']);
        }
        if (isset($input['status'])) {
            $input['status'] = self::convertStatus($input['status']);
        }
        
        // Convert dates
        if (isset($input['date']) && !empty($input['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $input['date'])) {
            $date = strtotime($input['date']);
            if ($date !== false) {
                $input['date'] = date('Y-m-d H:i:s', $date);
            } else {
                unset($input['date']);
            }
        }
        
        if (isset($input['time_to_resolve']) && !empty($input['time_to_resolve']) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $input['time_to_resolve'])) {
            $date = strtotime($input['time_to_resolve']);
            if ($date !== false) {
                $input['time_to_resolve'] = date('Y-m-d H:i:s', $date);
            } else {
                unset($input['time_to_resolve']);
            }
        }
        
        return $input;
    }
    
    /**
     * Get plugin config
     */
    static function getConfig($field = null) {
        global $DB;
        
        $config = [];
        if ($DB->tableExists(self::getTable())) {
            $iterator = $DB->request([
                'FROM' => self::getTable(),
                'LIMIT' => 1
            ]);
            $config = $iterator->current() ?: [];
        }
        
        return $field && isset($config[$field]) ? $config[$field] : $config;
    }
    
    /**
     * Helper methods for field conversion
     */
    static function convertPriority($value) {
        $priorities = [
            'very low' => 1, 'verylow' => 1, '1' => 1,
            'low' => 2, '2' => 2,
            'medium' => 3, 'med' => 3, '3' => 3,
            'high' => 4, '4' => 4,
            'very high' => 5, 'veryhigh' => 5, '5' => 5
        ];
        $value = strtolower(trim($value));
        return $priorities[$value] ?? 3;
    }
    
    static function convertStatus($value) {
        $statuses = [
            'new' => 1, 'incoming' => 1, '1' => 1,
            'in progress' => 2, 'progress' => 2, '2' => 2,
            'waiting' => 3, '3' => 3,
            'solved' => 5, '5' => 5,
            'closed' => 6, '6' => 6
        ];
        $value = strtolower(trim($value));
        return $statuses[$value] ?? 1;
    }
    
    static function convertType($value) {
        $types = [
            'incident' => 1, '1' => 1,
            'request' => 2, 'demand' => 2, '2' => 2
        ];
        $value = strtolower(trim($value));
        return $types[$value] ?? 1;
    }
    
    static function convertUrgency($value) {
        return self::convertPriority($value);
    }
    
    static function convertImpact($value) {
        return self::convertPriority($value);
    }
    
    static function findUser($identifier) {
        $user = new User();
        $users = $user->find([
            'OR' => [
                'name' => $identifier,
                'realname' => $identifier,
                'firstname' => $identifier,
                'email' => $identifier
            ]
        ]);
        
        if (count($users) > 0) {
            $user_data = reset($users);
            return $user_data['id'];
        }
        
        return 0;
    }
    
    static function findCategory($name) {
        $category = new ITILCategory();
        $categories = $category->find(['name' => $name]);
        
        if (count($categories) > 0) {
            $cat_data = reset($categories);
            return $cat_data['id'];
        }
        
        return 0;
    }
    
    static function findEntity($name) {
        $entity = new Entity();
        $entities = $entity->find(['name' => $name]);
        
        if (count($entities) > 0) {
            $ent_data = reset($entities);
            return $ent_data['id'];
        }
        
        return $_SESSION['glpiactive_entity'] ?? 0;
    }
    
    static function findLocation($name) {
        $location = new Location();
        $locations = $location->find(['name' => $name]);
        
        if (count($locations) > 0) {
            $loc_data = reset($locations);
            return $loc_data['id'];
        }
        
        return 0;
    }
}
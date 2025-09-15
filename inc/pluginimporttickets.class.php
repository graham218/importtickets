<?php
/**
 * Main plugin class for Ticket Import
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginImporttickets extends CommonDBTM {
    
    static $rightname = 'plugin_importtickets';
    
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
            'csv'  => __('CSV File', 'importtickets'),
            'json' => __('JSON File', 'importtickets')
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
        
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            
            // Get field mapping from options or use default
            $field_mapping = $options['field_mapping'] ?? [];
            if (empty($field_mapping)) {
                $field_mapping = self::getDefaultFieldMapping($headers);
            }
            
            $line = 1;
            $has_headers = $options['has_headers'] ?? true;
            
            if ($has_headers) {
                $line++; // Skip header row
            }
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $line++;
                
                // Skip empty rows
                if (count(array_filter($data)) === 0) {
                    $results['skipped']++;
                    continue;
                }
                
                if (count($data) != count($headers)) {
                    $results['errors']++;
                    $results['messages'][] = sprintf(__('Line %d: Invalid number of columns', 'importtickets'), $line);
                    continue;
                }
                
                $row_data = array_combine($headers, $data);
                $ticket_data = [];
                
                // Map CSV columns to GLPI fields
                foreach ($field_mapping as $csv_field => $glpi_field) {
                    if (isset($row_data[$csv_field]) && !empty($glpi_field) && trim($row_data[$csv_field]) !== '') {
                        $ticket_data[$glpi_field] = trim($row_data[$csv_field]);
                    }
                }
                
                // Process the ticket
                $ticket_id = self::createTicket($ticket_data, $options);
                if ($ticket_id) {
                    $results['success']++;
                    $results['imported_ids'][] = $ticket_id;
                } else {
                    $results['errors']++;
                    $results['messages'][] = sprintf(__('Line %d: Failed to create ticket', 'importtickets'), $line);
                }
            }
            fclose($handle);
        }
        
        return $results;
    }
    
    /**
     * Get default field mapping based on headers
     */
    static function getDefaultFieldMapping($headers) {
        $default_mapping = [
            'title' => 'name',
            'description' => 'content',
            'requester' => '_users_id_requester',
            'technician' => '_users_id_assign',
            'group' => '_groups_id_assign',
            'category' => 'itilcategories_id',
            'priority' => 'priority',
            'type' => 'type',
            'status' => 'status',
            'urgency' => 'urgency',
            'impact' => 'impact',
            'entity' => 'entities_id',
            'location' => 'locations_id'
        ];
        
        $mapping = [];
        foreach ($headers as $header) {
            $header_lower = strtolower(trim($header));
            $mapping[$header] = $default_mapping[$header_lower] ?? '';
        }
        
        return $mapping;
    }
    
    /**
     * Create a ticket from data
     */
    static function createTicket($data, $options = []) {
        $ticket = new Ticket();
        
        // Set default values
        $defaults = [
            'name' => __('Imported Ticket', 'importtickets'),
            'content' => __('Imported from CSV', 'importtickets'),
            '_users_id_requester' => Session::getLoginUserID(),
            'itilcategories_id' => 0,
            'priority' => 3,
            'urgency' => 3,
            'impact' => 2,
            'type' => Ticket::INCIDENT_TYPE,
            'status' => Ticket::INCOMING,
            'entities_id' => $_SESSION['glpiactive_entity'],
            'date' => date('Y-m-d H:i:s'),
            'date_mod' => date('Y-m-d H:i:s')
        ];
        
        $input = array_merge($defaults, $data);
        
        // Convert field values to appropriate types
        $input = self::convertFieldValues($input);
        
        // Validate required fields
        if (empty($input['name']) || empty($input['content'])) {
            return false;
        }
        
        try {
            $ticket_id = $ticket->add($input);
            
            if ($ticket_id && !empty($options['add_followup'])) {
                $followup = new ITILFollowup();
                $followup->add([
                    'items_id' => $ticket_id,
                    'itemtype' => 'Ticket',
                    'content' => __('Ticket imported from CSV', 'importtickets'),
                    'users_id' => Session::getLoginUserID()
                ]);
            }
            
            return $ticket_id;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Convert field values to appropriate types
     */
    static function convertFieldValues($input) {
        // Convert priority
        if (isset($input['priority'])) {
            $input['priority'] = self::convertPriority($input['priority']);
        }
        
        // Convert status
        if (isset($input['status'])) {
            $input['status'] = self::convertStatus($input['status']);
        }
        
        // Convert type
        if (isset($input['type'])) {
            $input['type'] = self::convertType($input['type']);
        }
        
        // Convert urgency
        if (isset($input['urgency'])) {
            $input['urgency'] = self::convertUrgency($input['urgency']);
        }
        
        // Convert impact
        if (isset($input['impact'])) {
            $input['impact'] = self::convertImpact($input['impact']);
        }
        
        // Find user by login/email/name
        if (isset($input['_users_id_requester']) && !is_numeric($input['_users_id_requester'])) {
            $input['_users_id_requester'] = self::findUser($input['_users_id_requester']);
        }
        
        if (isset($input['_users_id_assign']) && !is_numeric($input['_users_id_assign'])) {
            $input['_users_id_assign'] = self::findUser($input['_users_id_assign']);
        }
        
        // Find category by name
        if (isset($input['itilcategories_id']) && !is_numeric($input['itilcategories_id'])) {
            $input['itilcategories_id'] = self::findCategory($input['itilcategories_id']);
        }
        
        // Find entity by name
        if (isset($input['entities_id']) && !is_numeric($input['entities_id'])) {
            $input['entities_id'] = self::findEntity($input['entities_id']);
        }
        
        // Find location by name
        if (isset($input['locations_id']) && !is_numeric($input['locations_id'])) {
            $input['locations_id'] = self::findLocation($input['locations_id']);
        }
        
        // Convert dates
        if (isset($input['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $input['date'])) {
            $input['date'] = date('Y-m-d H:i:s', strtotime($input['date']));
        }
        
        if (isset($input['time_to_resolve']) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $input['time_to_resolve'])) {
            $input['time_to_resolve'] = date('Y-m-d H:i:s', strtotime($input['time_to_resolve']));
        }
        
        return $input;
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
        
        return $_SESSION['glpiactive_entity'];
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
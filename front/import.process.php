<?php
/**
 * Process ticket import
 */

include ('../../../inc/includes.php');

// Check rights and CSRF
Session::checkRight('plugin_importtickets', CREATE);
Session::checkCSRF();

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    Session::addMessageAfterRedirect(__('Please select a valid file to import', 'importtickets'), false, ERROR);
    Html::back();
}

// Validate file
$format = $_POST['format'] ?? 'csv';
$file_path = $_FILES['import_file']['tmp_name'];
$file_name = $_FILES['import_file']['name'];

// Check file extension
$allowed_extensions = [
    'csv' => ['csv', 'txt']
];

$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions[$format])) {
    Session::addMessageAfterRedirect(
        sprintf(__('Invalid file extension for %s format', 'importtickets'), $format),
        false,
        ERROR
    );
    Html::back();
}

// Check file size (max 10MB)
if ($_FILES['import_file']['size'] > 10 * 1024 * 1024) {
    Session::addMessageAfterRedirect(__('File size too large (max 10MB)', 'importtickets'), false, ERROR);
    Html::back();
}

// Process import
try {
    $options = [
        'has_headers' => ($_POST['has_headers'] ?? 1) == 1,
        'add_followup' => ($_POST['add_followup'] ?? 1) == 1
    ];
    
    $results = [];
    
    switch ($format) {
        case 'csv':
            $results = PluginImporttickets::processCSVImport($file_path, $options);
            break;
            
        default:
            throw new Exception(__('Unsupported format', 'importtickets'));
    }
    
    // Prepare result message
    $message = sprintf(
        __('Import completed: %d successful, %d errors, %d skipped', 'importtickets'),
        $results['success'],
        $results['errors'],
        $results['skipped']
    );
    
    $message_type = ($results['errors'] == 0) ? INFO : WARNING;
    Session::addMessageAfterRedirect($message, true, $message_type);
    
    // Add detailed messages
    if (!empty($results['messages'])) {
        foreach ($results['messages'] as $detail_message) {
            Session::addMessageAfterRedirect($detail_message, false, ERROR);
        }
    }
    
    // Show link to imported tickets if any
    if (!empty($results['imported_ids'])) {
        $ticket_links = [];
        foreach ($results['imported_ids'] as $ticket_id) {
            $ticket_links[] = "<a href='" . Ticket::getFormURLWithID($ticket_id) . "'>#$ticket_id</a>";
        }
        
        if (count($ticket_links) > 0) {
            $imported_message = __('Imported tickets: ', 'importtickets') . implode(', ', $ticket_links);
            Session::addMessageAfterRedirect($imported_message, false, INFO);
        }
    }
    
} catch (Exception $e) {
    Session::addMessageAfterRedirect(
        __('Import failed: ', 'importtickets') . $e->getMessage(),
        false,
        ERROR
    );
}

Html::back();
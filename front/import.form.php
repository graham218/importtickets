<?php
/**
 * Import form for tickets
 */

include ('../../../inc/includes.php');

// Check rights
Session::checkRight('plugin_importtickets', READ);

Html::header(__('Ticket Import', 'importtickets'), $_SERVER['PHP_SELF'], 'tools', 'PluginImporttickets');

echo "<div class='center'>";
echo "<h2>" . __('Import Tickets', 'importtickets') . "</h2>";

echo "<form method='post' action='import.process.php' enctype='multipart/form-data' id='importForm'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Import Options', 'importtickets') . "</th></tr>";

// File format selection
echo "<tr class='tab_bg_1'>";
echo "<td width='30%'>" . __('File Format', 'importtickets') . "</td>";
echo "<td>";
Dropdown::showFromArray('format', PluginImporttickets::getImportFormats(), [
    'value' => 'csv'
]);
echo "</td></tr>";

// File upload
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('File to import', 'importtickets') . "</td>";
echo "<td>";
echo "<input type='file' name='import_file' id='import_file' accept='.csv,.txt' required>";
echo "<br><small>" . __('Maximum file size: ', 'importtickets') . ini_get('upload_max_filesize') . "</small>";
echo "</td></tr>";

// Options
echo "<tr class='tab_bg_1'>";
echo "<td>" . __('First row contains headers', 'importtickets') . "</td>";
echo "<td>";
Dropdown::showYesNo('has_headers', 1);
echo "</td></tr>";

echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Add import followup', 'importtickets') . "</td>";
echo "<td>";
Dropdown::showYesNo('add_followup', 1);
echo "</td></tr>";

// Submit button
echo "<tr class='tab_bg_2'>";
echo "<td colspan='2' class='center'>";
echo Html::submit(__('Start Import', 'importtickets'), ['name' => 'import', 'class' => 'btn btn-primary']);
echo "</td></tr>";

echo "</table>";
Html::closeForm();

// Sample CSV format
echo "<br>";
echo "<h3>" . __('CSV Format Example', 'importtickets') . "</h3>";
echo "<div class='sample-csv'>";
echo "<pre style='background: #f4f4f4; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;'>";
echo "title,description,requester,technician,category,priority,type,status,urgency,impact,entity\n";
echo "\"Network Issue\",\"Internet connection is slow\",\"user@example.com\",\"tech@example.com\",\"Network\",\"high\",\"incident\",\"new\",\"high\",\"medium\",\"Root entity\"\n";
echo "\"Software Request\",\"Need Photoshop installed\",\"admin@example.com\",\"\",\"Software\",\"medium\",\"request\",\"in progress\",\"medium\",\"low\",\"\"";
echo "</pre>";
echo "<small>" . __('Note: Empty fields will use default values', 'importtickets') . "</small>";
echo "</div>";

echo "</div>";

Html::footer();
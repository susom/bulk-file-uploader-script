<?php

require "vendor/autoload.php";

// Load the config
$config = (object) parse_ini_file("_config.ini");

// Parse arguments
parse_str(implode('&', array_slice($argv, 1)), $_GET);

$usage = "call script and specify two parameters:\n" .
    " field: the field containing the files to download\n" .
    " folder: the folder to place the files in\n\n" .
    "php bulk_download.php field=upload_field folder=dest_dir\n\n";

$log = [];

if (empty($_GET['field'])) {
    exit("$usage Please include field=upload_field as an argument");
}

if (empty($_GET['folder'])) {
    exit("$usage Please include folder=dest_dir as an argument");
}
$folder = $_GET['folder'];
$field  = filter_var($_GET['field'], FILTER_SANITIZE_STRING);

if (! is_dir($folder)) {
    exit("$usage The specified folder $folder does not exist");
}

if (empty($config->apiToken)) {
    exit("An apiToken must be specified in the _config.ini file");
}

if (empty($config->apiUrl)) {
    exit("An apiUrl must be specified in the _config.ini file");
}

$phpCap = new IU\PHPCap\RedCapProject($config->apiUrl, $config->apiToken);

// Get record_id field
$m = $phpCap->exportMetadata('php');
$first_field = array_shift($m);
$record_id_field = $first_field['field_name'];

// Get all records with a value for that file:
$q = $phpCap->exportRecords('php','flat',null,array($record_id_field, $field));

foreach ($q as $record) {
    $record_id = $record[$record_id_field];
    if(!empty($record[$field])) {
        $file = $phpCap->exportFile($record_id,$field);
        var_dump($file);
    }
}


var_dump($q);

exit();






// Scan directory for files
$files = scandir($folder);
if (empty($files)) {
    exit("No files were found in $folder");
}

// Filter out files
function filterFiles($var) {
    // Skip . files
    if(substr($var,0,1) == ".") return false;

    return true;
}

$files = array_filter($files,"filterFiles");
$log[] = "Found " . count($files) . " in $folder";


// Get existing data
$rawData = $phpCap->exportRecords('php','flat',null);
// $log['rawData'] = $rawData;

$records        = [];
$next_id        = 1;
$existing_files = [];  // An array where key is key_filename and value is record_id
$used_keys      = 0;
foreach ($rawData as $record) {
    // $log[] = $record;
    $record_id  = $record[$config->record_id_field];
    $file_name  = $record[$config->file_name_field];
    $is_used    = $record[$config->claimed_field];

    if(is_numeric($record_id) && intval($record_id) >= $next_id) $next_id++;
    if(!empty($is_used)) $used_keys++;

    $existing_files[$file_name] = $record_id;
    $records[$record_id] = $record;
}
unset($rawData);

$log[] = $used_keys . " keys are used";
$log[] = "Next record ID is " . $next_id;

$import_records = [];
$skipped_files  = [];
foreach ($files as $file) {
    // Make sure filename is unique
    if (isset($existing_files[$file])) {
        $skipped_files[] = $file;
        $log[] = "Skipping file $file as it already exists in record " . $existing_files[$file];
        continue;
    }

    // Create the record
    $record = [
        $config->record_id_field        => $next_id,
        $config->file_name_field        => $file,
        $config->date_uploaded_field    => date("Y-m-d H:i:s")
    ];
    $result = $phpCap->importRecords(array($record));
    // $log['import_record_' . $next_id . '_result'] = $result;

    // Upload the file
    $result = $phpCap->importFile(
        "$folder/$file",
        $next_id,
        $config->file_field
    );
    // $log['import_file_' . $next_id . '_result'] = $result;
    $import_records[] = $record;
    $next_id++;
}



$log['skipped_files_count'] = count($skipped_files);
$log['imported_record_count'] = count($import_records);

print_r($log);


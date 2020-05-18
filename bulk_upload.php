<?php

require "vendor/autoload.php";

// Load the config
$config = (object) parse_ini_file("_config.ini");

// Parse arguments
parse_str(implode('&', array_slice($argv, 1)), $_GET);

$usage = "call script and specify the folder containing the files to upload, as in:\n" .
    "php bulk_upload.php folder=my_files\n" .
    "where my_files contains all of the keys to upload.\n\n";

$log = [];

if (empty($_GET['folder'])) {
    exit("$usage Please include folder=path_to_folder as an argument");
}
$folder = $_GET['folder'];

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


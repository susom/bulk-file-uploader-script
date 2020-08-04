<?php

require "vendor/autoload.php";

// Load the config
$config = (object) parse_ini_file("_config.ini");

// Parse arguments
parse_str(implode('&', array_slice($argv, 1)), $_GET);

$usage = "call script to make a copy of all key files as key_myphd if it doesn't already exist\n";
$log = [];

if (empty($config->apiToken)) {
    exit("An apiToken must be specified in the _config.ini file");
}

if (empty($config->apiUrl)) {
    exit("An apiUrl must be specified in the _config.ini file");
}

$phpCap = new IU\PHPCap\RedCapProject($config->apiUrl, $config->apiToken);

// Get existing data
$rawData = $phpCap->exportRecords('php','flat',null);

$records        = [];
$existing_files = [];  // An array where key is key_filename and value is record_id
$used_keys      = 0;

$count = 0;

$tempdir = sys_get_temp_dir();

foreach ($rawData as $record) {
    $record_id       = $record[$config->record_id_field];
    $file_name       = $record[$config->file_name_field];
    $file_name_myphd = $record[$config->file_field_myphd];

    if (!empty($file_name_myphd) || empty($file_name)) {
        // do nothing
        continue;
    }

    // Only process up to limit
    $count++;
    if ($count > $config->limit) continue;

    echo "\n Processing $record_id - $file_name";

    if (empty($record[$config->file_field])) {
        echo " FILE MISSING";
        continue;
    }

    // Get file
    $file = $phpCap->exportFile($record_id,$config->file_field);
    $new_file = str_replace(".txt",".myphd",$file_name);
    $new_file_path = $tempdir . "/" . $new_file;
    file_put_contents($new_file_path,$file);

    $phpCap->importFile(
        $new_file_path,
        $record_id,
        $config->file_field_myphd
    );

    echo "... duplicated to $new_file";
    unlink($new_file_path);
}

exit();

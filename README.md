# bulk-file-uploader-script
REDCap script to do bulk file uploading - built for 55577 keys

1. Copy the _config-example.ini to _config.ini and edit it to contain your api token and api url
1. Create a folder with all the files you want to upload.  Make sure the mapping for field names in the _config.ini match your destination project.
1. Run a command like this:
```
php bulk_upload.php folder=location_of_files
```

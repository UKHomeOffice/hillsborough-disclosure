<?php

require_once("library/database.php");
require_once("library/hillsboroughlog.php");
require_once("library/schema.php");
require_once("library/config.php");
require_once("library/functions.php");

if (!isset($argv[1]))
{
	echo "No params supplied (expecting filename) so exiting.\r\n";
	exit();
}

$db = new Database( DB_HOSTNAME, DB_NAME, DB_USER, DB_PASS );

$file = $argv[1];

echo "Importing contributor data from ".$file."\r\n";
$autoimport_log = new HillsboroughLog( "autopopulate-import", LOG_DIR );
$importtype = "autopopulate";
$importarray = array('owning_organisation','see_also', 'short_title','description','nondisclosed_reason', 'nondisclosed_summary', 'dir_name','lextranet_title','non_contributing', 'comments');
$uniqueID = array();
$start_time = microtime(true);

$row = 0;
$buildrow = 0;
$build = array();

$autoimport_log->write("Importing file: $file");

// start csv import
if (($handle = fopen($file, "r")) !== FALSE) 
{
	while (($data = fgetcsv($handle, 0, ",")) !== FALSE) 
	{	
	
		
		if ($row++ > 0 && $data[0] != '')
		{
			foreach ($data as $key => $value)
			{
			}
			$buildrow++;
		}
}
else
{
	$error_log->write("File: $file was not found. Finishing.");
	die("File not found");
}
foreach ($build as $row)
{
		
		if ($db->dbUpdate($sql))
		{
			$autoimport_log->write("Added contributor: " . $row['owning_organisation']);
		}

$importcomplete = "Import completed in " . (microtime(true) - $start_time) . " : " . $db->getQueryCount() . " queries executed";
$autoimport_log->write($importcomplete);

echo "Importing contributors completed\r\n";
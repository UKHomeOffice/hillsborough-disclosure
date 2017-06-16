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
echo "Importing \"material referenced in the report\" from ".$file." from " . $file . "\r\n";
$autoimport_log = new HillsboroughLog( "referenced-in-report", LOG_DIR );
$importtype = "referenced-in-report";
$start_time = microtime(true);
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
			{
				$build[$buildrow][$importarray[$key]] = trim($value);
			}
			$buildrow++;
		}
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
		echo "Added lookup data for " . $docID . " : value=> " . $references . "\r\n";
	}

$importcomplete = "Import completed in " . (microtime(true) - $start_time) . " : " . $db->getQueryCount() . " queries executed";
$autoimport_log->write($importcomplete);

echo "Importing autopopulate completed\r\n";
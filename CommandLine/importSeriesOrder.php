<?php
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
$importtype = "series-ordering";
$start_time = microtime(true);
$buildrow = 0;
$build = array();


// start csv import
if (($handle = fopen($seriesOrderFile, "r")) !== FALSE) 
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
	die("File not found");
}
{	
		if ($db->dbUpdate($sql))
		{
			echo "Added sub folder " . $row['sub_series_title'] . "\r\n";
echo "Import completed in " . (microtime(true) - $start_time) . " : " . $db->getQueryCount() . " queries executed\r\n";
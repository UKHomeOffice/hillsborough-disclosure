<?php
require_once("library/database.php");
require_once("library/hillsboroughlog.php");
require_once("library/schema.php");
require_once("library/config.php");
require_once("library/functions.php");
$db = new Database( DB_HOSTNAME, DB_NAME, DB_USER, DB_PASS );
echo "Additional validation\r\n";

$buildrow = 0;
$build = array();


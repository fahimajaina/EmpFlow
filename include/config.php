<?php 
// Set timezone - adjust to your local timezone
// Common timezones: 'Asia/Manila', 'America/New_York', 'Europe/London', 'Asia/Kolkata'
date_default_timezone_set('Asia/Dhaka');

// DB credentials.
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','empflow');
// Establish database connection.
try
{
$dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME,DB_USER, DB_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
}
catch (PDOException $e)
{
 die("DB Connection failed: " . $e->getMessage());
}
?>
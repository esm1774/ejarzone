<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Error Log:\n";
echo str_repeat("-", 50) . "\n";
$error_log = file_get_contents("C:/xampp/php/logs/php_error_log");
echo $error_log ? $error_log : "No errors found in log file";

echo "\n\nXAMPP Error Log:\n";
echo str_repeat("-", 50) . "\n";
$xampp_log = file_get_contents("C:/xampp/apache/logs/error.log");
echo $xampp_log ? $xampp_log : "No errors found in log file";

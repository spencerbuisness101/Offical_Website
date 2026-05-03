<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "PHP Version: " . PHP_VERSION . "\n";
require_once 'includes/init.php';
echo "Init loaded successfully.\n";
require_once 'includes/db.php';
echo "DB loaded successfully.\n";
require_once 'includes/csrf.php';
echo "CSRF loaded successfully.\n";
echo "All shared files loaded.\n";

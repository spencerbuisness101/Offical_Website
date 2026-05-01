<?php
/**
 * Login Page Redirect
 * Redirects to the new unified index.php landing page
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: main.php');
    exit;
}

// Preserve any query parameters (like forced_logout)
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: index.php' . $query);
exit;

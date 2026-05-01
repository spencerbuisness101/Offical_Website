<?php
/**
 * 403 Forbidden Error Page - Spencer's Website v7.0
 */

define('ERROR_PAGE', true);
http_response_code(403);

$error_code = 403;
$error_title = 'Access Denied';
$error_icon = '&#x1F6AB;';

// Context-aware message based on session state
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$role = $_SESSION['role'] ?? null;
$requested = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');

if (!$is_logged_in) {
    $error_message = 'You need to be logged in to access this page.';
    $error_description = 'Please sign in with your account to continue.';
} elseif ($role === 'community') {
    $error_message = 'Your account doesn\'t have permission to view this area.';
    $error_description = 'Community accounts have limited access. Contact an admin if you believe this is a mistake.';
} else {
    $error_message = 'You do not have permission to access this resource.';
    $error_description = 'If you believe this is an error, please contact an administrator.';
}

require_once __DIR__ . '/error_template.php';

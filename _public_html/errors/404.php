<?php
/**
 * 404 Not Found Error Page - Spencer's Website v7.0
 */

define('ERROR_PAGE', true);
http_response_code(404);

$error_code = 404;
$error_title = 'Page Not Found';
$error_icon = '&#x1F50D;';

// Build context-aware message from the requested path
$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedPath = htmlspecialchars(basename(rtrim($requestedPath, '/')));

if ($requestedPath && $requestedPath !== '/') {
    $error_message = "\u201c{$requestedPath}\u201d doesn't exist here.";
} else {
    $error_message = "The page you're looking for doesn't exist.";
}

$error_description = 'It may have been moved, renamed, or never existed. Check the URL and try again.';

require_once __DIR__ . '/error_template.php';

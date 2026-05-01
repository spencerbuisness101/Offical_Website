<?php
/**
 * 500 Internal Server Error Page
 * Spencer's Website v5.0
 */

define('ERROR_PAGE', true);
http_response_code(500);

$error_code = 500;
$error_title = 'Internal Server Error';
$error_message = 'Something went wrong on our end. Our team has been notified. Please try again later.';
$error_icon = '&#x2699;'; // Gear

require_once __DIR__ . '/error_template.php';

<?php
/**
 * 400 Bad Request Error Page
 * Spencer's Website v5.0
 */

define('ERROR_PAGE', true);
http_response_code(400);

$error_code = 400;
$error_title = 'Bad Request';
$error_message = 'The server could not understand your request. Please check your input and try again.';
$error_icon = '&#x1F6AB;'; // No entry sign

require_once __DIR__ . '/error_template.php';

<?php
/**
 * 401 Unauthorized Error Page
 * Spencer's Website v5.0
 */

define('ERROR_PAGE', true);
http_response_code(401);

$error_code = 401;
$error_title = 'Unauthorized';
$error_message = 'You need to log in to access this page. Please authenticate and try again.';
$error_icon = '&#x1F512;'; // Lock

require_once __DIR__ . '/error_template.php';

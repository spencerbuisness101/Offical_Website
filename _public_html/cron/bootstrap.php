<?php
/**
 * Cron Bootstrap - Spencer's Website v7.0
 * Shared setup for all cron scripts.
 * Returns a PDO $db connection.
 */

// Reject web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

define('APP_RUNNING', true);

// Load .env
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load database config
require_once __DIR__ . '/../config/database.php';

// Load payment & subscription helpers
if (file_exists(__DIR__ . '/../includes/payment.php')) {
    require_once __DIR__ . '/../includes/payment.php';
}
if (file_exists(__DIR__ . '/../includes/subscription.php')) {
    require_once __DIR__ . '/../includes/subscription.php';
}

// Connect to database
$database = new Database();
$db = $database->getConnection();

return $db;

<?php
/**
 * Cron Job: Purge Downgraded Account Data
 * 
 * This script runs daily to permanently delete sensitive data from accounts
 * that were downgraded more than 7 days ago.
 * 
 * USAGE:
 * - Run manually: php cron/purge_downgraded_data.php
 * - Cron job (daily at 3 AM): 0 3 * * * /usr/bin/php /path/to/cron/purge_downgraded_data.php
 */

define('APP_RUNNING', true);
define('CRON_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/DowngradeManager.php';

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_WEB_ALLOWED')) {
    http_response_code(403);
    die('This script must be run from the command line or cron.');
}

$logFile = __DIR__ . '/../logs/cron_purge.log';
$logDir = dirname($logFile);

// Create logs directory if needed
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    echo $logEntry;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    logMessage("Starting data purge process...");
    
    $downgradeManager = new DowngradeManager();
    $result = $downgradeManager->purgeDowngradedData();
    
    if ($result['success']) {
        $count = $result['purged_count'];
        
        if ($count > 0) {
            logMessage("Successfully purged data for {$count} account(s):");
            foreach ($result['users'] as $user) {
                logMessage("  - User ID: {$user['id']}, Username: {$user['username']}");
            }
        } else {
            logMessage("No accounts ready for data purge.");
        }
        
        logMessage("Process completed successfully.");
        exit(0);
        
    } else {
        logMessage("ERROR: " . $result['message']);
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

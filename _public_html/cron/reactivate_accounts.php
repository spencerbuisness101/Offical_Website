<?php
/**
 * Cron Job: Reactivate Expired Account Suspensions
 * 
 * This script runs periodically (e.g., every hour) to automatically reactivate
 * Paid Accounts whose Time Removal punishment has expired.
 * 
 * USAGE:
 * - Run manually: php cron/reactivate_accounts.php
 * - Cron syntax: every hour
 * - Cron syntax: every 15 minutes
 */

define('APP_RUNNING', true);
define('CRON_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/StrikeManager.php';

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_WEB_ALLOWED')) {
    http_response_code(403);
    die('This script must be run from the command line or cron.');
}

$logFile = __DIR__ . '/../logs/cron_reactivate.log';
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
    logMessage("Starting account reactivation process...");
    
    $strikeManager = new StrikeManager();
    $result = $strikeManager->reactivateExpiredSuspensions();
    
    if ($result['success']) {
        $count = $result['reactivated_count'];
        
        if ($count > 0) {
            logMessage("Successfully reactivated {$count} account(s):");
            foreach ($result['users'] as $user) {
                logMessage("  - User ID: {$user['id']}, Username: {$user['username']}");
            }
        } else {
            logMessage("No expired suspensions found.");
        }
        
        // Log completion
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

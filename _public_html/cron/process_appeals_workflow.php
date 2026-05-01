<?php
/**
 * Cron Job: Process Appeal Workflow Automation
 * 
 * Runs every 5 minutes to auto-process pending appeals based on workflow rules.
 * Also processes notification queue and cleans up expired ephemeral notifications.
 * 
 * USAGE:
 * - Run manually: php cron/process_appeals_workflow.php
 * - Cron syntax: every 5 minutes
 */

define('APP_RUNNING', true);
define('CRON_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/AppealWorkflow.php';
require_once __DIR__ . '/../includes/SystemNotificationManager.php';

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_WEB_ALLOWED')) {
    http_response_code(403);
    die('This script must be run from the command line or cron.');
}

$logFile = __DIR__ . '/../logs/cron_workflow.log';
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
    logMessage("Starting appeal workflow automation...");
    
    // 1. Process pending appeals through workflow rules
    logMessage("Processing pending appeals...");
    
    $workflow = new AppealWorkflow();
    $results = $workflow->processPendingAppeals(20);
    
    if (isset($results['error'])) {
        logMessage("ERROR processing appeals: " . $results['error']);
    } else {
        logMessage("Processed {$results['processed']} appeals:");
        logMessage("  - Auto-approved: {$results['auto_approved']}");
        logMessage("  - Auto-denied: {$results['auto_denied']}");
        logMessage("  - Flagged for review: {$results['flagged_for_review']}");
        
        if (!empty($results['errors'])) {
            foreach ($results['errors'] as $error) {
                logMessage("  ERROR: " . $error);
            }
        }
    }
    
    // 2. Process notification queue
    logMessage("Processing notification queue...");
    
    $notificationManager = new SystemNotificationManager();
    $queueResults = $notificationManager->processQueue(50);
    
    if ($queueResults['success']) {
        logMessage("Processed {$queueResults['processed']} notifications ({$queueResults['failed']} failed)");
    } else {
        logMessage("ERROR processing queue: " . $queueResults['error']);
    }
    
    // 3. Clean up expired ephemeral notifications
    logMessage("Cleaning up expired notifications...");
    
    $cleanupResults = $notificationManager->cleanupExpired();
    
    if ($cleanupResults['success']) {
        logMessage("Deleted {$cleanupResults['deleted_count']} expired ephemeral notifications");
    } else {
        logMessage("ERROR cleaning up: " . $cleanupResults['error']);
    }
    
    // 4. Check for SLA breaches (appeals pending >72 hours)
    logMessage("Checking SLA compliance...");
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("
        SELECT COUNT(*) FROM lockdown_appeals 
        WHERE status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND flagged_for_review = FALSE
    ");
    $slaBreaches = $stmt->fetchColumn();
    
    if ($slaBreaches > 0) {
        logMessage("WARNING: {$slaBreaches} appeals pending over 72 hours (SLA breach)");
        
        // Notify admins about SLA breaches
        $stmt = $db->query("SELECT id FROM users WHERE role = 'admin'");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $adminId) {
            $notificationManager->send(
                $adminId,
                'SLA_ALERT',
                'Appeal SLA Alert',
                "{$slaBreaches} appeal(s) have been pending for over 72 hours and require immediate review.",
                ['priority' => 'high', 'link' => '/admin/review_appeals.php']
            );
        }
    } else {
        logMessage("SLA compliance: OK");
    }
    
    logMessage("Workflow automation completed.");
    exit(0);
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

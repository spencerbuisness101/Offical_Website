<?php
/**
 * Cron Job: Process Scheduled Account Deletions
 *
 * Runs daily. Purges accounts where deletion_scheduled_at <= NOW().
 * Retains email_hash and device_fingerprint for ban list.
 * Public posts remain with "[Deleted User]" attribution.
 *
 * USAGE:
 *   php cron/process_account_deletions.php
 *   Cron (daily at 2 AM): 0 2 * * * /usr/bin/php /path/to/cron/process_account_deletions.php
 */

define('APP_RUNNING', true);
define('CRON_RUNNING', true);

if (php_sapi_name() !== 'cli' && !defined('CRON_WEB_ALLOWED')) {
    http_response_code(403);
    die('This script must be run from the command line or cron.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PunishmentManager.php';

$logFile = __DIR__ . '/../logs/cron_deletions.log';

function logMsg($msg) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . "] {$msg}" . PHP_EOL;
    echo $entry;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

try {
    logMsg("Starting account deletion processing...");

    $database = new Database();
    $db = $database->getConnection();
    $punMgr = new PunishmentManager();

    // Find accounts past the 30-day grace period
    $stmt = $db->query("
        SELECT id, username, email
        FROM users
        WHERE account_status = 'pending_deletion'
          AND deletion_scheduled_at IS NOT NULL
          AND deletion_scheduled_at <= NOW()
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMsg("Found " . count($accounts) . " account(s) to purge.");

    $purged = 0;
    $failed = 0;

    foreach ($accounts as $account) {
        try {
            $userId = (int)$account['id'];
            logMsg("Purging user ID {$userId} ({$account['username']})...");

            // 1. Anonymize all user content
            $punMgr->anonymizeUserContent($userId);

            // 2. Hash email for ban list (preserve for security; clear plaintext)
            $pepper    = $_ENV['PEPPER_SECRET'] ?? '';
            $emailHash = hash('sha256', ($account['email'] ?? '') . $pepper);

            // 3. Permanently purge personal data; retain email_hash + device_fingerprint
            $db->prepare("
                UPDATE users
                SET account_status          = 'terminated',
                    email                   = NULL,
                    email_hash              = ?,
                    declared_birthdate      = NULL,
                    age_verified_at         = NULL,
                    deletion_scheduled_at   = NULL,
                    deletion_requested_at   = NULL,
                    terminated_at           = NOW(),
                    termination_reason      = 'User-requested deletion (grace period expired)'
                WHERE id = ?
            ")->execute([$emailHash, $userId]);

            // 4. Delete IP logs (personal data)
            $db->prepare("DELETE FROM user_login_history WHERE user_id = ?")->execute([$userId]);

            // 5. Cancel subscription if active
            if (file_exists(__DIR__ . '/../includes/subscription.php')) {
                require_once __DIR__ . '/../includes/subscription.php';
                if (function_exists('cancelSubscription')) {
                    cancelSubscription($db, $userId, 'Account deletion');
                }
            }

            logMsg("User ID {$userId} purged successfully.");
            $purged++;

        } catch (Exception $e) {
            logMsg("ERROR purging user ID {$account['id']}: " . $e->getMessage());
            $failed++;
        }
    }

    logMsg("Deletion processing complete. Purged: {$purged}, Failed: {$failed}.");
    exit(0);

} catch (Exception $e) {
    logMsg("FATAL ERROR: " . $e->getMessage());
    exit(1);
}

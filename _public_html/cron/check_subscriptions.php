<?php
/**
 * Cron: Check Subscriptions - Spencer's Website v7.0
 * Suspends users whose monthly/yearly subscriptions have expired (3+ days past due).
 *
 * Schedule: Daily at 2:00 AM — 0 2 * * *
 */

$db = require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../includes/payment.php';
if (!arePaymentsEnabled($db)) {
    echo date('Y-m-d H:i:s') . " Payments are disabled. Skipping subscription expiration check.\n";
    exit(0);
}

$suspended = 0;
$errors = 0;

try {
    $stmt = $db->prepare("
        SELECT u.id, u.username
        FROM users u
        WHERE u.plan_type IN ('monthly', 'yearly')
          AND u.current_period_end < DATE_SUB(NOW(), INTERVAL 3 DAY)
          AND u.is_suspended = 0
    ");
    $stmt->execute();
    $expiredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredUsers as $user) {
        try {
            if (function_exists('suspendUser')) {
                suspendUser($db, $user['id'], 'Subscription expired');
                $suspended++;
                echo date('Y-m-d H:i:s') . " Suspended user #{$user['id']} ({$user['username']})\n";
            }
        } catch (Exception $e) {
            $errors++;
            error_log("Cron sub check: failed to suspend user #{$user['id']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Cron check_subscriptions error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo date('Y-m-d H:i:s') . " Subscription check complete: {$suspended} suspended, {$errors} errors\n";

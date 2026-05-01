<?php
/**
 * Cron: Cleanup Expired Data - Spencer's Website v7.0
 * Removes expired nonces, old failed payment sessions, and old processed webhook events.
 *
 * Schedule: Daily at 3:00 AM — 0 3 * * *
 */

$db = require __DIR__ . '/bootstrap.php';

$results = [];

try {
    // Expired payment nonces
    $stmt = $db->prepare("DELETE FROM payment_nonces WHERE expires_at < NOW()");
    $stmt->execute();
    $results['nonces'] = $stmt->rowCount();

    // Old failed/expired payment sessions (7+ days old)
    $stmt = $db->prepare("DELETE FROM payment_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND status IN ('expired','failed')");
    $stmt->execute();
    $results['sessions'] = $stmt->rowCount();

    // Old processed webhook events (90+ days old)
    $stmt = $db->prepare("DELETE FROM webhook_events WHERE processing_status = 'processed' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $results['webhooks'] = $stmt->rowCount();

    echo date('Y-m-d H:i:s') . " Expired data cleanup: "
        . "{$results['nonces']} nonces, "
        . "{$results['sessions']} sessions, "
        . "{$results['webhooks']} webhooks deleted\n";
} catch (Exception $e) {
    error_log("Cron cleanup_expired error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

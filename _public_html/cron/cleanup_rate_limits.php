<?php
/**
 * Cron: Cleanup Rate Limits - Spencer's Website v7.0
 * Removes expired rows from the rate_limit_ip table.
 *
 * Schedule: Every 30 min - 30 * * * *
 */

$db = require __DIR__ . '/bootstrap.php';

try {
    // Check if rate_limit_ip table exists
    $stmt = $db->query("SHOW TABLES LIKE 'rate_limit_ip'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("DELETE FROM rate_limit_ip WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo date('Y-m-d H:i:s') . " Rate limit cleanup: {$deleted} rows deleted\n";
    } else {
        echo date('Y-m-d H:i:s') . " Rate limit table does not exist, skipping cleanup\n";
    }
} catch (Exception $e) {
    error_log("Cron cleanup_rate_limits error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

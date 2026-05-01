<?php
/**
 * Cron: Cache Garbage Collection - Spencer's Website v7.0
 * Runs Cache::gc() to clean up expired cache files.
 *
 * Schedule: Hourly — 0 * * * *
 */

$db = require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../includes/cache.php';

try {
    if (class_exists('Cache')) {
        Cache::gc();
        echo date('Y-m-d H:i:s') . " Cache GC completed\n";
    } else {
        echo date('Y-m-d H:i:s') . " Cache class not available\n";
    }
} catch (Exception $e) {
    error_log("Cron cleanup_cache error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

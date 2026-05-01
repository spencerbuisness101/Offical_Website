<?php
/**
 * Emergency OPcache + LiteSpeed Cache Reset
 * DELETE THIS FILE AFTER USE — it is a security risk.
 */

// Reset PHP OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache: RESET OK\n";
} else {
    echo "OPcache: function not available\n";
}

// Show what's cached for init.php
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(true);
    $initCached = false;
    if (isset($status['scripts'])) {
        foreach ($status['scripts'] as $path => $info) {
            if (str_contains($path, 'init.php')) {
                echo "init.php cache entry: " . json_encode($info) . "\n";
                $initCached = true;
            }
        }
    }
    if (!$initCached) {
        echo "init.php: not in OPcache\n";
    }
}

// Purge LiteSpeed Cache if available
if (isset($_SERVER['X-LSCACHE']) || true) {
    header('X-LiteSpeed-Purge: *');
    echo "LiteSpeed Cache: purge header sent\n";
}

// Show current init.php file modification time
$initPath = __DIR__ . '/includes/init.php';
if (file_exists($initPath)) {
    echo "init.php last modified: " . date('Y-m-d H:i:s', filemtime($initPath)) . "\n";
    
    // Read the first few lines to verify the file content
    $lines = file($initPath);
    // Check for the age gate code
    $content = file_get_contents($initPath);
    if (str_contains($content, '_age_verification_needed')) {
        echo "init.php: CONTAINS session flag fix (GOOD - new version)\n";
    } else if (str_contains($content, "header('Location: /auth/verify_age_existing.php')")) {
        echo "init.php: STILL HAS the redirect (BAD - old version!)\n";
    } else {
        echo "init.php: neither pattern found (UNKNOWN)\n";
    }
}

echo "\nDone. DELETE THIS FILE IMMEDIATELY.\n";

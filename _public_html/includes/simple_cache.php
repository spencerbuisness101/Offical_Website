<?php
/**
 * Simple File-Based Cache for Hostinger Shared Hosting
 * Spencer's Website v7.1
 *
 * Lightweight caching without Redis/Memcached dependency.
 * Stores serialized data in /cache/ directory.
 */

if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/../cache/');
}

/**
 * Get cached value or compute it
 *
 * @param string $key Cache key
 * @param callable $callback Function to compute value if cache miss
 * @param int $ttl Time to live in seconds (default 300 = 5 minutes)
 * @return mixed Cached or computed value
 */
function cache_get_or_set($key, $callback, $ttl = 300) {
    $cache_file = CACHE_DIR . md5($key) . '.cache';
    
    // Check if cache exists and is valid
    if (file_exists($cache_file)) {
        $data = unserialize(file_get_contents($cache_file));
        if ($data['expires'] > time()) {
            return $data['value'];
        }
        // Expired - delete it
        @unlink($cache_file);
    }
    
    // Cache miss - compute value
    $value = $callback();
    
    // Ensure cache directory exists
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    
    // Store in cache
    $cache_data = [
        'expires' => time() + $ttl,
        'value' => $value
    ];
    
    @file_put_contents($cache_file, serialize($cache_data), LOCK_EX);
    
    return $value;
}

/**
 * Get cached value only (no auto-compute)
 *
 * @param string $key Cache key
 * @return mixed|null Cached value or null if not found/expired
 */
if (!function_exists('cache_get')) {
function cache_get($key) {
    $cache_file = CACHE_DIR . md5($key) . '.cache';
    
    if (!file_exists($cache_file)) {
        return null;
    }
    
    $data = unserialize(file_get_contents($cache_file));
    if ($data['expires'] > time()) {
        return $data['value'];
    }
    
    @unlink($cache_file);
    return null;
}
}

/**
 * Set cache value
 *
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds (default 300)
 * @return bool True on success
 */
if (!function_exists('cache_set')) {
function cache_set($key, $value, $ttl = 300) {
    $cache_file = CACHE_DIR . md5($key) . '.cache';
    
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    
    $cache_data = [
        'expires' => time() + $ttl,
        'value' => $value
    ];
    
    return @file_put_contents($cache_file, serialize($cache_data), LOCK_EX) !== false;
}
}

/**
 * Delete a specific cache entry
 *
 * @param string $key Cache key
 * @return bool True if deleted or didn't exist
 */
if (!function_exists('cache_delete')) {
function cache_delete($key) {
    $cache_file = CACHE_DIR . md5($key) . '.cache';
    if (file_exists($cache_file)) {
        return @unlink($cache_file);
    }
    return true;
}
}

/**
 * Clear all cache
 *
 * @return int Number of files deleted
 */
if (!function_exists('cache_clear')) {
function cache_clear() {
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $files = glob(CACHE_DIR . '*.cache');
    foreach ($files as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }
    return $count;
}
}

/**
 * Clean up expired cache files (call periodically via cron)
 *
 * @return int Number of expired files deleted
 */
function cache_cleanup() {
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $files = glob(CACHE_DIR . '*.cache');
    $now = time();
    
    foreach ($files as $file) {
        $data = @unserialize(file_get_contents($file));
        if ($data && $data['expires'] < $now) {
            if (@unlink($file)) {
                $count++;
            }
        }
    }
    return $count;
}

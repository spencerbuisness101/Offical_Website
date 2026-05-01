<?php
/**
 * Enhanced Cache System
 * Provides filesystem-based caching with improved security and features
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

class Cache {
    private static $dir = null;
    private static $memoryCache = [];
    private static $maxMemoryItems = 100;

    /**
     * Get cache directory path
     */
    private static function getDir() {
        if (self::$dir === null) {
            self::$dir = __DIR__ . '/../cache';
            if (!is_dir(self::$dir)) {
                @mkdir(self::$dir, 0755, true);
                // Add index.php to prevent directory listing
                @file_put_contents(self::$dir . '/index.php', '<?php http_response_code(403); die("Forbidden");');
            }
        }
        return self::$dir;
    }

    /**
     * Generate safe filename from key
     */
    private static function getFilename($key) {
        return self::getDir() . '/' . md5($key) . '.cache';
    }

    /**
     * Get cached value
     */
    public static function get($key, $default = null) {
        // Check memory cache first
        if (isset(self::$memoryCache[$key])) {
            $item = self::$memoryCache[$key];
            if ($item['expires'] > time()) {
                return $item['data'];
            }
            unset(self::$memoryCache[$key]);
        }

        $file = self::getFilename($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = @unserialize($content);
        if ($data === false || !isset($data['expires']) || !isset($data['data'])) {
            @unlink($file);
            return $default;
        }

        if (time() > $data['expires']) {
            @unlink($file);
            return $default;
        }

        // Store in memory cache for faster subsequent access
        if (count(self::$memoryCache) < self::$maxMemoryItems) {
            self::$memoryCache[$key] = $data;
        }

        return $data['data'];
    }

    /**
     * Set cached value
     */
    public static function set($key, $value, $ttl = 300) {
        $data = [
            'data' => $value,
            'expires' => time() + intval($ttl),
            'created' => time()
        ];

        $file = self::getFilename($key);
        $result = @file_put_contents($file, serialize($data), LOCK_EX);

        // Store in memory cache
        if (count(self::$memoryCache) < self::$maxMemoryItems) {
            self::$memoryCache[$key] = $data;
        }

        return $result !== false;
    }

    /**
     * Delete cached value
     */
    public static function delete($key) {
        unset(self::$memoryCache[$key]);
        $file = self::getFilename($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    /**
     * Check if cache exists and is valid
     */
    public static function has($key) {
        return self::get($key) !== null;
    }

    /**
     * Get or set cache (convenience method)
     */
    public static function remember($key, $ttl, $callback) {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * Clear all cache files
     */
    public static function clear() {
        self::$memoryCache = [];
        $dir = self::getDir();
        $files = glob($dir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    /**
     * Clear expired cache files (garbage collection)
     */
    public static function gc() {
        $dir = self::getDir();
        $files = glob($dir . '/*.cache');
        $cleared = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                @unlink($file);
                $cleared++;
                continue;
            }

            $data = @unserialize($content);
            if ($data === false || !isset($data['expires']) || time() > $data['expires']) {
                @unlink($file);
                $cleared++;
            }
        }

        return $cleared;
    }
}

// Legacy function support for backward compatibility
if (!function_exists('cache_get')) {
    function cache_get($key) {
        return Cache::get($key, false);
    }
}

if (!function_exists('cache_set')) {
    function cache_set($key, $content, $ttl = 60) {
        return Cache::set($key, $content, $ttl);
    }
}

if (!function_exists('cache_delete')) {
    function cache_delete($key) {
        return Cache::delete($key);
    }
}

if (!function_exists('cache_clear')) {
    function cache_clear() {
        return Cache::clear();
    }
}
?>
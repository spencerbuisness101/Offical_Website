<?php
/**
 * Asset Helper — Spencer's Website v7.0
 *
 * Returns a versioned URL for static assets so browsers cache them
 * aggressively (1 year immutable via .htaccess) while still picking up
 * changes whenever the file is edited.
 *
 * Version strategy:
 *   1. If build/asset-manifest.json exists (built via build/minify.php),
 *      use the hash from there (for minified production assets).
 *   2. Otherwise, fall back to filemtime() for dev convenience.
 *   3. Final fallback: SITE_VERSION.
 *
 * Usage:
 *   <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
 *   <script src="<?= asset('js/tracking.js') ?>" defer></script>
 */

if (!function_exists('asset')) {
    /**
     * @param string $relativePath e.g. 'css/tokens.css' or 'js/tracking.js'
     * @param bool   $preferMinified If true and *.min.* exists, return that
     * @return string URL like '/css/tokens.css?v=abc123'
     */
    function asset(string $relativePath, bool $preferMinified = true): string {
        static $manifest = null;
        if ($manifest === null) {
            $manifestPath = dirname(__DIR__) . '/cache/asset-manifest.json';
            if (is_readable($manifestPath)) {
                $manifest = json_decode((string)@file_get_contents($manifestPath), true) ?: [];
            } else {
                $manifest = [];
            }
        }

        $path = ltrim($relativePath, '/');

        // Look for minified version if caller wants it
        if ($preferMinified) {
            $dot = strrpos($path, '.');
            if ($dot !== false) {
                $minPath = substr($path, 0, $dot) . '.min' . substr($path, $dot);
                if (is_file(dirname(__DIR__) . '/' . $minPath)) {
                    $path = $minPath;
                }
            }
        }

        // Find version (manifest entry has 'hash' key, or use filemtime fallback)
        $entry = $manifest[$relativePath] ?? null; // Look up original path in manifest
        if ($entry && isset($entry['hash'])) {
            $version = $entry['hash'];
            // Prefer minified path from manifest if available
            if (isset($entry['min'])) {
                $path = $entry['min'];
            }
        } else {
            $full = dirname(__DIR__) . '/' . $path;
            if (is_file($full)) {
                $version = substr(hash('crc32b', (string)@filemtime($full)), 0, 8);
            } else {
                $version = defined('SITE_VERSION') ? SITE_VERSION : '7.0';
            }
        }

        return '/' . $path . '?v=' . $version;
    }
}

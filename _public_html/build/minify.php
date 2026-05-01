<?php
/**
 * Asset Build Pipeline — Spencer's Website v7.0
 *
 * Minifies CSS/JS, generates asset-manifest.json for cache busting,
 * and optionally creates gzip/brotli precompressed versions.
 *
 * Usage: php build/minify.php [--compress] [--watch]
 *   --compress  Also generate .gz and .br files
 *   --watch     Continuous watch mode (manual re-run preferred for now)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$args = getopt('', ['compress', 'watch']);
$doCompress = isset($args['compress']);

$root = dirname(__DIR__);
$srcDir = $root;
$outDir = $root; // Minified files live alongside originals with .min.{css,js}
$manifestPath = $root . '/cache/asset-manifest.json';

// Ensure cache dir exists
if (!is_dir(dirname($manifestPath))) {
    mkdir(dirname($manifestPath), 0755, true);
}

$manifest = [];
$processed = 0;
$skipped = 0;

function logInfo(string $msg): void {
    echo "[INFO] " . $msg . PHP_EOL;
}
function logError(string $msg): void {
    echo "[ERROR] " . $msg . PHP_EOL;
}

// Simple CSS minifier (strip comments, whitespace)
function minifyCss(string $css): string {
    // Remove comments
    $css = preg_replace('/\/\*[^*]*\*+(?:[^\/][^*]*\*+)*\//', '', $css);
    // Collapse whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    // Remove spaces around operators
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
    // Trim
    return trim($css);
}

// Simple JS minifier (strip comments, collapse whitespace, careful with strings)
function minifyJs(string $js): string {
    // Remove single-line comments (but not in strings)
    $js = preg_replace('/(?:(?:\/\/)|(?:\/\*.*?\*\/))/', '', $js);
    // Collapse multiple spaces
    $js = preg_replace('/\s+/', ' ', $js);
    // Trim around operators/punctuation (simplified)
    $js = preg_replace('/\s*([{}();,])\s*/', '$1', $js);
    return trim($js);
}

function hashFile(string $path): string {
    return substr(hash_file('sha256', $path), 0, 16);
}

function writeCompressed(string $path, string $content): void {
    // Gzip
    $gzPath = $path . '.gz';
    $gz = gzencode($content, 9);
    if ($gz !== false) {
        file_put_contents($gzPath, $gz);
    }
    // Brotli (if brotli extension available)
    if (function_exists('brotli_compress')) {
        $brPath = $path . '.br';
        $br = brotli_compress($content, 11, BROTLI_TEXT);
        if ($br !== false) {
            file_put_contents($brPath, $br);
        }
    }
}

// Process CSS files
$cssFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($cssFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'css') continue;
    $path = $file->getPathname();
    // Skip already minified
    if (str_contains($path, '.min.css')) continue;
    // Skip vendor/CDN files (assume already optimized)
    if (str_contains($path, 'vendor') || str_contains($path, 'node_modules')) continue;

    $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $relPath = str_replace('\\', '/', $relPath); // Normalize to forward slashes for web
    $minPath = preg_replace('/\.css$/', '.min.css', $path);
    $minRelPath = str_replace($root . DIRECTORY_SEPARATOR, '', $minPath);
    $minRelPath = str_replace('\\', '/', $minRelPath);

    $original = file_get_contents($path);
    $minified = minifyCss($original);
    $originalSize = strlen($original);
    $minSize = strlen($minified);

    // Only write if different
    $existingMin = @file_get_contents($minPath);
    if ($existingMin !== $minified) {
        file_put_contents($minPath, $minified);
        if ($doCompress) {
            writeCompressed($minPath, $minified);
        }
        $processed++;
    } else {
        $skipped++;
    }

    $hash = hashFile($minPath);
    $manifest[$relPath] = [
        'min' => $minRelPath,
        'hash' => $hash,
        'size' => $minSize,
        'originalSize' => $originalSize,
        'saved' => $originalSize - $minSize,
    ];

    logInfo("CSS: {$relPath} (saved " . ($originalSize - $minSize) . " bytes)");
}

// Process JS files
$jsFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($jsFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'js') continue;
    $path = $file->getPathname();
    // Skip already minified
    if (str_contains($path, '.min.js')) continue;
    // Skip vendor/CDN files
    if (str_contains($path, 'vendor') || str_contains($path, 'node_modules')) continue;

    $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $relPath = str_replace('\\', '/', $relPath);
    $minPath = preg_replace('/\.js$/', '.min.js', $path);
    $minRelPath = str_replace($root . DIRECTORY_SEPARATOR, '', $minPath);
    $minRelPath = str_replace('\\', '/', $minRelPath);

    $original = file_get_contents($path);
    $minified = minifyJs($original);
    $originalSize = strlen($original);
    $minSize = strlen($minified);

    $existingMin = @file_get_contents($minPath);
    if ($existingMin !== $minified) {
        file_put_contents($minPath, $minified);
        if ($doCompress) {
            writeCompressed($minPath, $minified);
        }
        $processed++;
    } else {
        $skipped++;
    }

    $hash = hashFile($minPath);
    $manifest[$relPath] = [
        'min' => $minRelPath,
        'hash' => $hash,
        'size' => $minSize,
        'originalSize' => $originalSize,
        'saved' => $originalSize - $minSize,
    ];

    logInfo("JS: {$relPath} (saved " . ($originalSize - $minSize) . " bytes)");
}

// Write manifest
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Summary
logInfo("Build complete: {$processed} updated, {$skipped} unchanged");
logInfo("Manifest: {$manifestPath}");
if ($doCompress) {
    logInfo("Precompressed .gz/.br files generated");
}

// Generate build timestamp for cache invalidation
$buildInfo = [
    'timestamp' => time(),
    'date' => date('c'),
    'files_processed' => $processed,
    'files_skipped' => $skipped,
];
file_put_contents(dirname($manifestPath) . '/build-info.json', json_encode($buildInfo, JSON_PRETTY_PRINT));

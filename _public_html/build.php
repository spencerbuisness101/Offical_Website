#!/usr/bin/env php
<?php
/**
 * Build Script — Spencer's Website v7.0
 *
 * One-command build process for deployment preparation.
 * Runs migrations, minifies assets, and performs health checks.
 *
 * Usage: php build.php [options]
 *   --dry-run    Show what would happen without making changes
 *   --skip-minify Skip the minification step (faster builds during dev)
 *   --verbose    Show detailed output
 */

define('APP_RUNNING', true);
define('BUILD_START', microtime(true));

$args = array_slice($argv ?? [], 1);
$dryRun = in_array('--dry-run', $args);
$skipMinify = in_array('--skip-minify', $args);
$verbose = in_array('--verbose', $args) || in_array('-v', $args);

$errors = [];
$warnings = [];
$success = [];

function logInfo(string $msg): void {
    echo "[INFO] {$msg}\n";
}

function logError(string $msg): void {
    echo "[ERROR] {$msg}\n";
}

function logSuccess(string $msg): void {
    echo "[OK] {$msg}\n";
}

function logWarning(string $msg): void {
    echo "[WARN] {$msg}\n";
}

// ============================================================================
// Header
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          Spencer's Website — Build Process v7.0                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    logWarning("DRY RUN MODE — No changes will be made");
    echo "\n";
}

// ============================================================================
// Step 1: Environment Check
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 1: Environment Check\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Check PHP version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
if ($phpOk) {
    logSuccess("PHP {$phpVersion}");
} else {
    logError("PHP {$phpVersion} (requires 8.1+)");
    $errors[] = "PHP version too old";
}

// Check required extensions
$required = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring'];
$missing = [];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (empty($missing)) {
    logSuccess("Required extensions loaded");
} else {
    logError("Missing extensions: " . implode(', ', $missing));
    $errors[] = "Missing PHP extensions";
}

// Check critical directories
$dirs = ['cache', 'logs', 'uploads'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if ($dryRun) {
            logInfo("Would create directory: {$dir}/");
        } else {
            if (@mkdir($path, 0755, true)) {
                logSuccess("Created directory: {$dir}/");
            } else {
                logError("Failed to create: {$dir}/");
                $errors[] = "Cannot create {$dir} directory";
            }
        }
    } else {
        if ($verbose) logInfo("Directory exists: {$dir}/");
    }
}

// ============================================================================
// Step 2: Database Migrations
// ============================================================================
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 2: Database Migrations\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($dryRun) {
    logInfo("Would run: php migrations/run.php --dry-run");
    // Show what migrations exist
    $migrations = glob(__DIR__ . '/migrations/*.sql');
    logInfo("Found " . count($migrations) . " migration files");
    if ($verbose) {
        foreach (array_slice($migrations, -5) as $m) {
            logInfo("  - " . basename($m));
        }
    }
} else {
    logInfo("Running database migrations...");
    
    $output = [];
    $returnCode = 0;
    $phpBinary = PHP_BINARY ?: 'php';
    exec($phpBinary . ' ' . escapeshellarg(__DIR__ . '/migrations/run.php') . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        logSuccess("Migrations completed");
        if ($verbose) {
            foreach ($output as $line) {
                if (trim($line)) echo "  {$line}\n";
            }
        }
    } else {
        logError("Migration failed (code {$returnCode})");
        foreach ($output as $line) {
            if (trim($line)) logError("  " . $line);
        }
        $errors[] = "Database migrations failed";
    }
}

// ============================================================================
// Step 3: Asset Minification
// ============================================================================
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 3: Asset Minification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($skipMinify) {
    logWarning("Skipping minification (--skip-minify)");
} elseif ($dryRun) {
    logInfo("Would run: php build/minify.php");
} else {
    logInfo("Minifying CSS/JS assets...");
    
    $output = [];
    $returnCode = 0;
    $phpBinary = PHP_BINARY ?: 'php';
    exec($phpBinary . ' ' . escapeshellarg(__DIR__ . '/build/minify.php') . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        logSuccess("Assets minified");
        if ($verbose) {
            foreach ($output as $line) {
                if (trim($line)) echo "  {$line}\n";
            }
        }
        
        // Check if manifest was created
        if (file_exists(__DIR__ . '/cache/asset-manifest.json')) {
            $manifest = json_decode(file_get_contents(__DIR__ . '/cache/asset-manifest.json'), true);
            $count = count($manifest);
            logSuccess("Generated asset-manifest.json ({$count} entries)");
        }
    } else {
        logError("Minification failed (code {$returnCode})");
        foreach ($output as $line) {
            if (trim($line)) logError("  " . $line);
        }
        $warnings[] = "Asset minification had errors";
    }
}

// ============================================================================
// Step 4: Health Check
// ============================================================================
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 4: Health Check\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($dryRun) {
    logInfo("Would check: php health.php (locally)");
} else {
    // Run health check by including it (avoiding HTTP request)
    logInfo("Running health checks...");
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        logSuccess("PHP version OK (" . PHP_VERSION . ")");
    } else {
        logWarning("PHP version (" . PHP_VERSION . ") — recommend 8.1+");
    }
    
    // Check extensions
    $exts = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
    $allOk = true;
    foreach ($exts as $ext) {
        if (!extension_loaded($ext)) {
            logError("Extension missing: {$ext}");
            $allOk = false;
        }
    }
    if ($allOk) {
        logSuccess("All required extensions present");
    }
    
    // Check disk space
    $free = disk_free_space(__DIR__);
    $freeGB = round($free / 1024 / 1024 / 1024, 2);
    if ($freeGB < 1) {
        logError("Low disk space: {$freeGB} GB free");
        $errors[] = "Insufficient disk space";
    } elseif ($freeGB < 5) {
        logWarning("Disk space: {$freeGB} GB free");
    } else {
        logSuccess("Disk space: {$freeGB} GB free");
    }
    
    // Check critical files exist
    $critical = [
        'css/tokens.css',
        'includes/head.php',
        'includes/asset.php',
        'includes/db.php',
        'migrations/run.php',
        'health.php'
    ];
    $missingFiles = [];
    foreach ($critical as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missingFiles[] = $file;
        }
    }
    if (empty($missingFiles)) {
        logSuccess("All critical files present");
    } else {
        logError("Missing files: " . implode(', ', $missingFiles));
        $errors[] = "Critical files missing";
    }
}

// ============================================================================
// Step 5: Syntax Validation
// ============================================================================
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 5: Syntax Validation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$keyFiles = [
    'migrations/run.php',
    'health.php',
    'includes/db.php',
    'includes/logger.php',
    'includes/asset.php',
    'includes/identity_bar_v2.php',
    'auth/login.php',
    'main.php'
];

$syntaxErrors = 0;
foreach ($keyFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) continue;
    
    $output = [];
    $returnCode = 0;
    $phpBinary = PHP_BINARY ?: 'php';
    exec($phpBinary . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $returnCode);
    
    if ($returnCode !== 0) {
        logError("Syntax error in {$file}");
        foreach ($output as $line) {
            if (trim($line) && !str_contains($line, 'No syntax errors')) {
                logError("  " . $line);
            }
        }
        $syntaxErrors++;
    }
}

if ($syntaxErrors === 0) {
    logSuccess("All files pass PHP lint");
} else {
    $errors[] = "{$syntaxErrors} files have syntax errors";
}

// ============================================================================
// Summary
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        BUILD SUMMARY                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$elapsed = round(microtime(true) - BUILD_START, 2);

if (empty($errors)) {
    echo "✅ BUILD SUCCESSFUL ({$elapsed}s)\n\n";
    
    if (!empty($warnings)) {
        echo "Warnings:\n";
        foreach ($warnings as $w) {
            echo "  ⚠️  {$w}\n";
        }
        echo "\n";
    }
    
    if ($dryRun) {
        echo "This was a dry run. To apply changes, run without --dry-run\n";
    } else {
        echo "Ready for deployment!\n";
        echo "\nNext steps:\n";
        echo "  1. Test login flow on staging\n";
        echo "  2. Run: php tests/run.php\n";
        echo "  3. Check /health.php endpoint\n";
    }
    
    exit(0);
} else {
    echo "❌ BUILD FAILED ({$elapsed}s)\n\n";
    echo "Errors:\n";
    foreach ($errors as $e) {
        echo "  ❌ {$e}\n";
    }
    
    if (!empty($warnings)) {
        echo "\nWarnings:\n";
        foreach ($warnings as $w) {
            echo "  ⚠️  {$w}\n";
        }
    }
    
    exit(1);
}

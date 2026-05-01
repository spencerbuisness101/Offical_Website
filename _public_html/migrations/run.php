<?php
/**
 * Migration Runner — Spencer's Website v7.0
 *
 * CLI-only migration runner. Applies SQL files from /migrations/ in
 * alphabetical order, tracking applied migrations in the schema_migrations
 * table. Idempotent: already-applied migrations are skipped.
 *
 * Usage:
 *   php migrations/run.php              # apply pending migrations
 *   php migrations/run.php --dry-run    # show what would run, no changes
 *   php migrations/run.php --status     # list applied + pending
 *
 * Security: Refuses to run from web requests.
 */

declare(strict_types=1);

// ============================================================================
// Web-request guard (defense in depth)
// ============================================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden — this script is CLI-only.\n";
    echo "Run from the command line: php migrations/run.php\n";
    exit;
}

// ============================================================================
// Bootstrap
// ============================================================================
define('APP_RUNNING', true);
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/db.php';

// ============================================================================
// Args
// ============================================================================
$args    = array_slice($argv ?? [], 1);
$dryRun  = in_array('--dry-run', $args, true);
$status  = in_array('--status',  $args, true);
$force   = in_array('--force',   $args, true); // allow re-applying a specific file

// ============================================================================
// Helpers
// ============================================================================
function info(string $msg):    void { echo "\033[36m[INFO]\033[0m  $msg\n"; }
function ok(string $msg):      void { echo "\033[32m[OK]\033[0m    $msg\n"; }
function warn(string $msg):    void { echo "\033[33m[WARN]\033[0m  $msg\n"; }
function err(string $msg):     void { echo "\033[31m[ERROR]\033[0m $msg\n"; }
function line():               void { echo str_repeat('-', 70) . "\n"; }

function ensureMigrationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            filename    VARCHAR(255) NOT NULL UNIQUE,
            checksum    VARCHAR(64)  NOT NULL,
            applied_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            duration_ms INT          DEFAULT 0,
            INDEX idx_applied (applied_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function listMigrationFiles(string $dir): array {
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);
    return $files;
}

function splitStatements(string $sql): array {
    // Splits SQL into individual statements, handling DELIMITER changes
    // for stored procedures and triggers.
    $lines  = preg_split('/\r\n|\n|\r/', $sql);
    $buffer = '';
    $out    = [];
    $delim  = ';';

    foreach ($lines as $line) {
        // Detect DELIMITER change
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
            // Flush any buffered content with the old delimiter first
            $trimBuf = trim($buffer);
            if ($trimBuf !== '') {
                $out[] = $trimBuf;
            }
            $buffer = '';
            $delim = $m[1];
            continue;
        }

        // Reset delimiter to default when encountering a known reset pattern
        if (str_starts_with(trim($line), 'DELIMITER ;') || trim($line) === 'DELIMITER ;') {
            $trimBuf = trim($buffer);
            if ($trimBuf !== '') {
                $out[] = $trimBuf;
            }
            $buffer = '';
            $delim = ';';
            continue;
        }

        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) continue;
        $buffer .= $line . "\n";

        // Check if the trimmed line ends with the current delimiter
        if (str_ends_with($trim, $delim)) {
            // Remove the delimiter from the end before storing
            $stmt = trim(substr(trim($buffer), 0, -strlen($delim)));
            if ($stmt !== '') {
                $out[] = $stmt . ';';
            }
            $buffer = '';
        }
    }

    // Flush remaining buffer
    $trimBuf = trim($buffer);
    if ($trimBuf !== '') {
        $out[] = $trimBuf;
    }

    return $out;
}

// Errors that are SAFE to ignore on re-runs (schema already matches).
function isIgnorableError(string $msg): bool {
    $ignorable = [
        'Duplicate column',
        'Duplicate key name',
        "already exists",
        'check that column/key exists',
    ];
    foreach ($ignorable as $needle) {
        if (stripos($msg, $needle) !== false) return true;
    }
    return false;
}

// ============================================================================
// Main
// ============================================================================
try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    ensureMigrationsTable($pdo);

    $applied = [];
    $stmt = $pdo->query('SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $applied[$row['filename']] = $row['applied_at'];
    }

    $files = listMigrationFiles(__DIR__);
    // Seed prior ad-hoc migrations that were applied via the old login.php
    // auto-migration block so we don't try to re-run them.
    $bootstrap = ['007_login_auto_migration_consolidation.sql', '008_drop_legacy_tables.sql'];
    foreach ($bootstrap as $bs) {
        if (!isset($applied[$bs]) && file_exists(__DIR__ . "/../cache/.login_migration_done")) {
            // Mark as applied without running (same effect as old auto-migration block)
            $p = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename, checksum, duration_ms) VALUES (?, ?, 0)');
            $p->execute([$bs, hash_file('sha256', __DIR__ . '/' . $bs) ?: '']);
            $applied[$bs] = 'auto-seeded';
            info("Seeded legacy migration as already-applied: $bs");
        }
    }

    // --- Status mode ---
    if ($status) {
        line();
        info("Migration status for " . gethostname());
        line();
        echo sprintf("%-50s %-10s %s\n", 'FILENAME', 'STATUS', 'APPLIED_AT');
        line();
        foreach ($files as $f) {
            $name = basename($f);
            if (isset($applied[$name])) {
                echo sprintf("%-50s \033[32m%-10s\033[0m %s\n", $name, 'APPLIED', $applied[$name]);
            } else {
                echo sprintf("%-50s \033[33m%-10s\033[0m %s\n", $name, 'PENDING', '-');
            }
        }
        line();
        exit(0);
    }

    // --- Dry-run or apply ---
    $pending = [];
    foreach ($files as $f) {
        $name = basename($f);
        if (!isset($applied[$name])) $pending[] = $f;
    }

    if (empty($pending)) {
        ok("No pending migrations. Schema is up to date.");
        exit(0);
    }

    info(count($pending) . " pending migration(s):");
    foreach ($pending as $f) echo "  - " . basename($f) . "\n";

    if ($dryRun) {
        warn("DRY RUN — no changes will be made.");
        exit(0);
    }

    // --- Apply ---
    foreach ($pending as $file) {
        $name     = basename($file);
        $checksum = hash_file('sha256', $file) ?: '';
        $sql      = (string)file_get_contents($file);
        $stmts    = splitStatements($sql);

        line();
        info("Applying $name (" . count($stmts) . " statement" . (count($stmts) === 1 ? '' : 's') . ")");
        $start = microtime(true);
        $errorsIgnored = 0;

        foreach ($stmts as $i => $s) {
            try {
                $pdo->exec($s);
            } catch (PDOException $e) {
                if (isIgnorableError($e->getMessage())) {
                    $errorsIgnored++;
                    continue;
                }
                err("  Statement " . ($i + 1) . " failed: " . $e->getMessage());
                echo "  SQL: " . substr($s, 0, 150) . (strlen($s) > 150 ? '...' : '') . "\n";
                exit(1);
            }
        }

        $duration = (int)round((microtime(true) - $start) * 1000);
        $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum, duration_ms) VALUES (?, ?, ?)');
        $ins->execute([$name, $checksum, $duration]);

        ok("$name applied in {$duration}ms" . ($errorsIgnored ? " ({$errorsIgnored} ignorable errors)" : ''));
    }

    line();
    ok("All migrations applied successfully.");
    exit(0);

} catch (Throwable $e) {
    err($e->getMessage());
    err("at " . $e->getFile() . ":" . $e->getLine());
    exit(2);
}

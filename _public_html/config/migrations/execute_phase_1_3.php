<?php
// Phase 1.3 Beta-Tester Removal Migration Executor
// This script safely executes the beta-tester removal migration

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/db.php';

// Only allow admins to run this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

$pdo = db();

// Check if migration has already been run
$checkSql = "SELECT COUNT(*) as count FROM system_changelog WHERE version = '7.0' AND section = 'Phase 1.3'";
$result = $pdo->query($checkSql);
$row = $result->fetch(PDO::FETCH_ASSOC);

if ($row['count'] > 0) {
    echo "Phase 1.3 migration has already been applied.\n";
    exit;
}

echo "Starting Phase 1.3: Beta-Tester Removal migration...\n";

// Load and execute the migration SQL
$migrationFile = __DIR__ . '/phase_1_3_beta_tester_removal.sql';
if (!file_exists($migrationFile)) {
    die("Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

// Execute the migration
try {
    $pdo->exec($sql);
    echo "✓ Successfully migrated beta-tester users to 'user' role\n";
    echo "✓ Dropped beta_tester_slots table\n";
    echo "✓ Dropped beta_submissions table\n";
    echo "✓ Migration logged in system_changelog\n";
    echo "\nPhase 1.3 migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

// Purge cache after migration
if (function_exists('purgeCache')) {
    purgeCache(['public', 'admin', 'user']);
    echo "✓ Cache purged\n";
}
?>

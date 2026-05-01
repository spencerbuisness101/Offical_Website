<?php
/**
 * Test Runner — Spencer's Website v7.0
 *
 * Runs all tests without requiring full PHPUnit installation.
 * Compatible with PHPUnit syntax for future migration.
 *
 * Usage: php tests/run.php [TestClassName]
 *   php tests/run.php                    # Run all tests
 *   php tests/run.php HealthEndpointTest # Run specific test class
 */

require_once __DIR__ . '/bootstrap.php';

use Tests\Feature\HealthEndpointTest;
use Tests\Unit\HelperFunctionsTest;
use Tests\Unit\CsrfTest;
use Tests\Unit\LoggerTest;

// Available test classes
$testClasses = [
    HealthEndpointTest::class,
    HelperFunctionsTest::class,
    CsrfTest::class,
    LoggerTest::class,
];

// Check for specific test class argument
$runSpecific = $argv[1] ?? null;

$ranTests = false;

foreach ($testClasses as $class) {
    // Skip if specific test requested and doesn't match
    if ($runSpecific && !str_contains($class, $runSpecific)) {
        continue;
    }
    
    if (class_exists($class)) {
        $class::run();
        $ranTests = true;
    }
}

if (!$ranTests) {
    if ($runSpecific) {
        echo "Test class '{$runSpecific}' not found.\n";
        echo "Available tests:\n";
        foreach ($testClasses as $class) {
            echo "  - " . basename(str_replace('\\', '/', $class)) . "\n";
        }
    } else {
        echo "No tests found.\n";
    }
    exit(1);
}

// Print summary
Tests\TestCase::printSummary();

<?php
/**
 * PHPUnit Bootstrap — Spencer's Website v7.0
 *
 * Initializes test environment with:
 * - Error reporting to E_ALL
 * - Autoloader for test classes
 * - Mock database configuration
 * - Test helper functions
 */

define('APP_RUNNING', true);
define('APP_ENV', 'testing');
define('SITE_VERSION', '7.0-test');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Base path
define('BASE_PATH', dirname(__DIR__));
define('TESTS_PATH', __DIR__);

// Autoloader for test classes
spl_autoload_register(function ($class) {
    $prefixes = [
        'Tests\\' => TESTS_PATH . '/',
    ];
    
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Test helper functions
require_once __DIR__ . '/TestCase.php';

/**
 * Create a mock PDO connection for testing
 */
function createMockPdo(): PDO {
    return new PDO('sqlite::memory:');
}

/**
 * Generate a test CSRF token
 */
function generateTestCsrfToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Assert that a response is valid JSON
 */
function assertValidJson(string $response): array {
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg());
    }
    return $data;
}

echo "PHPUnit Bootstrap loaded. Testing environment initialized.\n";

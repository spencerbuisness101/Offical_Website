<?php
/**
 * Base Test Case — Spencer's Website v7.0
 *
 * Minimal PHPUnit-style base class for testing without full PHPUnit installation.
 * Can be replaced with actual PHPUnit\Framework\TestCase when PHPUnit is installed.
 */

namespace Tests;

class TestCase {
    protected static $testCount = 0;
    protected static $passCount = 0;
    protected static $failCount = 0;
    protected static $currentTest = '';
    
    /**
     * Run all test methods in this class
     */
    public static function run(): void {
        $class = new static();
        $methods = get_class_methods($class);
        
        echo "\n" . get_called_class() . "\n";
        echo str_repeat("=", 50) . "\n";
        
        // Run setUpBeforeClass if exists
        if (method_exists($class, 'setUpBeforeClass')) {
            $class->setUpBeforeClass();
        }
        
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                self::$currentTest = $method;
                self::$testCount++;
                
                // Run setUp if exists
                if (method_exists($class, 'setUp')) {
                    $class->setUp();
                }
                
                try {
                    $class->$method();
                    self::$passCount++;
                    echo "  ✓ {$method}\n";
                } catch (\Exception $e) {
                    self::$failCount++;
                    echo "  ✗ {$method}: {$e->getMessage()}\n";
                }
                
                // Run tearDown if exists
                if (method_exists($class, 'tearDown')) {
                    $class->tearDown();
                }
            }
        }
        
        // Run tearDownAfterClass if exists
        if (method_exists($class, 'tearDownAfterClass')) {
            $class->tearDownAfterClass();
        }
    }
    
    /**
     * Assert that a condition is true
     */
    protected function assertTrue($condition, string $message = ''): void {
        if (!$condition) {
            throw new \Exception($message ?: 'Failed asserting that condition is true');
        }
    }
    
    /**
     * Assert that a condition is false
     */
    protected function assertFalse($condition, string $message = ''): void {
        if ($condition) {
            throw new \Exception($message ?: 'Failed asserting that condition is false');
        }
    }
    
    /**
     * Assert that two values are equal
     */
    protected function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \Exception($message ?: "Failed asserting that {$actual} equals expected {$expected}");
        }
    }
    
    /**
     * Assert that two values are not equal
     */
    protected function assertNotEquals($expected, $actual, string $message = ''): void {
        if ($expected === $actual) {
            throw new \Exception($message ?: "Failed asserting that {$actual} does not equal {$expected}");
        }
    }
    
    /**
     * Assert that a value is not null
     */
    protected function assertNotNull($value, string $message = ''): void {
        if ($value === null) {
            throw new \Exception($message ?: 'Failed asserting that value is not null');
        }
    }
    
    /**
     * Assert that a string contains a substring
     */
    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception($message ?: "Failed asserting that string contains '{$needle}'");
        }
    }
    
    /**
     * Assert that a string does NOT contain a substring
     */
    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) !== false) {
            throw new \Exception($message ?: "Failed asserting that string does not contain '{$needle}'");
        }
    }
    
    /**
     * Assert that an array has a key
     */
    protected function assertArrayHasKey(string $key, ?array $array, string $message = ''): void {
        if (!is_array($array)) {
            throw new \Exception($message ?: "Failed asserting that value is an array (got " . gettype($array) . ")");
        }
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array has key '{$key}'");
        }
    }
    
    /**
     * Assert that HTTP response code equals expected
     */
    protected function assertResponseCode(int $expected): void {
        $actual = http_response_code();
        if ($actual !== $expected) {
            throw new \Exception("Failed asserting that response code {$actual} equals {$expected}");
        }
    }
    
    /**
     * Make an HTTP GET request and return response
     */
    protected function get(string $url): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $body
        ];
    }
    
    /**
     * Make an HTTP POST request
     */
    protected function post(string $url, array $data): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize)
        ];
    }
    
    /**
     * Print test summary
     */
    public static function printSummary(): void {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Tests: " . self::$testCount . " | ";
        echo "Passed: " . self::$passCount . " | ";
        echo "Failed: " . self::$failCount . "\n";
        
        if (self::$failCount === 0) {
            echo "✓ All tests passed!\n";
        } else {
            echo "✗ Some tests failed.\n";
            exit(1);
        }
    }
}

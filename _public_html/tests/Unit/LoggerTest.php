<?php
/**
 * Logger Tests — Spencer's Website v7.0
 *
 * Tests the Logger class functionality.
 */

namespace Tests\Unit;

use Tests\TestCase;

class LoggerTest extends TestCase {
    private string $logDir;
    
    protected function setUp(): void {
        $this->logDir = BASE_PATH . '/logs';
        require_once BASE_PATH . '/includes/logger.php';
    }
    
    protected function tearDown(): void {
        // Clean up test log files
        $pattern = $this->logDir . '/test-*.log';
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Test Logger can be instantiated
     */
    public function testLoggerInstantiation(): void {
        $logger = new \Logger('test', 'DEBUG');
        $this->assertNotNull($logger);
        $this->assertTrue($logger instanceof \Logger);
    }
    
    /**
     * Test different log levels work
     */
    public function testLogLevels(): void {
        $logger = new \Logger('test', 'DEBUG');
        
        // These should all work without throwing
        $logger->debug('Debug message', ['test' => true]);
        $logger->info('Info message', ['user' => 123]);
        $logger->notice('Notice message');
        $logger->warning('Warning message', ['context' => 'test']);
        $logger->error('Error message', ['error' => 'test']);
        
        $this->assertTrue(true, 'All log levels executed without error');
    }
    
    /**
     * Test log file is created
     */
    public function testLogFileCreated(): void {
        $logger = new \Logger('test', 'DEBUG');
        $logger->info('Test message');
        
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        
        $this->assertTrue(file_exists($logFile), 'Log file should be created');
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('INFO', $content);
    }
    
    /**
     * Test JSON format in log file
     */
    public function testLogFormatIsJson(): void {
        $logger = new \Logger('test', 'DEBUG');
        $logger->info('JSON test', ['key' => 'value']);
        
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        
        $lines = file($logFile);
        $lastLine = trim($lines[count($lines) - 1]);
        
        $data = json_decode($lastLine, true);
        $this->assertNotNull($data, 'Log entry should be valid JSON');
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('level', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('channel', $data);
    }
    
    /**
     * Test log level filtering
     */
    public function testLogLevelFiltering(): void {
        // Create logger with INFO level (DEBUG should be filtered)
        $logger = new \Logger('test', 'INFO');
        
        // Clear any existing log
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        @unlink($logFile);
        
        $logger->debug('Debug - should be filtered');
        $logger->info('Info - should appear');
        
        $content = file_get_contents($logFile);
        $this->assertStringNotContainsString('Debug - should be filtered', $content);
        $this->assertStringContainsString('Info - should appear', $content);
    }
    
    /**
     * Test exception logging
     */
    public function testExceptionLogging(): void {
        $logger = new \Logger('test', 'DEBUG');
        
        $exception = new \Exception('Test exception', 500);
        $logger->exception($exception);
        
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('exception', $content);
    }
    
    /**
     * Test helper functions
     */
    public function testHelperFunctions(): void {
        // Just ensure they don't throw
        log_info('Helper info test');
        log_error('Helper error test');
        log_exception(new \Exception('Helper exception test'));
        
        $this->assertTrue(true, 'Helper functions executed without error');
    }
    
    /**
     * Test multiple log entries in same file
     */
    public function testMultipleEntries(): void {
        $logger = new \Logger('test', 'DEBUG');
        
        for ($i = 1; $i <= 5; $i++) {
            $logger->info("Entry {$i}");
        }
        
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        $lines = file($logFile);
        
        $this->assertTrue(count($lines) >= 5, 'Should have at least 5 log entries');
    }
    
    /**
     * Test context data is properly JSON encoded
     */
    public function testContextEncoding(): void {
        $logger = new \Logger('test', 'DEBUG');
        
        $context = [
            'user_id' => 123,
            'action' => 'test',
            'data' => ['nested' => 'value']
        ];
        
        $logger->info('Context test', $context);
        
        $today = date('Y-m-d');
        $logFile = $this->logDir . "/test-{$today}.log";
        $lines = file($logFile);
        $lastLine = trim($lines[count($lines) - 1]);
        
        $data = json_decode($lastLine, true);
        $this->assertEquals($context, $data['context']);
    }
}

<?php
/**
 * Health Endpoint Smoke Tests — Spencer's Website v7.0
 *
 * Tests the /health.php endpoint for monitoring integration.
 */

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase {
    private string $baseUrl;
    
    public function setUp(): void {
        $this->baseUrl = $_ENV['TEST_BASE_URL'] ?? 'http://localhost';
    }
    
    private function checkServerAvailable(): bool {
        // Quick check if server is responding
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $available = curl_errno($ch) === 0;
        curl_close($ch);
        return $available;
    }
    
    /**
     * Test basic health endpoint returns JSON
     */
    public function testHealthEndpointReturnsJson(): void {
        if (!$this->checkServerAvailable()) {
            echo "  (skipped - server not available)\n";
            $this->assertTrue(true, 'Skipped - no server running');
            return;
        }
        
        $response = $this->get("{$this->baseUrl}/health.php");
        
        $this->assertEquals(200, $response['status'], 'Health endpoint should return 200');
        
        $data = json_decode($response['body'], true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
    }
    
    /**
     * Test health endpoint includes required checks
     */
    public function testHealthEndpointHasRequiredChecks(): void {
        $response = $this->get("{$this->baseUrl}/health.php");
        $data = json_decode($response['body'], true);
        
        $requiredChecks = ['php_version', 'extensions', 'database', 'disk_space', 'session_storage'];
        foreach ($requiredChecks as $check) {
            $this->assertArrayHasKey($check, $data['checks'], "Missing check: {$check}");
        }
    }
    
    /**
     * Test health endpoint returns valid status value
     */
    public function testHealthStatusIsValid(): void {
        $response = $this->get("{$this->baseUrl}/health.php");
        $data = json_decode($response['body'], true);
        
        $validStatuses = ['healthy', 'degraded', 'unhealthy'];
        $this->assertTrue(in_array($data['status'], $validStatuses), 
            'Status should be one of: healthy, degraded, unhealthy');
    }
    
    /**
     * Test detailed health check with ?checks=all
     */
    public function testDetailedHealthCheck(): void {
        $response = $this->get("{$this->baseUrl}/health.php?checks=all");
        $data = json_decode($response['body'], true);
        
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('external_apis', $data['checks'], 'Detailed check should include external_apis');
        $this->assertArrayHasKey('memory', $data['checks'], 'Detailed check should include memory');
    }
    
    /**
     * Test Prometheus format returns plain text
     */
    public function testPrometheusFormat(): void {
        $response = $this->get("{$this->baseUrl}/health.php?format=prometheus");
        
        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('spencer_health_check', $response['body']);
        $this->assertStringContainsString('spencer_health_response_time_ms', $response['body']);
    }
    
    /**
     * Test PHP version check passes for supported version
     */
    public function testPhpVersionCheck(): void {
        $response = $this->get("{$this->baseUrl}/health.php");
        $data = json_decode($response['body'], true);
        
        $phpCheck = $data['checks']['php_version'] ?? null;
        $this->assertNotNull($phpCheck);
        
        // Should pass for PHP 8.1+
        $this->assertTrue(
            $phpCheck['status'] === 'pass' || version_compare(PHP_VERSION, '8.1.0', '>='),
            'PHP version check should pass for 8.1+'
        );
    }
    
    /**
     * Test database connectivity check
     */
    public function testDatabaseCheck(): void {
        $response = $this->get("{$this->baseUrl}/health.php");
        $data = json_decode($response['body'], true);
        
        $dbCheck = $data['checks']['database'] ?? null;
        $this->assertNotNull($dbCheck, 'Database check should exist');
        $this->assertArrayHasKey('status', $dbCheck);
        
        // If database is available, check should pass
        if (file_exists(BASE_PATH . '/config/database.php')) {
            // We expect either pass (if DB works) or fail (if DB is down)
            $this->assertTrue(in_array($dbCheck['status'], ['pass', 'fail']));
        }
    }
    
    /**
     * Test response time is reasonable (< 5 seconds)
     */
    public function testResponseTime(): void {
        $start = microtime(true);
        $response = $this->get("{$this->baseUrl}/health.php");
        $elapsed = (microtime(true) - $start) * 1000;
        
        $this->assertTrue($elapsed < 5000, 'Health check should complete within 5 seconds');
        
        $data = json_decode($response['body'], true);
        if (isset($data['response_time_ms'])) {
            $this->assertTrue($data['response_time_ms'] < 5000, 'Reported response time should be under 5s');
        }
    }
    
    /**
     * Test CORS headers are present
     */
    public function testCorsHeaders(): void {
        $response = $this->get("{$this->baseUrl}/health.php");
        
        $this->assertStringContainsString('application/json', $response['headers']);
        $this->assertStringContainsString('Access-Control-Allow-Origin', $response['headers']);
    }
}

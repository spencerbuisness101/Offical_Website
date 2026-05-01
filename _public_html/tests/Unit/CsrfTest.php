<?php
/**
 * CSRF Protection Tests — Spencer's Website v7.0
 *
 * Tests CSRF token generation, validation, and rotation.
 */

namespace Tests\Unit;

use Tests\TestCase;

class CsrfTest extends TestCase {
    
    protected function setUp(): void {
        // Ensure clean session state
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        @session_start();
        
        require_once BASE_PATH . '/includes/csrf.php';
    }
    
    /**
     * Test token has correct format (64 hex characters)
     */
    public function testTokenFormat(): void {
        $token = generateCsrfToken();
        
        $this->assertEquals(64, strlen($token));
        $this->assertTrue(ctype_xdigit($token), 'Token should be hexadecimal');
        $this->assertTrue(!empty($token), 'Token should not be empty');
    }
    
    /**
     * Test token is stored in session
     */
    public function testTokenStoredInSession(): void {
        $token = generateCsrfToken();
        
        $this->assertTrue(isset($_SESSION['csrf_token']), 'Token should be stored in session');
        $this->assertEquals($token, $_SESSION['csrf_token'], 'Session token should match generated token');
    }
    
    /**
     * Test token validation succeeds for correct token
     */
    public function testValidTokenPasses(): void {
        $token = generateCsrfToken();
        
        $this->assertTrue(validateCsrfToken($token), 'Valid token should validate');
    }
    
    /**
     * Test token validation fails for incorrect token
     */
    public function testInvalidTokenFails(): void {
        generateCsrfToken(); // Generate a valid token first
        
        $this->assertFalse(validateCsrfToken('invalid'), 'Invalid token should not validate');
        $this->assertFalse(validateCsrfToken('1234567890abcdef'), 'Short token should not validate');
        $this->assertFalse(validateCsrfToken(str_repeat('gg', 32)), 'Non-hex token should not validate');
    }
    
    /**
     * Test token validation fails for empty/null
     */
    public function testEmptyTokenFails(): void {
        $this->assertFalse(validateCsrfToken(''), 'Empty string should not validate');
    }
    
    /**
     * Test token rotation creates new token
     */
    public function testTokenRotation(): void {
        $token1 = generateCsrfToken();
        regenerateCsrfToken();
        $token2 = generateCsrfToken();
        
        $this->assertNotEquals($token1, $token2, 'Regenerated token should be different');
        $this->assertFalse(validateCsrfToken($token1), 'Old token should not validate after rotation');
        $this->assertTrue(validateCsrfToken($token2), 'New token should validate');
    }
    
    /**
     * Test token rotation on validation (when rotate=true)
     */
    public function testTokenRotationOnValidation(): void {
        $token = generateCsrfToken();
        
        // Validate with rotation
        $result = validateCsrfToken($token, true);
        $this->assertTrue($result, 'Validation should succeed');
        
        // Old token should now be invalid
        $this->assertFalse(validateCsrfToken($token), 'Old token should be invalid after rotation');
    }
    
    /**
     * Test csrfField generates hidden input
     */
    public function testCsrfFieldOutput(): void {
        $field = csrfField();
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        
        // Extract and verify value matches current token
        preg_match('/value="([^"]+)"/', $field, $matches);
        $this->assertTrue(isset($matches[1]), 'Should have value attribute');
        
        $fieldValue = $matches[1];
        $currentToken = getCsrfToken();
        $this->assertEquals($currentToken, $fieldValue, 'Field value should match current token');
    }
    
    /**
     * Test getCsrfToken returns current session token
     */
    public function testGetCsrfToken(): void {
        $token = generateCsrfToken();
        $retrieved = getCsrfToken();
        
        $this->assertEquals($token, $retrieved);
    }
    
    /**
     * Test requireCsrfToken with exitOnFailure=false returns boolean
     */
    public function testRequireCsrfTokenNoExit(): void {
        // Without POST request, should return true (no validation needed)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $result = requireCsrfToken(false);
        $this->assertTrue($result, 'GET request should pass without validation');
        
        // POST with valid token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = generateCsrfToken();
        $result = requireCsrfToken(false);
        $this->assertTrue($result, 'POST with valid token should pass');
        
        // POST without token
        unset($_POST['csrf_token']);
        $result = requireCsrfToken(false);
        $this->assertFalse($result, 'POST without token should fail');
    }
    
    /**
     * Test tokens are unique per session
     */
    public function testTokensAreUnique(): void {
        $token1 = generateCsrfToken();
        
        // Simulate different session
        $oldSession = $_SESSION;
        $_SESSION = [];
        $token2 = generateCsrfToken();
        
        $this->assertNotEquals($token1, $token2, 'Different sessions should have different tokens');
        
        // Restore session
        $_SESSION = $oldSession;
    }
}

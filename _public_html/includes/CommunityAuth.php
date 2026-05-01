<?php
/**
 * Community Account Authentication Handler
 * 
 * CRITICAL COPPA COMPLIANCE FILE
 * 
 * This class manages Community Account sessions WITHOUT collecting or storing
 * any personally identifiable information (PII). 
 * 
 * KEY PRINCIPLES:
 * - No email addresses are stored
 * - No usernames are stored  
 * - No passwords are stored
 * - Only session identifiers and hashed IP addresses for operational security
 * - All data auto-purged after 30 days of inactivity
 */

require_once __DIR__ . '/../config/database.php';

class CommunityAuth {
    private $db;
    private $pepper;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pepper = $_ENV['PEPPER_SECRET'] ?? '';
    }
    
    /**
     * Create a new Community Account session
     * 
     * @param string $ip User's IP address
     * @param string $userAgent User's browser user agent
     * @return string Session token (cookie value)
     */
    public function createSession($ip, $userAgent) {
        // Generate cryptographically secure random token
        $token = bin2hex(random_bytes(32)); // 64 characters
        
        // Hash the token for storage (never store raw token)
        $tokenHash = hash('sha256', $token);
        
        // Hash IP with pepper for privacy
        $ipHash = hash('sha256', $ip . $this->pepper);
        
        // Hash user agent with pepper
        $userAgentHash = hash('sha256', $userAgent . $this->pepper);
        
        // Calculate expiration (30 days)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Insert into database
        $stmt = $this->db->prepare("INSERT INTO community_sessions (session_token, ip_hash, user_agent_hash, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tokenHash, $ipHash, $userAgentHash, $expiresAt]);
        
        return $token;
    }
    
    /**
     * Validate an existing session
     * 
     * @param string $token Session token from cookie
     * @return array|bool Session data if valid, false otherwise
     */
    public function validateSession($token) {
        if (empty($token) || strlen($token) !== 64) {
            return false;
        }
        
        // Hash the token for lookup
        $tokenHash = hash('sha256', $token);
        
        // Find session
        $stmt = $this->db->prepare("SELECT id, ip_hash, user_agent_hash, created_at, expires_at FROM community_sessions WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$tokenHash]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return false;
        }
        
        // Verify IP hash (optional security check)
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $currentIpHash = hash('sha256', $currentIp . $this->pepper);
        
        // SEC-H2: IP mismatch detection — IP can change legitimately (mobile, CGNAT)
        // We allow the session but log and flag it for alerting via SYSTEM Tray.
        $ipChanged = ($session['ip_hash'] !== $currentIpHash);

        if ($ipChanged) {
            // Log the mismatch for security monitoring
            error_log("SECURITY INFO: Community session IP changed for session ID {$session['id']}. This may be legitimate (mobile/VPN) or stolen cookie.");
        }
        
        // Update last activity and record IP change flag
        $stmt = $this->db->prepare("UPDATE community_sessions SET last_activity = NOW(), last_ip_hash = ? WHERE id = ?");
        $stmt->execute([$currentIpHash, $session['id']]);
        
        return [
            'id' => $session['id'],
            'is_community_account' => true,
            'ip_changed' => $ipChanged,
            'created_at' => $session['created_at'],
            'expires_at' => $session['expires_at']
        ];
    }
    
    /**
     * Destroy a session (logout)
     * 
     * @param string $token Session token
     * @return bool Success
     */
    public function destroySession($token) {
        if (empty($token)) {
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        
        $stmt = $this->db->prepare("DELETE FROM community_sessions WHERE session_token = ?");
        $stmt->execute([$tokenHash]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Clean up expired sessions (called by cron job)
     * 
     * @return int Number of sessions deleted
     */
    public function cleanupExpiredSessions() {
        $stmt = $this->db->prepare("DELETE FROM community_sessions WHERE expires_at < NOW() OR last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Check if current user has a valid Community Account session
     * 
     * @return bool
     */
    public static function isCommunityAccount() {
        if (!isset($_COOKIE['community_session']) || empty($_COOKIE['community_session'])) {
            return false;
        }
        
        $auth = new self();
        return $auth->validateSession($_COOKIE['community_session']) !== false;
    }
    
    /**
     * Get Community Account session ID for logging
     * Note: This returns the hashed token, not the raw cookie value
     * 
     * @return string|null
     */
    public static function getCommunitySessionId() {
        if (!isset($_COOKIE['community_session']) || empty($_COOKIE['community_session'])) {
            return null;
        }
        
        $pepper = $_ENV['PEPPER_SECRET'] ?? '';
        return hash('sha256', $_COOKIE['community_session'] . $pepper);
    }
    
    /**
     * Logout current Community Account user
     */
    public static function logout() {
        if (isset($_COOKIE['community_session'])) {
            $auth = new self();
            $auth->destroySession($_COOKIE['community_session']);
            
            // Clear cookie
            setcookie('community_session', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            unset($_COOKIE['community_session']);
        }
    }
    
    /**
     * Get current Community Account session info
     * 
     * @return array|null
     */
    public static function getCurrentSession() {
        if (!isset($_COOKIE['community_session']) || empty($_COOKIE['community_session'])) {
            return null;
        }
        
        $auth = new self();
        return $auth->validateSession($_COOKIE['community_session']);
    }
}

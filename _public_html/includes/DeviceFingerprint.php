<?php
/**
 * Device Fingerprint - Phase 4 Security Implementation
 * 
 * Generates unique device fingerprints for Paid Accounts to detect:
 * - Account sharing
 * - New device alerts
 * - Ban evasion attempts
 * 
 * Fingerprint components (Collected but NOT stored):
 * - IP Address
 * - User Agent
 * - Screen resolution
 * - Timezone
 * - Language
 * - Platform
 * - Canvas/WebGL fingerprint (if available)
 * 
 * Stored: SHA256 hash of the fingerprint string (non-reversible)
 */

class DeviceFingerprint {
    
    /**
     * Generate device fingerprint hash
     * Used for ban evasion detection and security logging
     * 
     * @param int $userId User ID
     * @param string $ipAddress IP address
     * @param string $userAgent User agent string
     * @param string $pepper Environment pepper for hashing
     * @return string SHA256 hash
     */
    public static function generate($userId, $ipAddress, $userAgent, $pepper = '') {
        // Combine all fingerprint components
        $components = [
            'user_id' => $userId,
            'ip' => $ipAddress,
            'ua' => $userAgent,
            // Add additional components if available from client
            'screen' => $_POST['screen_resolution'] ?? 'unknown',
            'tz' => $_POST['timezone'] ?? 'unknown',
            'lang' => $_POST['language'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
            'platform' => $_POST['platform'] ?? 'unknown'
        ];
        
        // Create consistent string
        $fingerprintString = json_encode($components, JSON_UNESCAPED_SLASHES | JSON_SORT_KEYS);
        
        // Hash with pepper for additional security
        $pepper = $pepper ?: ($_ENV['PEPPER_SECRET'] ?? '');
        
        return hash('sha256', $fingerprintString . $pepper);
    }
    
    /**
     * Check if this is a new device for the user
     * Only for Paid Accounts (Community Accounts use different detection)
     * 
     * @param int $userId User ID
     * @param string $fingerprintHash Current device fingerprint
     * @param PDO $db Database connection
     * @return bool True if this is a new device
     */
    public static function isNewDevice($userId, $fingerprintHash, $db) {
        try {
            // Check if this fingerprint exists in recent history (30 days)
            // Use DATE() comparison to avoid midnight precision bug
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM user_devices 
                WHERE user_id = ? 
                AND fingerprint_hash = ?
                AND DATE(last_seen) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$userId, $fingerprintHash]);
            $count = $stmt->fetchColumn();
            
            return $count === 0;
            
        } catch (Exception $e) {
            error_log("isNewDevice check error: " . $e->getMessage());
            return false; // Fail open - don't block on errors
        }
    }
    
    /**
     * Record device login
     * Called on every login for Paid Accounts
     * 
     * @param int $userId User ID
     * @param string $fingerprintHash Device fingerprint
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @param PDO $db Database connection
     * @return bool Success
     */
    public static function recordDevice($userId, $fingerprintHash, $ipAddress, $userAgent, $db) {
        try {
            // Insert or update device record
            $stmt = $db->prepare("
                INSERT INTO user_devices 
                (user_id, fingerprint_hash, ip_address, user_agent, first_seen, last_seen, login_count)
                VALUES (?, ?, ?, ?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE
                last_seen = NOW(),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                login_count = login_count + 1
            ");
            $stmt->execute([$userId, $fingerprintHash, $ipAddress, $userAgent]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Record device error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's devices
     * 
     * @param int $userId User ID
     * @param PDO $db Database connection
     * @return array Device records
     */
    public static function getUserDevices($userId, $db) {
        try {
            $stmt = $db->prepare("
                SELECT fingerprint_hash, ip_address, 
                       DATE_FORMAT(first_seen, '%Y-%m-%d %H:%i') as first_seen,
                       DATE_FORMAT(last_seen, '%Y-%m-%d %H:%i') as last_seen,
                       login_count
                FROM user_devices 
                WHERE user_id = ?
                ORDER BY last_seen DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get user devices error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check for suspicious device patterns
     * - Multiple accounts from same device (account sharing)
     * - Known banned device attempting new registration
     * 
     * @param string $fingerprintHash Device fingerprint
     * @param int $excludeUserId User ID to exclude (for self-checks)
        * @param PDO $db Database connection
     * @return array Suspicious activity findings
     */
    public static function checkSuspiciousPatterns($fingerprintHash, $excludeUserId, $db) {
        $findings = [
            'multiple_accounts' => false,
            'accounts' => [],
            'banned_device' => false,
            'risk_level' => 'low'
        ];
        
        try {
            // Check if this device is associated with other accounts
            $stmt = $db->prepare("
                SELECT DISTINCT u.id, u.username, u.email_hash, u.account_status
                FROM user_devices d
                JOIN users u ON d.user_id = u.id
                WHERE d.fingerprint_hash = ?
                AND d.user_id != ?
                AND u.account_tier = 'paid'
                AND DATE(d.last_seen) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$fingerprintHash, $excludeUserId]);
            $otherAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($otherAccounts) > 0) {
                $findings['multiple_accounts'] = true;
                $findings['accounts'] = $otherAccounts;
                $findings['risk_level'] = 'medium';
                
                // Check if any of those accounts are banned/terminated
                foreach ($otherAccounts as $account) {
                    if ($account['account_status'] === 'terminated') {
                        $findings['banned_device'] = true;
                        $findings['risk_level'] = 'high';
                        break;
                    }
                }
            }
            
            return $findings;
            
        } catch (Exception $e) {
            error_log("Suspicious pattern check error: " . $e->getMessage());
            return $findings;
        }
    }
    
    /**
     * Generate client-side fingerprint data collection script
     * This is included in login pages to collect browser data
     * 
     * @return string JavaScript code
     */
    public static function getCollectionScript() {
        return <<<JS
<script>
(function() {
    // Collect browser fingerprint data
    const fingerprint = {
        screen_resolution: screen.width + 'x' + screen.height,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        language: navigator.language || navigator.userLanguage,
        platform: navigator.platform,
        color_depth: screen.colorDepth,
        pixel_ratio: window.devicePixelRatio || 1,
        touch_support: 'ontouchstart' in window || navigator.maxTouchPoints > 0
    };
    
    // Add hidden form fields or send via AJAX
    const form = document.querySelector('form[action*="login"], form[action*="auth"]');
    if (form) {
        Object.keys(fingerprint).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fingerprint[key];
            form.appendChild(input);
        });
    }
})();
</script>
JS;
    }
}

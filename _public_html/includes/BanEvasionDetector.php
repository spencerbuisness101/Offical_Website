<?php
/**
 * Ban Evasion Detector - Phase 4 Security Implementation
 * 
 * Detects attempts to circumvent bans through:
 * - Email similarity matching (local part similarity > 80%)
 * - Device fingerprint matching (known banned devices)
 * - IP address matching (same IP as banned account)
 * - Name variation matching (birth year, underscores, numbers)
 * - Browser fingerprint matching (canvas, WebGL, fonts)
 * 
 * Actions on detection:
 * - Immediate termination of new account
 * - Escalated punishment on original account (if exists)
 * - Device fingerprint added to ban list
 * - Email hash added to ban list
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/DeviceFingerprint.php';

class BanEvasionDetector {
    private $db;
    private $pepper;
    
    // Similarity threshold for email matching (80%)
    const EMAIL_SIMILARITY_THRESHOLD = 0.80;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pepper = $_ENV['PEPPER_SECRET'] ?? '';
    }
    
    /**
     * Check for ban evasion attempts during registration
     * 
     * @param string $email New user's email
     * @param string $username New user's username
     * @param string $fingerprintHash Device fingerprint
     * @param string $ipAddress IP address
     * @return array Detection results
     */
    public function checkRegistration($email, $username, $fingerprintHash, $ipAddress) {
        $findings = [
            'is_evasion' => false,
            'matches' => [],
            'action' => 'allow', // allow, block, terminate
            'confidence' => 0
        ];
        
        // 1. Check email against banned list
        $emailMatch = $this->checkEmailAgainstBanList($email);
        if ($emailMatch['is_banned']) {
            $findings['matches'][] = $emailMatch;
            $findings['confidence'] += $emailMatch['confidence'];
        }
        
        // 2. Check email similarity to existing accounts
        $similarEmail = $this->checkEmailSimilarity($email);
        if ($similarEmail['is_similar']) {
            $findings['matches'][] = $similarEmail;
            $findings['confidence'] += $similarEmail['confidence'];
        }
        
        // 3. Check device fingerprint against banned devices
        $deviceMatch = $this->checkDeviceFingerprint($fingerprintHash);
        if ($deviceMatch['is_banned_device']) {
            $findings['matches'][] = $deviceMatch;
            $findings['confidence'] += $deviceMatch['confidence'];
        }
        
        // 4. Check IP address against banned accounts
        $ipMatch = $this->checkIPAddress($ipAddress);
        if ($ipMatch['is_banned_ip']) {
            $findings['matches'][] = $ipMatch;
            $findings['confidence'] += $ipMatch['confidence'];
        }
        
        // 5. Check username for variations of banned names
        $nameMatch = $this->checkUsernameVariation($username);
        if ($nameMatch['is_variation']) {
            $findings['matches'][] = $nameMatch;
            $findings['confidence'] += $nameMatch['confidence'];
        }
        
        // Determine action based on confidence score
        if ($findings['confidence'] >= 90) {
            $findings['is_evasion'] = true;
            $findings['action'] = 'terminate';
        } elseif ($findings['confidence'] >= 60) {
            $findings['is_evasion'] = true;
            $findings['action'] = 'block';
        }
        
        return $findings;
    }
    
    /**
     * Check email hash against banned list
     */
    private function checkEmailAgainstBanList($email) {
        $emailHash = hash('sha256', strtolower(trim($email)) . $this->pepper);
        
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.termination_reason, u.terminated_at
            FROM users u
            WHERE u.email_hash = ?
            AND u.account_status = 'terminated'
            LIMIT 1
        ");
        $stmt->execute([$emailHash]);
        $banned = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($banned) {
            return [
                'type' => 'banned_email',
                'is_banned' => true,
                'confidence' => 100, // Certain match
                'matched_account' => $banned,
                'message' => 'This email address belongs to a terminated account'
            ];
        }
        
        return ['is_banned' => false];
    }
    
    /**
     * Check email similarity (>80% local part similarity)
     * Handles patterns like: user1@gmail.com, user_1@gmail.com, user.1@gmail.com
     */
    private function checkEmailSimilarity($email) {
        $parts = explode('@', strtolower($email));
        if (count($parts) !== 2) {
            return ['is_similar' => false];
        }
        
        $localPart = $parts[0];
        $domain = $parts[1];
        
        // Normalize: remove dots, underscores, numbers
        $normalized = preg_replace('/[._0-9]/', '', $localPart);
        
        // Find similar emails in database
        $stmt = $this->db->prepare("
            SELECT id, username, email_hash, account_status, terminated_at
            FROM users
            WHERE account_status = 'terminated'
            AND email_hash IS NOT NULL
        ");
        $stmt->execute();
        $bannedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bannedAccounts as $account) {
            // We can't decrypt the email_hash, so we compare against stored patterns
            // This is a simplified check - in production, you'd store normalized patterns
            $stmt = $this->db->prepare("
                SELECT pattern FROM ban_email_patterns 
                WHERE user_id = ?
            ");
            $stmt->execute([$account['id']]);
            $patterns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($patterns as $pattern) {
                similar_text($normalized, $pattern, $similarity);
                if ($similarity >= (self::EMAIL_SIMILARITY_THRESHOLD * 100)) {
                    return [
                        'type' => 'similar_email',
                        'is_similar' => true,
                        'confidence' => round($similarity),
                        'matched_account' => $account,
                        'message' => "Email similarity detected ({$similarity}% match)"
                    ];
                }
            }
        }
        
        return ['is_similar' => false];
    }
    
    /**
     * Check device fingerprint against banned devices
     */
    private function checkDeviceFingerprint($fingerprintHash) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id, u.username, u.terminated_at, d.last_seen
            FROM user_devices d
            JOIN users u ON d.user_id = u.id
            WHERE d.fingerprint_hash = ?
            AND u.account_status = 'terminated'
            LIMIT 1
        ");
        $stmt->execute([$fingerprintHash]);
        $bannedDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bannedDevice) {
            return [
                'type' => 'banned_device',
                'is_banned_device' => true,
                'confidence' => 95,
                'matched_account' => $bannedDevice,
                'message' => 'This device was used by a terminated account'
            ];
        }
        
        return ['is_banned_device' => false];
    }
    
    /**
     * Check IP address against banned accounts
     */
    private function checkIPAddress($ipAddress) {
        // Check recent logins from banned accounts
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id, u.username, u.terminated_at, d.last_seen
            FROM user_devices d
            JOIN users u ON d.user_id = u.id
            WHERE d.ip_address = ?
            AND u.account_status = 'terminated'
            AND DATE(d.last_seen) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            LIMIT 1
        ");
        $stmt->execute([$ipAddress]);
        $bannedIP = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bannedIP) {
            return [
                'type' => 'banned_ip',
                'is_banned_ip' => true,
                'confidence' => 70, // Lower confidence - IPs can change
                'matched_account' => $bannedIP,
                'message' => 'This IP was recently used by a terminated account'
            ];
        }
        
        return ['is_banned_ip' => false];
    }
    
    /**
     * Check username for variations of banned names
     * Patterns: birth years, underscores, numbers added/removed
     */
    private function checkUsernameVariation($username) {
        $normalized = strtolower(preg_replace('/[0-9_.-]/', '', $username));
        
        // Get banned usernames (stored before anonymization)
        $stmt = $this->db->prepare("
            SELECT u.id, u.terminated_at, b.normalized_username
            FROM banned_username_patterns b
            JOIN users u ON b.user_id = u.id
            WHERE b.normalized_username = ?
            LIMIT 1
        ");
        $stmt->execute([$normalized]);
        $bannedUsername = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bannedUsername) {
            return [
                'type' => 'username_variation',
                'is_variation' => true,
                'confidence' => 85,
                'matched_account' => $bannedUsername,
                'message' => 'Username matches a banned account pattern'
            ];
        }
        
        return ['is_variation' => false];
    }
    
    /**
     * Add user to ban tracking
     * Called when account is terminated
     * 
     * @param int $userId User being terminated
     * @param string $email Email (before clearing)
     * @param string $username Username (before anonymizing)
     * @param string $fingerprintHash Device fingerprint
     * @return bool Success
     */
    public function addToBanList($userId, $email, $username, $fingerprintHash) {
        try {
            $this->db->beginTransaction();
            
            // 1. Store email hash (already done in users table)
            $emailHash = hash('sha256', strtolower(trim($email)) . $this->pepper);
            
            // 2. Store email pattern for similarity detection
            $parts = explode('@', strtolower($email));
            $localPart = preg_replace('/[._0-9]/', '', $parts[0]);
            $domain = $parts[1] ?? 'unknown';
            
            $stmt = $this->db->prepare("
                INSERT INTO ban_email_patterns 
                (user_id, email_hash, normalized_local, domain, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $emailHash, $localPart, $domain]);
            
            // 3. Store normalized username pattern
            $normalizedUsername = strtolower(preg_replace('/[0-9_.-]/', '', $username));
            
            $stmt = $this->db->prepare("
                INSERT INTO banned_username_patterns
                (user_id, username, normalized_username, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $username, $normalizedUsername]);
            
            // 4. Mark device as banned
            if ($fingerprintHash) {
                $stmt = $this->db->prepare("
                    INSERT INTO banned_devices
                    (fingerprint_hash, banned_user_id, banned_at, reason)
                    VALUES (?, ?, NOW(), 'account_termination')
                    ON DUPLICATE KEY UPDATE
                    banned_at = NOW()
                ");
                $stmt->execute([$fingerprintHash, $userId]);
            }
            
            // 5. Mark IP as banned
            $stmt = $this->db->prepare("
                SELECT DISTINCT ip_address FROM user_devices WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($ips as $ip) {
                $stmt = $this->db->prepare("
                    INSERT INTO banned_ips
                    (ip_address, banned_user_id, banned_at, reason)
                    VALUES (?, ?, NOW(), 'account_termination')
                    ON DUPLICATE KEY UPDATE
                    banned_at = NOW()
                ");
                $stmt->execute([$ip, $userId]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Add to ban list error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Escalate punishment on original account
     * When ban evasion is detected, increase punishment on original
     * 
     * @param int $originalUserId Original banned account
     * @param int $newUserId New evasion account
     * @return bool Success
     */
    public function escalateOriginalAccount($originalUserId, $newUserId) {
        try {
            // Get current punishment
            $stmt = $this->db->prepare("
                SELECT punishment_type, restriction_until, account_status
                FROM users WHERE id = ?
            ");
            $stmt->execute([$originalUserId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$original) {
                return false;
            }
            
            // If already terminated, nothing to escalate
            if ($original['account_status'] === 'terminated') {
                return true;
            }
            
            // If in Time Removal, extend it
            if ($original['account_status'] === 'suspended' && $original['restriction_until']) {
                $newRestriction = date('Y-m-d H:i:s', strtotime($original['restriction_until'] . ' +14 days'));
                
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET restriction_until = ?,
                        escalation_reason = CONCAT(escalation_reason, ' | Ban evasion detected on new account #', ?)
                    WHERE id = ?
                ");
                $stmt->execute([$newRestriction, $newUserId, $originalUserId]);
                
                return true;
            }
            
            // If in lockdown, keep them there longer
            if ($original['account_status'] === 'restricted') {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET escalation_reason = CONCAT(escalation_reason, ' | Ban evasion detected on new account #', ?)
                    WHERE id = ?
                ");
                $stmt->execute([$newUserId, $originalUserId]);
                
                return true;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Escalate original account error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get ban evasion statistics
     */
    public function getStatistics($days = 30) {
        try {
            $stats = [
                'detected_attempts' => 0,
                'blocked_registrations' => 0,
                'terminated_evasion_accounts' => 0,
                'escalated_originals' => 0
            ];
            
            // Detected attempts (logged evasion checks)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM ban_evasion_logs 
                WHERE detected_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['detected_attempts'] = $stmt->fetchColumn();
            
            // Blocked registrations
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM ban_evasion_logs 
                WHERE action = 'block'
                AND detected_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['blocked_registrations'] = $stmt->fetchColumn();
            
            // Terminated evasion accounts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM ban_evasion_logs 
                WHERE action = 'terminate'
                AND detected_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['terminated_evasion_accounts'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Ban evasion stats error: " . $e->getMessage());
            return $stats;
        }
    }
    
    /**
     * Log evasion detection for admin review
     */
    public function logDetection($newEmail, $newUsername, $findings, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ban_evasion_logs
                (attempted_email, attempted_username, detection_confidence, 
                 matched_accounts, action, detected_at, ip_address)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $confidence = $findings['confidence'] ?? 0;
            $matches = json_encode(array_map(function($m) {
                return $m['matched_account']['id'] ?? null;
            }, $findings['matches'] ?? []));
            
            $stmt->execute([
                $newEmail,
                $newUsername,
                $confidence,
                $matches,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Log evasion detection error: " . $e->getMessage());
        }
    }
}

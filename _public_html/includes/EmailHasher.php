<?php
/**
 * Email Hasher - Phase 4 Security Implementation
 * 
 * Secure email hashing for ban list and privacy compliance:
 * - SHA256 with pepper for ban list matching
 * - One-way hashing (cannot be reversed)
 * - Consistent hashing across the application
 */

class EmailHasher {
    
    private static $pepper = null;
    
    /**
     * Get the pepper (environment secret)
     */
    private static function getPepper() {
        if (self::$pepper === null) {
            self::$pepper = $_ENV['PEPPER_SECRET'] ?? '';
        }
        return self::$pepper;
    }
    
    /**
     * Hash an email address for ban list / storage
     * 
     * @param string $email Email address
     * @return string SHA256 hash
     */
    public static function hash($email) {
        // Normalize: lowercase, trim whitespace
        $normalized = strtolower(trim($email));
        
        // Get pepper
        $pepper = self::getPepper();
        
        // Generate hash: email + pepper
        return hash('sha256', $normalized . $pepper);
    }
    
    /**
     * Verify an email against a stored hash
     * 
     * @param string $email Email to verify
     * @param string $storedHash Hash from database
     * @return bool Match
     */
    public static function verify($email, $storedHash) {
        $computedHash = self::hash($email);
        return hash_equals($storedHash, $computedHash);
    }
    
    /**
     * Check if email is in banned list
     * 
     * @param string $email Email to check
     * @param PDO $db Database connection
     * @return array Result with banned status and account info if banned
     */
    public static function checkBanList($email, $db) {
        try {
            $hash = self::hash($email);
            
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.terminated_at, u.termination_reason
                FROM users u
                WHERE u.email_hash = ?
                AND u.account_status = 'terminated'
                LIMIT 1
            ");
            $stmt->execute([$hash]);
            $banned = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($banned) {
                return [
                    'is_banned' => true,
                    'account' => $banned,
                    'message' => 'This email address is associated with a terminated account.'
                ];
            }
            
            return ['is_banned' => false];
            
        } catch (Exception $e) {
            error_log("Check ban list error: " . $e->getMessage());
            return ['is_banned' => false, 'error' => true];
        }
    }
    
    /**
     * Generate email patterns for similarity detection
     * Creates multiple patterns for catching variations
     * 
     * @param string $email Original email
     * @return array Array of patterns
     */
    public static function generatePatterns($email) {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return [];
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        $patterns = [];
        
        // Pattern 1: Remove dots (gmail treats dots as same)
        $patterns[] = str_replace('.', '', $local) . '@' . $domain;
        
        // Pattern 2: Remove underscores
        $patterns[] = str_replace('_', '', $local) . '@' . $domain;
        
        // Pattern 3: Remove numbers
        $patterns[] = preg_replace('/[0-9]/', '', $local) . '@' . $domain;
        
        // Pattern 4: Remove all special chars and numbers (base name)
        $patterns[] = preg_replace('/[._0-9-]/', '', $local) . '@' . $domain;
        
        // Pattern 5: Just local part
        $patterns[] = $local;
        
        return $patterns;
    }
    
    /**
     * Store email patterns for a user
     * Called during registration or email change
     * 
     * @param int $userId User ID
     * @param string $email Email address
     * @param PDO $db Database connection
     * @return bool Success
     */
    public static function storePatterns($userId, $email, $db) {
        try {
            $patterns = self::generatePatterns($email);
            
            // Clear existing patterns
            $stmt = $db->prepare("DELETE FROM ban_email_patterns WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Insert new patterns
            $stmt = $db->prepare("
                INSERT INTO ban_email_patterns 
                (user_id, email_hash, normalized_local, domain, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $hash = self::hash($email);
            $parts = explode('@', strtolower(trim($email)));
            $domain = $parts[1] ?? 'unknown';
            
            foreach ($patterns as $pattern) {
                $normalized = preg_replace('/[._0-9-]/', '', $parts[0]);
                $stmt->execute([$userId, $hash, $normalized, $domain]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Store email patterns error: " . $e->getMessage());
            return false;
        }
    }
}

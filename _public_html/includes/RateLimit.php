<?php
/**
 * Rate Limiting - Phase 4 Security Implementation
 * 
 * Flexible rate limiting for various actions:
 * - Login attempts (5 per 5 minutes)
 * - Registration (3 per hour)
 * - VPC code requests (3 per 15 minutes)
 * - Age verification (5 per day)
 * - Appeal submissions (2 per day)
 */

require_once __DIR__ . '/db.php';

class RateLimit {
    private $db;
    
    // Default limits for different actions
    const LIMITS = [
        'login' => ['max' => 5, 'window' => 300],           // 5 per 5 minutes
        'registration' => ['max' => 3, 'window' => 3600],  // 3 per hour
        'vpc_request' => ['max' => 3, 'window' => 900],    // 3 per 15 minutes
        'vpc_verify' => ['max' => 5, 'window' => 900],    // 5 per 15 minutes
        'age_verification' => ['max' => 5, 'window' => 86400], // 5 per day
        'appeal' => ['max' => 2, 'window' => 86400],       // 2 per day
        'password_reset' => ['max' => 3, 'window' => 3600], // 3 per hour
        'api_general' => ['max' => 100, 'window' => 60],   // 100 per minute (API)
    ];
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Check if action is allowed for the given identifier.
     *
     * BUG FIX (v7.1): Previously this method ALWAYS inserted a log row, even
     * when the caller was already over the limit. That caused every retry to
     * slide the window forward, producing a permanent lockout. We now count
     * first and only log when the attempt is actually allowed (or when the
     * caller asks us to via $logAttempt). Failed-credential logging is the
     * caller's responsibility (auth/login.php) so we don't double-count.
     *
     * @param string $action Action type (login, registration, etc.)
     * @param string $identifier IP address, user ID, or other identifier
     * @param int|null $customMax Override default max attempts
     * @param int|null $customWindow Override default window in seconds
     * @param bool $logAttempt If true, log this attempt when allowed. Defaults
     *                         to false so callers explicitly opt in via log().
     * @return bool True if allowed, false if rate limited
     */
    public function check($action, $identifier, $customMax = null, $customWindow = null, $logAttempt = false) {
        $lockName = null;
        try {
            // Get limits
            $maxAttempts = $customMax ?? (self::LIMITS[$action]['max'] ?? 10);
            $windowSeconds = $customWindow ?? (self::LIMITS[$action]['window'] ?? 3600);

            // SEC-H3: Advisory lock serializes per (action, identifier) to prevent TOCTOU race.
            // Two concurrent requests for the same (action, identifier) execute sequentially.
            // 5-second timeout prevents deadlock if a prior request crashes mid-check.
            $lockName = 'rl_' . md5($action . '_' . $identifier);
            $lockStmt = $this->db->query("SELECT GET_LOCK(" . $this->db->quote($lockName) . ", 5)");
            $lockStmt->fetchColumn();
            $lockStmt->closeCursor();

            // Clean up old entries (prevents table bloat even if cron isn't running)
            $this->cleanup($action, $windowSeconds);

            // Count recent attempts BEFORE deciding whether to log a new one
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM rate_limit_log
                WHERE action = ? AND identifier = ?
                AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $identifier, $windowSeconds]);
            $attemptCount = (int)$stmt->fetchColumn();
            $stmt->closeCursor();

            $allowed = ($attemptCount < $maxAttempts);

            // Only log when allowed AND caller opted in. This prevents the
            // sliding-window self-extension that previously caused permanent
            // lockouts. Callers who want to record a failure should call
            // log($action, $identifier) explicitly after credential check.
            if ($allowed && $logAttempt) {
                $this->log($action, $identifier);
            }

            return $allowed;
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            // Throw so callers can decide whether to fail-open or fail-closed.
            // Previously returned false (fail-closed), which caused permanent
            // login lockouts when the rate_limit_log table was missing or errored.
            throw new RuntimeException("Rate limit check failed: " . $e->getMessage(), 0, $e);
        } finally {
            // Always release the advisory lock — even on success/exception.
            // Without finally, a thrown exception could leave the lock held
            // until session end, blocking concurrent requests.
            if ($lockName !== null) {
                try {
                    $releaseStmt = $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockName) . ")");
                    $releaseStmt->fetchColumn();
                    $releaseStmt->closeCursor();
                } catch (Exception $ignored) { /* lock cleanup is best-effort */ }
            }
        }
    }

    /**
     * Explicitly record an attempt against an action+identifier. Use this
     * after a failed credential check so brute-force attempts are counted.
     *
     * Acquires the same advisory lock as check() to prevent a TOCTOU race
     * where two concurrent requests both pass check() and then both log(),
     * exceeding the max by 1.
     *
     * @param string $action
     * @param string $identifier
     * @return void
     */
    public function log($action, $identifier) {
        $lockName = null;
        try {
            // Re-acquire the same advisory lock used by check() so that
            // concurrent log() calls are serialized per (action, identifier).
            $lockName = 'rl_' . md5($action . '_' . $identifier);
            $lockStmt = $this->db->query("SELECT GET_LOCK(" . $this->db->quote($lockName) . ", 5)");
            $lockStmt->fetchColumn();
            $lockStmt->closeCursor();

            $rawIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $pepper = $_ENV['PEPPER_SECRET'] ?? getenv('PEPPER_SECRET') ?? '';
            $hashedIp = hash('sha256', $rawIp . $pepper);

            $stmt = $this->db->prepare("
                INSERT INTO rate_limit_log (action, identifier, attempted_at, ip_address)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$action, $identifier, $hashedIp]);
        } catch (Exception $e) {
            error_log("Rate limit log error: " . $e->getMessage());
        } finally {
            if ($lockName !== null) {
                try {
                    $releaseStmt = $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockName) . ")");
                    $releaseStmt->fetchColumn();
                    $releaseStmt->closeCursor();
                } catch (Exception $ignored) { /* lock cleanup is best-effort */ }
            }
        }
    }
    
    /**
     * Get remaining attempts and retry after time
     * 
     * @param string $action Action type
     * @param string $identifier Identifier
     * @return array Remaining and retry info
     */
    public function getStatus($action, $identifier) {
        try {
            $maxAttempts = self::LIMITS[$action]['max'] ?? 10;
            $windowSeconds = self::LIMITS[$action]['window'] ?? 3600;
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM rate_limit_log
                WHERE action = ? AND identifier = ?
                AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $identifier, $windowSeconds]);
            $attemptCount = $stmt->fetchColumn();
            $stmt->closeCursor();
            
            // Get oldest attempt time to calculate retry after
            $stmt = $this->db->prepare("
                SELECT attempted_at FROM rate_limit_log
                WHERE action = ? AND identifier = ?
                AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                ORDER BY attempted_at ASC
                LIMIT 1
            ");
            $stmt->execute([$action, $identifier, $windowSeconds]);
            $oldestAttempt = $stmt->fetchColumn();
            $stmt->closeCursor();
            
            $retryAfter = 0;
            if ($oldestAttempt) {
                $oldestTime = strtotime($oldestAttempt);
                $retryAfter = max(0, $oldestTime + $windowSeconds - time());
            }
            
            return [
                'remaining' => max(0, $maxAttempts - $attemptCount),
                'max' => $maxAttempts,
                'used' => $attemptCount,
                'retry_after_seconds' => $retryAfter,
                'retry_after_formatted' => $this->formatDuration($retryAfter)
            ];
            
        } catch (Exception $e) {
            error_log("Rate limit status error: " . $e->getMessage());
            return ['remaining' => 0, 'retry_after_seconds' => 60];
        }
    }
    
    /**
     * Get retry after time for rate limited requests
     */
    public function getRetryAfter($action, $identifier) {
        $status = $this->getStatus($action, $identifier);
        return $status['retry_after_seconds'];
    }
    
    /**
     * Reset rate limit for an identifier
     * Useful for successful actions or admin intervention
     * 
     * @param string $action Action type
     * @param string $identifier Identifier
     * @return bool Success
     */
    public function reset($action, $identifier) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM rate_limit_log
                WHERE action = ? AND identifier = ?
            ");
            $stmt->execute([$action, $identifier]);
            $stmt->closeCursor();
            return true;
            
        } catch (Exception $e) {
            error_log("Rate limit reset error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old rate limit entries
     */
    private function cleanup($action, $windowSeconds) {
        try {
            // Keep entries for 2x the window to allow analysis
            $cleanupWindow = $windowSeconds * 2;
            
            $stmt = $this->db->prepare("
                DELETE FROM rate_limit_log
                WHERE action = ?
                AND attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $cleanupWindow]);
            $stmt->closeCursor();
            
        } catch (Exception $e) {
            error_log("Rate limit cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Format duration in human-readable format
     */
    private function formatDuration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return ceil($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return ceil($seconds / 3600) . ' hours';
        } else {
            return ceil($seconds / 86400) . ' days';
        }
    }
    
    /**
     * Get rate limiting statistics
     */
    public function getStatistics($hours = 24) {
        try {
            $stats = [];
            
            foreach (self::LIMITS as $action => $config) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM rate_limit_log
                    WHERE action = ?
                    AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ");
                $stmt->execute([$action, $hours]);
                $count = $stmt->fetchColumn();
                $stmt->closeCursor();
                
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT identifier) FROM rate_limit_log
                    WHERE action = ?
                    AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ");
                $stmt->execute([$action, $hours]);
                $uniqueIdentifiers = $stmt->fetchColumn();
                $stmt->closeCursor();
                
                $stats[$action] = [
                    'total_attempts' => $count,
                    'unique_identifiers' => $uniqueIdentifiers,
                    'limit' => $config['max'],
                    'window_seconds' => $config['window']
                ];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Rate limit stats error: " . $e->getMessage());
            return [];
        }
    }
}


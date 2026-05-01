<?php
/**
 * Punishment Manager - Enforcement & Lockdown
 * 
 * Handles application of punishments from the Three-Tier system:
 * - Tier 1: Verbal Warnings (notifications)
 * - Tier 2: Time Removal (temporary suspension)  
 * - Tier 3: Account Termination (permanent ban)
 * 
 * Special: Lockdown Mode (B1/NSFW, C1/Doxxing)
 * - Immediate restriction to view-only
 * - Forced appeal process
 * - Account deletion disabled during lockdown
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_mailer.php';

class PunishmentManager {
    private $db;
    
    // Time Removal duration options (days)
    const REMOVAL_DURATIONS = [1, 3, 7, 14];
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Apply Time Removal (Tier 2)
     * 
     * @param int $userId User to suspend
     * @param int $durationDays Duration in days (1, 3, 7, or 14)
     * @param string $reason Reason for suspension
     * @param int $strikeId Associated strike ID
     * @return array Result
     */
    public function applyTimeRemoval($userId, $durationDays, $reason, $strikeId) {
        try {
            // Validate duration
            if (!in_array($durationDays, self::REMOVAL_DURATIONS)) {
                return ['success' => false, 'message' => 'Invalid duration. Must be 1, 3, 7, or 14 days.'];
            }
            
            // Calculate end date
            $restrictionUntil = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
            
            // Update user status
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_status = 'suspended',
                    restriction_until = ?,
                    suspension_reason = ?,
                    suspension_strike_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$restrictionUntil, $reason, $strikeId, $userId]);
            
            // Send notification to user
            $this->notifyTimeRemoval($userId, $durationDays, $reason);
            
            return [
                'success' => true,
                'message' => "User suspended for {$durationDays} days.",
                'restriction_until' => $restrictionUntil
            ];
            
        } catch (Exception $e) {
            error_log("Apply Time Removal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to apply Time Removal.'];
        }
    }
    
    /**
     * Apply Lockdown Mode (Special enforcement for B1/C1)
     * 
     * @param int $userId User to lock down
     * @param string $ruleId Rule violated (B1 or C1)
     * @param string $reason Detailed reason
     * @param int $strikeId Associated strike ID
     * @return array Result
     */
    public function applyLockdown($userId, $ruleId, $reason, $strikeId) {
        try {
            // Update user status to restricted (lockdown)
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_status = 'restricted',
                    lockdown_mode = TRUE,
                    lockdown_rule = ?,
                    lockdown_reason = ?,
                    lockdown_strike_id = ?,
                    lockdown_at = NOW(),
                    can_delete_account = FALSE
                WHERE id = ?
            ");
            $stmt->execute([$ruleId, $reason, $strikeId, $userId]);
            
            // Send lockdown notification
            $this->notifyLockdown($userId, $ruleId, $reason);
            
            return [
                'success' => true,
                'message' => 'User placed in lockdown mode.',
                'requires_appeal' => true
            ];
            
        } catch (Exception $e) {
            error_log("Apply Lockdown error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to apply lockdown.'];
        }
    }
    
    /**
     * Apply Account Termination (Tier 3)
     * 
     * @param int $userId User to terminate
     * @param string $reason Reason for termination
     * @param int $strikeId Associated strike ID
     * @param bool $banEvasion Whether this is for ban evasion (affects original account)
     * @return array Result
     */
    public function applyTermination($userId, $reason, $strikeId, $banEvasion = false) {
        try {
            $this->db->beginTransaction();
            
            // Fetch ALL fields needed for ban list BEFORE the row is mutated
            $stmt = $this->db->prepare("
                SELECT email, username, device_fingerprint 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            // Hash email for ban list before clearing it
            $pepper = $_ENV['PEPPER_SECRET'] ?? '';
            $emailHash = hash('sha256', ($user['email'] ?? '') . $pepper);
            
            // Update user to terminated status
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_status = 'terminated',
                    terminated_at = NOW(),
                    termination_reason = ?,
                    termination_strike_id = ?,
                    email = NULL,
                    email_hash = ?,
                    can_delete_account = FALSE,
                    username = CONCAT('[Banned_', ?, ']')
                WHERE id = ?
            ");
            $stmt->execute([$reason, $strikeId, $emailHash, $userId, $userId]);
            
            // If ban evasion, escalate original account's punishment
            if ($banEvasion) {
                // This would require tracking the original account
                // Implementation depends on ban evasion detection system
            }
            
            // Anonymize user's content
            $this->anonymizeUserContent($userId);
            
            $this->db->commit();

            // Populate ban list AFTER commit so data is consistent
            try {
                if (file_exists(__DIR__ . '/BanEvasionDetector.php')) {
                    require_once __DIR__ . '/BanEvasionDetector.php';
                    if (class_exists('BanEvasionDetector')) {
                        $detector = new BanEvasionDetector();
                        $detector->addToBanList(
                            $userId,
                            $user['email'] ?? '',
                            $user['username'] ?? '',
                            $user['device_fingerprint'] ?? ''
                        );
                    }
                }
            } catch (Exception $banEx) {
                // Non-fatal — account is already terminated; log and continue
                error_log("Ban list population error for user {$userId}: " . $banEx->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Account terminated.',
                'email_hash' => $emailHash
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Apply Termination error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to terminate account.'];
        }
    }
    
    /**
     * Remove Lockdown (after successful appeal)
     * 
     * @param int $userId User to release from lockdown
     * @param int $adminId Admin who approved the appeal
     * @param string $notes Admin notes
     * @return array Result
     */
    public function removeLockdown($userId, $adminId, $notes = '') {
        try {
            // Update user status back to active
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_status = 'active',
                    lockdown_mode = FALSE,
                    lockdown_released_at = NOW(),
                    lockdown_released_by = ?,
                    lockdown_release_notes = ?,
                    can_delete_account = TRUE
                WHERE id = ? AND account_status = 'restricted'
            ");
            $stmt->execute([$adminId, $notes, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'User not in lockdown or not found.'];
            }
            
            // Send notification
            $this->notifyLockdownReleased($userId, $notes);
            
            return [
                'success' => true,
                'message' => 'Lockdown removed. User can now access full features.'
            ];
            
        } catch (Exception $e) {
            error_log("Remove Lockdown error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to remove lockdown.'];
        }
    }
    
    /**
     * Check if user is in lockdown
     */
    public function isInLockdown($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT account_status, lockdown_mode, lockdown_reason, lockdown_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            return $user['account_status'] === 'restricted' && $user['lockdown_mode'];
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get lockdown info for a user
     */
    public function getLockdownInfo($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.lockdown_reason, u.lockdown_at, u.lockdown_rule,
                       s.rule_id, s.violation_type, s.evidence
                FROM users u
                LEFT JOIN user_strikes s ON u.lockdown_strike_id = s.id
                WHERE u.id = ? AND u.account_status = 'restricted'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Anonymize user content upon termination/downgrade
     */
    private function anonymizeUserContent($userId) {
        try {
            // Anonymize posts
            $stmt = $this->db->prepare("
                UPDATE posts 
                SET user_id = NULL, username = '[Deleted User]' 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Anonymize comments
            $stmt = $this->db->prepare("
                UPDATE comments 
                SET user_id = NULL, username = '[Deleted User]' 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Anonymize messages (keep content but remove sender identity)
            $stmt = $this->db->prepare("
                UPDATE messages 
                SET sender_id = NULL, sender_name = '[Deleted User]' 
                WHERE sender_id = ?
            ");
            $stmt->execute([$userId]);
            
        } catch (Exception $e) {
            error_log("Anonymize user content error: " . $e->getMessage());
        }
    }
    
    /**
     * Notify user of Time Removal
     */
    private function notifyTimeRemoval($userId, $durationDays, $reason) {
        $title = 'Account Suspended - Time Removal';
        $message = "Your account has been suspended for {$durationDays} days due to: {$reason}. ";
        $message .= "During this time, you cannot post, message, or interact with content. ";
        $message .= "Your account will be automatically reactivated on " . date('F j, Y', strtotime("+{$durationDays} days")) . ".";
        
        // Send via SYSTEM notification
        $this->sendSystemNotification($userId, $title, $message);
    }
    
    /**
     * Notify user of Lockdown
     */
    private function notifyLockdown($userId, $ruleId, $reason) {
        $title = 'Account in Lockdown Mode';
        $message = "Your account has been placed in lockdown for violating rule {$ruleId}: {$reason}. ";
        $message .= "You can only view content. To restore full access, you must submit an appeal explaining your behavior. ";
        $message .= "Account deletion is disabled during lockdown.";
        
        $this->sendSystemNotification($userId, $title, $message);
    }
    
    /**
     * Notify user of Lockdown release
     */
    private function notifyLockdownReleased($userId, $notes) {
        $title = 'Lockdown Removed - Account Restored';
        $message = "Your appeal has been reviewed and accepted. Your account lockdown has been removed and you can now access all features. ";
        if ($notes) {
            $message .= "Admin notes: {$notes}";
        }
        
        $this->sendSystemNotification($userId, $title, $message);
    }
    
    /**
     * Notify user of Appeal Denied
     */
    public function notifyAppealDenied($userId, $notes = '') {
        $title = 'Appeal Denied - Lockdown Remains Active';
        $message = 'Your appeal was reviewed and denied. Your account remains in lockdown mode.';
        if ($notes) {
            $message .= " Admin notes: {$notes}";
        }
        
        $this->sendSystemNotification($userId, $title, $message);
    }
    
    /**
     * Send SYSTEM notification to user
     */
    private function sendSystemNotification($userId, $title, $message) {
        try {
            // Check if user is Community or Paid
            $stmt = $this->db->prepare("SELECT account_tier FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $tier = $stmt->fetchColumn();
            
            if ($tier === 'community') {
                // Community Accounts: WebSocket only (handled by SYSTEM Tray)
                // No database storage
                return;
            }
            
            // Paid Accounts: Store in database (persistent notification)
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, sender_id, is_system, created_at)
                VALUES (?, 'system', ?, ?, 0, TRUE, NOW())
            ");
            $stmt->execute([$userId, $title, $message]);
            
        } catch (Exception $e) {
            error_log("Send SYSTEM notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Get punishment statistics for admin dashboard
     */
    public function getPunishmentStats($days = 30) {
        try {
            $stats = [
                'time_removals' => 0,
                'lockdowns' => 0,
                'terminations' => 0,
                'currently_suspended' => 0,
                'currently_in_lockdown' => 0,
                'pending_appeals' => 0
            ];
            
            // Time removals in period
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_strikes 
                WHERE tier_applied = 2 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['time_removals'] = $stmt->fetchColumn();
            
            // Lockdowns in period
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_strikes 
                WHERE tier_applied = 2 AND violation_type IN ('NSFW/Adult Content', 'Doxxing')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['lockdowns'] = $stmt->fetchColumn();
            
            // Terminations in period
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_strikes 
                WHERE tier_applied = 3 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['terminations'] = $stmt->fetchColumn();
            
            // Currently suspended
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM users 
                WHERE account_status = 'suspended'
            ");
            $stats['currently_suspended'] = $stmt->fetchColumn();
            
            // Currently in lockdown
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM users 
                WHERE account_status = 'restricted' AND lockdown_mode = TRUE
            ");
            $stats['currently_in_lockdown'] = $stmt->fetchColumn();
            
            // Pending appeals
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM lockdown_appeals 
                WHERE status = 'pending'
            ");
            $stats['pending_appeals'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get punishment stats error: " . $e->getMessage());
            return $stats;
        }
    }
}

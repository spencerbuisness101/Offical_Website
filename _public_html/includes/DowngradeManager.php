<?php
/**
 * Downgrade Manager - Account Tier Downgrade System
 * 
 * Handles downgrading Paid Accounts to Community Accounts:
 * 1. Voluntary downgrade (user initiated)
 * 2. Parental revocation (parent initiated via VPC)
 * 
 * Both trigger full anonymization:
 * - Posts/messages → "[Deleted User]"
 * - Personal data purged after 7-day grace period
 * - 30-day cooldown before re-upgrade
 */

require_once __DIR__ . '/db.php';

class DowngradeManager {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Process account downgrade
     * 
     * @param int $userId User to downgrade
     * @param string $initiatedBy 'user' or 'parent_revocation'
     * @param string $reason Optional reason
     * @return array Result
     */
    public function downgradeAccount($userId, $initiatedBy = 'user', $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Get user info before making changes
            $stmt = $this->db->prepare("
                SELECT username, email, account_tier, account_status 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            if ($user['account_tier'] === 'community') {
                return ['success' => false, 'message' => 'Account is already Community tier.'];
            }
            
            // 1. Hash email before clearing (for ban list)
            $pepper = $_ENV['PEPPER_SECRET'] ?? '';
            $emailHash = hash('sha256', ($user['email'] ?? '') . $pepper);
            
            // 2. Anonymize all posts
            $stmt = $this->db->prepare("
                UPDATE posts 
                SET user_id = NULL, username = '[Deleted User]' 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $postsAnonymized = $stmt->rowCount();
            
            // 3. Anonymize all comments
            $stmt = $this->db->prepare("
                UPDATE comments 
                SET user_id = NULL, username = '[Deleted User]' 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $commentsAnonymized = $stmt->rowCount();
            
            // 4. Anonymize all messages (keep content but remove sender identity)
            $stmt = $this->db->prepare("
                UPDATE messages 
                SET sender_id = NULL, sender_name = '[Deleted User]' 
                WHERE sender_id = ?
            ");
            $stmt->execute([$userId]);
            $messagesAnonymized = $stmt->rowCount();
            
            // 5. Clear user profile data
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_tier = 'community',
                    username = CONCAT('[Deleted_', ?, ']'),
                    email = NULL,
                    email_hash = ?,
                    bio = NULL,
                    location = NULL,
                    website = NULL,
                    avatar_url = NULL,
                    reupgrade_blocked_until = DATE_ADD(NOW(), INTERVAL 30 DAY),
                    can_message = FALSE,
                    can_post = FALSE,
                    has_profile = FALSE,
                    data_purge_scheduled_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                    downgrade_initiated_by = ?,
                    downgrade_reason = ?,
                    downgraded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId, $emailHash, $initiatedBy, $reason, $userId]);
            
            // 6. Log the downgrade
            $stmt = $this->db->prepare("
                INSERT INTO account_downgrades 
                (user_id, from_tier, to_tier, initiated_by, reason, reupgrade_eligible_at, subscription_canceled_at, created_at)
                VALUES (?, 'paid', 'community', ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW())
            ");
            $stmt->execute([$userId, $initiatedBy, $reason]);
            
            // 7. Cancel any active subscriptions (would integrate with payment processor)
            $this->cancelSubscription($userId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Account downgraded successfully.',
                'anonymized' => [
                    'posts' => $postsAnonymized,
                    'comments' => $commentsAnonymized,
                    'messages' => $messagesAnonymized
                ],
                'reupgrade_available' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Downgrade error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to downgrade account: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if user can downgrade
     */
    public function canDowngrade($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT account_tier, account_status, reupgrade_blocked_until 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['can_downgrade' => false, 'reason' => 'User not found'];
            }
            
            if ($user['account_tier'] === 'community') {
                return ['can_downgrade' => false, 'reason' => 'Already Community tier'];
            }
            
            if ($user['account_status'] === 'terminated') {
                return ['can_downgrade' => false, 'reason' => 'Account is terminated'];
            }
            
            // Check if currently in lockdown
            if ($user['account_status'] === 'restricted') {
                return ['can_downgrade' => false, 'reason' => 'Cannot downgrade while in lockdown'];
            }
            
            return ['can_downgrade' => true, 'reason' => null];
            
        } catch (Exception $e) {
            return ['can_downgrade' => false, 'reason' => 'Error checking eligibility'];
        }
    }
    
    /**
     * Check if user can re-upgrade after cooldown
     */
    public function canReupgrade($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT account_tier, reupgrade_blocked_until 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['account_tier'] !== 'community') {
                return ['can_reupgrade' => false, 'reason' => 'Not a Community Account'];
            }
            
            if ($user['reupgrade_blocked_until'] && strtotime($user['reupgrade_blocked_until']) > time()) {
                $waitDays = ceil((strtotime($user['reupgrade_blocked_until']) - time()) / 86400);
                return [
                    'can_reupgrade' => false, 
                    'reason' => "Must wait {$waitDays} more days",
                    'available_at' => $user['reupgrade_blocked_until']
                ];
            }
            
            return ['can_reupgrade' => true, 'reason' => null];
            
        } catch (Exception $e) {
            return ['can_reupgrade' => false, 'reason' => 'Error checking eligibility'];
        }
    }
    
    /**
     * Schedule data purge (run by cron job)
     */
    public function purgeDowngradedData() {
        try {
            // Find users whose 7-day grace period has ended
            $stmt = $this->db->prepare("
                SELECT id, username FROM users 
                WHERE account_tier = 'community'
                AND data_purge_scheduled_at IS NOT NULL
                AND data_purge_scheduled_at <= NOW()
            ");
            $stmt->execute();
            $usersToPurge = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $purgedCount = 0;
            
            foreach ($usersToPurge as $user) {
                // Permanently delete sensitive data
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET 
                        password_hash = NULL,
                        ip_logs = NULL,
                        login_history = NULL,
                        session_tokens = NULL,
                        data_purge_scheduled_at = NULL,
                        data_purged_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                $purgedCount++;
            }
            
            return [
                'success' => true,
                'purged_count' => $purgedCount,
                'users' => $usersToPurge
            ];
            
        } catch (Exception $e) {
            error_log("Data purge error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Cancel subscription by delegating to the existing subscription helper.
     * Gracefully falls back to a log entry if the function is unavailable.
     */
    private function cancelSubscription($userId) {
        try {
            $subscriptionFile = __DIR__ . '/subscription.php';
            if (file_exists($subscriptionFile)) {
                require_once $subscriptionFile;
            }

            if (function_exists('cancelSubscription')) {
                $database = new Database();
                $db = $database->getConnection();
                cancelSubscription($db, $userId, 'Account downgraded to Community');
                return true;
            }

            // Fallback: mark inactive in user_premium table directly
            $stmt = $this->db->prepare("
                UPDATE user_premium 
                SET is_premium = FALSE, 
                    subscription_status = 'cancelled',
                    cancelled_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            error_log("Subscription cancelled for user {$userId} (direct DB update)");
            return true;

        } catch (Exception $e) {
            error_log("Subscription cancellation failed for user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get downgrade statistics
     */
    public function getStatistics($days = 30) {
        try {
            $stats = [
                'voluntary_downgrades' => 0,
                'parental_revocations' => 0,
                'total_downgrades' => 0,
                'pending_reupgrade' => 0
            ];
            
            // Voluntary downgrades
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM account_downgrades 
                WHERE initiated_by = 'user' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['voluntary_downgrades'] = $stmt->fetchColumn();
            
            // Parental revocations
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM account_downgrades 
                WHERE initiated_by = 'parent_revocation' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['parental_revocations'] = $stmt->fetchColumn();
            
            // Total
            $stats['total_downgrades'] = $stats['voluntary_downgrades'] + $stats['parental_revocations'];
            
            // Pending re-upgrade (still in 30-day cooldown)
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM users 
                WHERE account_tier = 'community'
                AND reupgrade_blocked_until > NOW()
            ");
            $stats['pending_reupgrade'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get downgrade stats error: " . $e->getMessage());
            return $stats;
        }
    }
}

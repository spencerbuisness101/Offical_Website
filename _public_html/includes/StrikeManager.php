<?php
/**
 * Strike Manager - Three-Tier Punishment System
 * 
 * Manages the strike system for Paid Accounts only.
 * Community Accounts cannot receive strikes (no communication features).
 * 
 * THREE-TIER SYSTEM:
 * Tier 1: Verbal Warning - Notification sent, strike recorded, account active
 * Tier 2: Time Removal - Posting/viewing frozen for 3/7/14 days (admin choice)
 * Tier 3: Account Termination - Permanent ban, deletion disabled
 * 
 * STRIKE RESET LOGIC:
 * - Strikes expire after 30 calendar days
 * - Active strike count calculated dynamically using DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
 * - All strikes visible in history, expired ones grayed out
 */

require_once __DIR__ . '/db.php';

class StrikeManager {
    private $db;
    
    // Rule definitions with escalation paths
    const RULES = [
        'A1' => [
            'name' => 'Harassment',
            'category' => 'Respect & Conduct',
            'description' => 'Targeting users with insults, slurs, or intimidation',
            'tier1' => 'Verbal Warning',
            'tier2' => 'Time Removal (Admin choice)',
            'tier3' => 'Termination'
        ],
        'A2' => [
            'name' => 'Hate Speech',
            'category' => 'Respect & Conduct',
            'description' => 'Racism, sexism, homophobia, extremist ideology',
            'tier1' => 'Immediate 5-Day Removal', // Zero tolerance
            'tier2' => 'N/A',
            'tier3' => 'Termination'
        ],
        'B1' => [
            'name' => 'NSFW/Adult Content',
            'category' => 'Content & Safety',
            'description' => 'Explicit nudity or pornography',
            'tier1' => 'Immediate Lockdown Mode',
            'tier2' => 'N/A',
            'tier3' => 'Termination after appeal review'
        ],
        'B2' => [
            'name' => 'Gore/Violent Extremism',
            'category' => 'Content & Safety',
            'description' => 'Real-life gore, animal cruelty, terrorist content',
            'tier1' => 'Immediate Termination', // No warning
            'tier2' => 'N/A',
            'tier3' => 'N/A'
        ],
        'C1' => [
            'name' => 'Doxxing',
            'category' => 'Security & Privacy',
            'description' => 'Posting another user\'s personal info without consent',
            'tier1' => 'Immediate Lockdown Mode',
            'tier2' => 'N/A',
            'tier3' => 'Termination after appeal review'
        ],
        'C2' => [
            'name' => 'Impersonation',
            'category' => 'Security & Privacy',
            'description' => 'Pretending to be Staff, Moderator, or another user',
            'tier1' => 'Warning + Forced Name Change',
            'tier2' => 'Termination',
            'tier3' => 'N/A'
        ],
        'D1' => [
            'name' => 'Spamming/Flooding',
            'category' => 'Platform Integrity',
            'description' => 'Repetitive messages, excessive CAPS, chain mail',
            'tier1' => 'Warning + Content Removal',
            'tier2' => '3-Day Removal',
            'tier3' => '7-Day Removal'
        ],
        'D2' => [
            'name' => 'Unauthorized Advertising',
            'category' => 'Platform Integrity',
            'description' => 'Unsolicited links to external services',
            'tier1' => 'Warning',
            'tier2' => '3-Day Removal',
            'tier3' => 'Termination'
        ],
        'D3' => [
            'name' => 'Ban Evasion',
            'category' => 'Platform Integrity',
            'description' => 'Creating new account during active Time Removal or Termination',
            'tier1' => 'New account: Immediate Termination, Original: Punishment doubled',
            'tier2' => 'N/A',
            'tier3' => 'N/A'
        ],
        'E1' => [
            'name' => 'Illegal Activity',
            'category' => 'Legal',
            'description' => 'Buying/selling drugs, hacking services, stolen goods',
            'tier1' => 'Immediate Termination',
            'tier2' => 'N/A',
            'tier3' => 'N/A'
        ]
    ];
    
    // Time removal duration options (days)
    const REMOVAL_DURATIONS = [1, 3, 7, 14];
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Count active strikes for a user (within 30 days)
     * 
     * @param int $userId
     * @return int Active strike count
     */
    public function countActiveStrikes($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM user_strikes 
            WHERE user_id = ? 
            AND is_active = TRUE
            AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    }
    
    /**
     * Get all strikes for a user (for history display)
     * 
     * @param int $userId
     * @return array All strikes with is_expired flag
     */
    public function getUserStrikes($userId) {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   CASE 
                       WHEN DATE(s.created_at) < DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                       THEN TRUE 
                       ELSE FALSE 
                   END as is_expired,
                   u.username as applied_by_username
            FROM user_strikes s
            LEFT JOIN users u ON s.applied_by = u.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get strike history for admin view (all users)
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllStrikes($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   u.username as target_username,
                   a.username as applied_by_username
            FROM user_strikes s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN users a ON s.applied_by = a.id
            ORDER BY s.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Determine the punishment tier based on rule and active strike count
     * 
     * @param string $ruleId
     * @param int $activeStrikeCount
     * @return array Punishment details
     */
    public function determinePunishment($ruleId, $activeStrikeCount) {
        $rule = self::RULES[$ruleId] ?? null;
        
        if (!$rule) {
            return [
                'tier' => 1,
                'action' => 'Verbal Warning',
                'description' => 'Warning issued for rule violation'
            ];
        }
        
        // Special handling for zero-tolerance rules
        if ($ruleId === 'A2') {
            // Hate Speech: Immediate 5-Day Removal on first offense
            return [
                'tier' => 2,
                'action' => 'Time Removal',
                'duration_days' => 5,
                'description' => 'Zero tolerance: 5-day removal for hate speech',
                'can_appeal' => true
            ];
        }
        
        if ($ruleId === 'B2' || $ruleId === 'E1') {
            // Gore/Violent Extremism or Illegal Activity: Immediate Termination
            return [
                'tier' => 3,
                'action' => 'Account Termination',
                'description' => 'Immediate termination for ' . $rule['name'],
                'can_appeal' => false
            ];
        }
        
        if ($ruleId === 'B1' || $ruleId === 'C1') {
            // NSFW or Doxxing: Immediate Lockdown Mode
            return [
                'tier' => 2,
                'action' => 'Lockdown Mode',
                'duration_days' => null, // Indefinite until appeal
                'description' => 'Immediate lockdown for ' . $rule['name'],
                'can_appeal' => true,
                'forced_appeal' => true
            ];
        }
        
        if ($ruleId === 'C2') {
            // Impersonation: Warning first, then Termination
            if ($activeStrikeCount === 0) {
                return [
                    'tier' => 1,
                    'action' => 'Verbal Warning + Forced Name Change',
                    'description' => 'Warning issued, username must be changed',
                    'requires_name_change' => true
                ];
            } else {
                return [
                    'tier' => 3,
                    'action' => 'Account Termination',
                    'description' => 'Termination for repeated impersonation',
                    'can_appeal' => false
                ];
            }
        }
        
        if ($ruleId === 'D3') {
            // Ban Evasion: Immediate Termination of new account
            return [
                'tier' => 3,
                'action' => 'Account Termination',
                'description' => 'Ban evasion detected: new account terminated',
                'affect_original' => true,
                'original_account_action' => 'double_punishment',
                'can_appeal' => false
            ];
        }
        
        // Standard tier escalation for A1, D1, D2
        $tier = min($activeStrikeCount + 1, 3);
        
        if ($tier === 1) {
            return [
                'tier' => 1,
                'action' => 'Verbal Warning',
                'description' => 'First offense: Warning issued'
            ];
        } elseif ($tier === 2) {
            $duration = ($ruleId === 'D1') ? 3 : 3; // 3 days for spam/advertising
            return [
                'tier' => 2,
                'action' => 'Time Removal',
                'duration_days' => $duration,
                'description' => "Second offense: {$duration}-day removal",
                'can_appeal' => true
            ];
        } else {
            if ($ruleId === 'D1') {
                return [
                    'tier' => 2,
                    'action' => 'Time Removal',
                    'duration_days' => 7,
                    'description' => 'Third offense: 7-day removal for spamming',
                    'can_appeal' => true
                ];
            } else {
                return [
                    'tier' => 3,
                    'action' => 'Account Termination',
                    'description' => 'Third offense: Account terminated',
                    'can_appeal' => false
                ];
            }
        }
    }
    
    /**
     * Apply a strike to a user
     * 
     * @param int $userId User receiving the strike
     * @param string $ruleId Rule violated (A1, B2, etc.)
     * @param string $evidence Evidence/description of violation
     * @param int $appliedBy Admin user ID applying the strike
     * @param int|null $customDuration Custom Time Removal duration (for moderator discretion)
     * @return array Result with success status and applied punishment
     */
    public function applyStrike($userId, $ruleId, $evidence, $appliedBy, $customDuration = null) {
        try {
            // Validate user is not an admin
            $stmt = $this->db->prepare("SELECT role, account_tier FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            // Cannot strike admins
            if ($user['role'] === 'admin') {
                return ['success' => false, 'message' => 'Cannot apply strikes to admin accounts.'];
            }
            
            // Cannot strike Community Accounts (they have no communication features)
            if ($user['account_tier'] === 'community') {
                return ['success' => false, 'message' => 'Community Accounts cannot receive strikes.'];
            }
            
            // Get active strike count before applying new strike
            $activeStrikes = $this->countActiveStrikes($userId);
            
            // Determine punishment
            $punishment = $this->determinePunishment($ruleId, $activeStrikes);
            
            // Allow admin override for Time Removal duration (moderator discretion)
            if ($punishment['tier'] === 2 && $customDuration !== null && in_array($customDuration, self::REMOVAL_DURATIONS)) {
                $punishment['duration_days'] = $customDuration;
                $punishment['description'] .= " (Admin discretion: {$customDuration} days)";
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Insert strike record
            $stmt = $this->db->prepare("
                INSERT INTO user_strikes 
                (user_id, rule_id, violation_type, evidence, applied_by, tier_applied, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())
            ");
            $stmt->execute([
                $userId,
                $ruleId,
                self::RULES[$ruleId]['name'] ?? 'Unknown',
                $evidence,
                $appliedBy,
                $punishment['tier']
            ]);
            
            $strikeId = $this->db->lastInsertId();
            
            // Apply punishment
            $punishmentResult = $this->applyPunishment($userId, $punishment, $strikeId);
            
            if (!$punishmentResult['success']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $punishmentResult['message']];
            }
            
            // Send notification to user
            $this->notifyUserOfStrike($userId, $ruleId, $punishment);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Strike applied successfully.',
                'strike_id' => $strikeId,
                'punishment' => $punishment,
                'active_strikes_after' => $this->countActiveStrikes($userId)
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Apply strike error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while applying the strike.'];
        }
    }
    
    /**
     * Apply the actual punishment to the user
     */
    private function applyPunishment($userId, $punishment, $strikeId) {
        try {
            switch ($punishment['action']) {
                case 'Verbal Warning':
                case 'Verbal Warning + Forced Name Change':
                    // Just the strike record - no account restrictions
                    // If name change required, flag it
                    if (!empty($punishment['requires_name_change'])) {
                        $stmt = $this->db->prepare("
                            UPDATE users 
                            SET requires_name_change = TRUE 
                            WHERE id = ?
                        ");
                        $stmt->execute([$userId]);
                    }
                    break;
                    
                case 'Time Removal':
                    // Suspend account for specified duration
                    $restrictionUntil = date('Y-m-d H:i:s', strtotime("+{$punishment['duration_days']} days"));
                    $stmt = $this->db->prepare("
                        UPDATE users 
                        SET account_status = 'suspended',
                            restriction_until = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$restrictionUntil, $userId]);
                    break;
                    
                case 'Lockdown Mode':
                    // Immediate lockdown - view-only, forced appeal
                    $stmt = $this->db->prepare("
                        UPDATE users 
                        SET account_status = 'restricted',
                            in_lockdown = TRUE,
                            lockdown_reason = ?,
                            lockdown_strike_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$punishment['description'], $strikeId, $userId]);
                    break;
                    
                case 'Account Termination':
                    // Delegate to PunishmentManager so ban list is always populated
                    require_once __DIR__ . '/PunishmentManager.php';
                    $punMgr = new PunishmentManager();
                    $punMgr->applyTermination(
                        $userId,
                        $punishment['description'],
                        $strikeId,
                        !empty($punishment['affect_original'])
                    );
                    break;
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Apply punishment error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to apply punishment.'];
        }
    }
    
    /**
     * Send notification to user about strike
     */
    private function notifyUserOfStrike($userId, $ruleId, $punishment) {
        try {
            require_once __DIR__ . '/NotificationManager.php';
            $notifier = new NotificationManager();
            
            $rule = self::RULES[$ruleId] ?? ['name' => 'Unknown Rule'];
            
            $title = 'Strike Applied - ' . $rule['name'];
            $message = "You have received a strike for violating rule {$ruleId}: {$rule['name']}. ";
            $message .= "Punishment: {$punishment['action']}. ";
            
            if ($punishment['tier'] === 1) {
                $message .= "This is a warning. Further violations may result in account restrictions.";
            } elseif ($punishment['tier'] === 2) {
                if (!empty($punishment['duration_days'])) {
                    $message .= "Your account is suspended for {$punishment['duration_days']} days.";
                } else {
                    $message .= "Your account is in lockdown mode. You must submit an appeal.";
                }
            } else {
                $message .= "Your account has been permanently terminated.";
            }
            
            // Send via appropriate channel (Smail for Paid Accounts)
            $notifier->sendSystemNotification($userId, $title, $message);
            
        } catch (Exception $e) {
            error_log("Failed to send strike notification: " . $e->getMessage());
            // Don't fail the strike application if notification fails
        }
    }
    
    /**
     * Get rule definitions for admin interface
     */
    public static function getRules() {
        return self::RULES;
    }
    
    /**
     * Get a specific rule definition
     */
    public static function getRule($ruleId) {
        return self::RULES[$ruleId] ?? null;
    }
    
    /**
     * Check if a user is eligible for Time Removal (not already terminated)
     */
    public function canApplyTimeRemoval($userId) {
        $stmt = $this->db->prepare("SELECT account_status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return in_array($user['account_status'], ['active', 'suspended']);
    }
    
    /**
     * Reactivate account after Time Removal expires (called by cron job)
     */
    public function reactivateExpiredSuspensions() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username FROM users 
                WHERE account_status = 'suspended' 
                AND restriction_until <= NOW()
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reactivated = 0;
            
            foreach ($users as $user) {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET account_status = 'active',
                        restriction_until = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                // Notify user
                $this->notifyReactivation($user['id']);
                
                $reactivated++;
            }
            
            return [
                'success' => true,
                'reactivated_count' => $reactivated,
                'users' => $users
            ];
            
        } catch (Exception $e) {
            error_log("Reactivate expired suspensions error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Notify user of reactivation
     */
    private function notifyReactivation($userId) {
        try {
            require_once __DIR__ . '/NotificationManager.php';
            $notifier = new NotificationManager();
            
            $notifier->sendSystemNotification(
                $userId,
                'Account Reactivated',
                'Your account suspension has ended. You can now access all features again. Please follow our community guidelines to avoid future strikes.'
            );
        } catch (Exception $e) {
            error_log("Failed to send reactivation notification: " . $e->getMessage());
        }
    }
    
    /**
     * Get statistics for admin dashboard
     */
    public function getStatistics($days = 30) {
        try {
            // Total strikes in period
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM user_strikes 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $totalStrikes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Strikes by rule
            $stmt = $this->db->prepare("
                SELECT rule_id, COUNT(*) as count 
                FROM user_strikes 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY rule_id
                ORDER BY count DESC
            ");
            $stmt->execute([$days]);
            $strikesByRule = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Active strikes (currently counting toward escalation)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM user_strikes 
                WHERE is_active = TRUE 
                AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $activeStrikes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Users with strikes
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT user_id) as count 
                FROM user_strikes 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $usersWithStrikes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            return [
                'total_strikes' => $totalStrikes,
                'active_strikes' => $activeStrikes,
                'users_with_strikes' => $usersWithStrikes,
                'strikes_by_rule' => $strikesByRule
            ];
            
        } catch (Exception $e) {
            error_log("Get strike statistics error: " . $e->getMessage());
            return [
                'total_strikes' => 0,
                'active_strikes' => 0,
                'users_with_strikes' => 0,
                'strikes_by_rule' => []
            ];
        }
    }
}

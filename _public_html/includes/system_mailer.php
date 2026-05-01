<?php
/**
 * SYSTEM Account Automated Mailer - Spencer's Website v7.0
 * Handles automated moderation notifications and strike enforcement
 * 
 * SYSTEM account (User ID: 0) - Cannot receive messages, only sends
 */

if (!defined('APP_RUNNING')) {
    die('Direct access forbidden - SYSTEM_MAILER');
}

/**
 * Send a notification from the SYSTEM account to a user via Smail
 * 
 * @param PDO $db Database connection
 * @param int $userId Recipient user ID
 * @param string $type Notification type (SECURITY_ALERT, STRIKE_APPLIED, etc.)
 * @param string $title Message title/subject
 * @param string $message Message body
 * @return bool Success status
 */
function sendSystemNotification($db, $userId, $type, $title, $message) {
    // SYSTEM account ID is always 0
    $systemId = 0;
    
    // Ensure smail_messages table exists
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS smail_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message_body TEXT NOT NULL,
            color_code VARCHAR(7) DEFAULT '#ef4444',
            urgency_level ENUM('low','normal','high','urgent') DEFAULT 'urgent',
            read_status BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receiver (receiver_id),
            INDEX idx_sender (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log("SYSTEM mailer: Failed to create smail_messages table: " . $e->getMessage());
        return false;
    }
    
    try {
        // Insert message from SYSTEM
        $stmt = $db->prepare("INSERT INTO smail_messages 
            (sender_id, receiver_id, title, message_body, color_code, urgency_level) 
            VALUES (?, ?, ?, ?, '#ef4444', 'urgent')");
        $stmt->execute([$systemId, $userId, '[SYSTEM] ' . $title, $message]);
        
        return true;
    } catch (PDOException $e) {
        error_log("SYSTEM mailer: Failed to send notification to user {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Apply a strike to a user and send notification
 * 
 * @param PDO $db Database connection
 * @param int $userId User to apply strike to
 * @param string $ruleId Rule code (A1, B2, etc.)
 * @param string $evidence Evidence/description of violation
 * @param int $adminId Admin applying the strike (0 for SYSTEM)
 * @return array Strike details including punishment applied
 */
function applyStrike($db, $userId, $ruleId, $evidence, $adminId = 0) {
    // Get current active strike count for this rule (within 30 days)
    // Use DATE() to compare dates only, preventing midnight boundary issues
    $stmt = $db->prepare("SELECT COUNT(*) as strikes FROM user_strikes 
                          WHERE user_id = ? AND rule_id = ? 
                          AND is_active = TRUE 
                          AND DATE(created_at) > DATE(DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $stmt->execute([$userId, $ruleId]);
    $strikeCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['strikes'] + 1;
    
    // Determine punishment based on rule and strike count
    $punishment = calculatePunishment($ruleId, $strikeCount);
    
    // Calculate expiration (30 days from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    try {
        // Record strike
        $stmt = $db->prepare("INSERT INTO user_strikes 
            (user_id, rule_id, strike_number, evidence, applied_by, 
             punishment_type, punishment_duration, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId, 
            $ruleId, 
            $strikeCount, 
            $evidence, 
            $adminId,
            $punishment['type'],
            $punishment['duration'],
            $expiresAt
        ]);
        
        $strikeId = $db->lastInsertId();
        
        // Update user's current strike count
        $stmt = $db->prepare("UPDATE users SET 
            current_strike_count = (SELECT COUNT(*) FROM user_strikes WHERE user_id = ? AND is_active = TRUE),
            last_strike_at = NOW()
            WHERE id = ?");
        $stmt->execute([$userId, $userId]);
        
        // Send SYSTEM notification
        $ruleDescriptions = [
            'A1' => 'Harassment',
            'A2' => 'Hate Speech',
            'B1' => 'NSFW/Adult Content',
            'B2' => 'Gore/Violent Extremism',
            'C1' => 'Doxxing',
            'C2' => 'Impersonation',
            'D1' => 'Spamming',
            'D2' => 'Unauthorized Advertising',
            'D3' => 'Ban Evasion',
            'E1' => 'Illegal Activity'
        ];
        
        $ruleName = $ruleDescriptions[$ruleId] ?? $ruleId;
        
        $notificationMessage = "A strike has been applied to your account.\n\n" .
            "Rule Violated: {$ruleId} - {$ruleName}\n" .
            "Strike Count: {$strikeCount} of 3\n" .
            "Punishment: " . strtoupper($punishment['type']) . "\n";
            
        if ($punishment['duration'] > 0) {
            $notificationMessage .= "Duration: {$punishment['duration']} days\n";
        }
        
        $notificationMessage .= "\nEvidence/Reason: {$evidence}\n\n" .
            "This strike will expire on {$expiresAt} if no further violations occur.\n" .
            "If you believe this strike was applied in error, you may appeal within 14 days via Smail to an administrator.\n\n" .
            "Note: Strikes reset after 30 days of clean behavior.";
        
        sendSystemNotification(
            $db,
            $userId,
            'STRIKE_APPLIED',
            "Strike Applied: {$ruleId} ({$strikeCount}/3)",
            $notificationMessage
        );
        
        // Apply punishment to user account
        applyPunishment($db, $userId, $punishment);
        
        return [
            'success' => true,
            'strike_id' => $strikeId,
            'strike_number' => $strikeCount,
            'punishment' => $punishment,
            'expires_at' => $expiresAt
        ];
        
    } catch (PDOException $e) {
        error_log("SYSTEM mailer: Failed to apply strike to user {$userId}: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Database error while applying strike'
        ];
    }
}

/**
 * Calculate punishment based on rule and strike count
 * 
 * @param string $ruleId Rule code
 * @param int $strikeCount Current strike number (1-3)
 * @return array Punishment details
 */
function calculatePunishment($ruleId, $strikeCount) {
    // Define escalation patterns for each rule
    $patterns = [
        // A1 - Harassment
        'A1' => [
            1 => ['type' => 'warning', 'duration' => 0],
            2 => ['type' => 'suspension', 'duration' => 5],
            3 => ['type' => 'termination', 'duration' => 0]
        ],
        // A2 - Hate Speech (zero tolerance - skip warning)
        'A2' => [
            1 => ['type' => 'suspension', 'duration' => 5],
            2 => ['type' => 'suspension', 'duration' => 14],
            3 => ['type' => 'termination', 'duration' => 0]
        ],
        // B1 - NSFW (immediate lockdown)
        'B1' => [
            1 => ['type' => 'lockdown', 'duration' => 0]
        ],
        // B2 - Gore/Extremism (immediate termination)
        'B2' => [
            1 => ['type' => 'termination', 'duration' => 0]
        ],
        // C1 - Doxxing (immediate lockdown)
        'C1' => [
            1 => ['type' => 'lockdown', 'duration' => 0]
        ],
        // C2 - Impersonation
        'C2' => [
            1 => ['type' => 'warning', 'duration' => 0],
            2 => ['type' => 'termination', 'duration' => 0]
        ],
        // D1 - Spamming
        'D1' => [
            1 => ['type' => 'warning', 'duration' => 0],
            2 => ['type' => 'suspension', 'duration' => 3],
            3 => ['type' => 'suspension', 'duration' => 7]
        ],
        // D2 - Unauthorized Advertising
        'D2' => [
            1 => ['type' => 'warning', 'duration' => 0],
            2 => ['type' => 'suspension', 'duration' => 3],
            3 => ['type' => 'termination', 'duration' => 0]
        ],
        // D3 - Ban Evasion (termination)
        'D3' => [
            1 => ['type' => 'termination', 'duration' => 0]
        ],
        // E1 - Illegal Activity (immediate termination)
        'E1' => [
            1 => ['type' => 'termination', 'duration' => 0]
        ]
    ];
    
    // Get pattern for rule, default to warning
    $pattern = $patterns[$ruleId] ?? [1 => ['type' => 'warning', 'duration' => 0]];
    
    // Get punishment for current strike, or use last defined if beyond
    if (isset($pattern[$strikeCount])) {
        return $pattern[$strikeCount];
    }
    
    // If no specific rule for this strike count, use the highest defined
    $maxStrike = max(array_keys($pattern));
    return $pattern[$maxStrike];
}

/**
 * Apply punishment to user account
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param array $punishment Punishment details
 * @return bool Success status
 */
function applyPunishment($db, $userId, $punishment) {
    try {
        switch ($punishment['type']) {
            case 'warning':
                // No account changes needed for warning
                return true;
                
            case 'suspension':
                $suspendedUntil = date('Y-m-d H:i:s', strtotime("+{$punishment['duration']} days"));
                // Write BOTH old and new schema columns so all code paths stay in sync
                $stmt = $db->prepare("UPDATE users SET 
                    status = 'suspended',
                    account_status = 'suspended',
                    restriction_until = ?,
                    is_suspended = 1,
                    suspended_until = ? 
                    WHERE id = ?");
                $stmt->execute([$suspendedUntil, $suspendedUntil, $userId]);
                return true;
                
            case 'lockdown':
                // Create sanitized public reason (never expose sensitive admin details)
                $publicReason = 'Violation of Content Policy requiring administrative review';
                $stmt = $db->prepare("UPDATE users SET 
                    status = 'restricted',
                    account_status = 'restricted',
                    lockdown_mode = TRUE,
                    lockdown_reason = ?,
                    lockdown_reason_public = ?,
                    lockdown_at = NOW(),
                    can_delete_account = FALSE
                    WHERE id = ?");
                $stmt->execute([$evidence, $publicReason, $userId]);
                return true;
                
            case 'termination':
                // Delegate to PunishmentManager to ensure ban list is populated
                require_once __DIR__ . '/PunishmentManager.php';
                $punMgr = new PunishmentManager();
                $punMgr->applyTermination($userId, 'Community Standards violation', 0);
                return true;
                
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("SYSTEM mailer: Failed to apply punishment to user {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Send login notification for new device/location
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param string $ipAddress IP address
 * @param string $userAgent User agent string
 * @return bool Success status
 */
function sendLoginNotification($db, $userId, $ipAddress, $userAgent) {
    // Get user info
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return false;
    }
    
    $time = date('Y-m-d H:i:s T');
    $userAgentShort = substr($userAgent, 0, 50) . (strlen($userAgent) > 50 ? '...' : '');
    
    $message = "Your account was accessed from a new device or location.\n\n" .
        "Time: {$time}\n" .
        "IP Address: {$ipAddress}\n" .
        "Device: {$userAgentShort}\n\n" .
        "If this was you, no action is needed.\n" .
        "If you don't recognize this activity, change your password immediately:\n" .
        "1. Go to Settings > Security\n" .
        "2. Change your password\n" .
        "3. Contact support if you need assistance\n\n" .
        "This is an automated security alert from SYSTEM.";
    
    return sendSystemNotification(
        $db,
        $userId,
        'SECURITY_ALERT',
        'New Login Detected',
        $message
    );
}

/**
 * Check if IP/user agent combination is new for user
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param string $ipAddress IP address
 * @param string $userAgent User agent
 * @return bool True if this is a new device/location
 */
function isNewDevice($db, $userId, $ipAddress, $userAgent) {
    // Use DATE() for consistent date-only comparison
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_login_history 
                          WHERE user_id = ? AND ip_address = ? AND user_agent = ? 
                          AND DATE(created_at) > DATE(DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $stmt->execute([$userId, $ipAddress, $userAgent]);
    return ($stmt->fetchColumn() == 0);
}

/**
 * Log login attempt and send notification if new device
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param string $ipAddress IP address
 * @param string $userAgent User agent
 * @return void
 */
function logLoginAndNotify($db, $userId, $ipAddress, $userAgent) {
    // Check if this is a new device
    $isNew = isNewDevice($db, $userId, $ipAddress, $userAgent);
    
    // Log the login
    $stmt = $db->prepare("INSERT INTO user_login_history 
        (user_id, ip_address, user_agent, is_new_device, notification_sent) 
        VALUES (?, ?, ?, ?, ?)");
    
    if ($isNew) {
        // Send notification for new device
        $notificationSent = sendLoginNotification($db, $userId, $ipAddress, $userAgent);
        $stmt->execute([$userId, $ipAddress, $userAgent, true, $notificationSent]);
    } else {
        $stmt->execute([$userId, $ipAddress, $userAgent, false, false]);
    }
}

/**
 * Get user's active strikes
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @return array Array of strike records
 */
function getUserStrikes($db, $userId) {
    $stmt = $db->prepare("SELECT s.*, 
                          CASE 
                            WHEN s.rule_id LIKE 'A%' THEN 'Respect & Conduct'
                            WHEN s.rule_id LIKE 'B%' THEN 'Content & Safety'
                            WHEN s.rule_id LIKE 'C%' THEN 'Security & Privacy'
                            WHEN s.rule_id LIKE 'D%' THEN 'Platform Integrity'
                            WHEN s.rule_id LIKE 'E%' THEN 'Legal'
                            ELSE 'Other'
                          END as category
                          FROM user_strikes s
                          WHERE s.user_id = ? AND s.is_active = TRUE
                          ORDER BY s.created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Expire old strikes (after 30 days)
 * 
 * @param PDO $db Database connection
 * @return int Number of strikes expired
 */
function expireOldStrikes($db) {
    $stmt = $db->prepare("UPDATE user_strikes SET is_active = FALSE 
                          WHERE is_active = TRUE AND expires_at < NOW()");
    $stmt->execute();
    $expired = $stmt->rowCount();
    
    if ($expired > 0) {
        // Recalculate strike counts for affected users
        $stmt = $db->prepare("UPDATE users u 
            SET current_strike_count = (
                SELECT COUNT(*) FROM user_strikes s 
                WHERE s.user_id = u.id AND s.is_active = TRUE
            )");
        $stmt->execute();
    }
    
    return $expired;
}

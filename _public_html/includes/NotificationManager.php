<?php
/**
 * Notification Manager v2.0 - Spencer's Website
 * Handles notifications with SYSTEM account special handling
 * SYSTEM notifications are infinite and not rate-limited
 */

class NotificationManager {
    private $db;
    private $rateLimiterEnabled = true;
    
    // SYSTEM account is exempt from all limits
    const SYSTEM_USER_ID = 0;
    
    // Rate limits for regular users
    const RATE_LIMIT_PER_MINUTE = 10;
    const RATE_LIMIT_PER_HOUR = 100;
    const RATE_LIMIT_PER_DAY = 500;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Send a notification
     * SYSTEM user (ID: 0) bypasses ALL rate limits
     */
    public function sendNotification(int $userId, string $type, string $title, string $message, array $data = [], int $senderId = null): array {
        try {
            // Validate recipient exists
            if (!$this->userExists($userId)) {
                return ['success' => false, 'error' => 'Recipient user not found'];
            }
            
            // Check rate limits (SYSTEM bypasses all limits)
            if ($senderId !== self::SYSTEM_USER_ID && $this->rateLimiterEnabled) {
                $rateCheck = $this->checkRateLimit($senderId ?? $userId);
                if (!$rateCheck['allowed']) {
                    return ['success' => false, 'error' => 'Rate limit exceeded: ' . $rateCheck['reason']];
                }
            }
            
            // Insert notification
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, data, sender_id, created_at, is_read, is_system)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), FALSE, ?)
            ");
            
            $isSystem = ($senderId === self::SYSTEM_USER_ID) ? 1 : 0;
            $jsonData = json_encode($data);
            
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $jsonData,
                $senderId,
                $isSystem
            ]);
            
            $notificationId = $this->db->lastInsertId();
            
            // Update user's unread count cache
            $this->updateUnreadCount($userId);
            
            // Log system notifications for audit
            if ($isSystem) {
                error_log("[SYSTEM NOTIFICATION] ID: {$notificationId} | User: {$userId} | Type: {$type} | Title: {$title}");
            }
            
            return [
                'success' => true,
                'notification_id' => $notificationId,
                'is_system' => (bool)$isSystem
            ];
            
        } catch (PDOException $e) {
            error_log("NotificationManager Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Send bulk notifications (SYSTEM only - no limits)
     */
    public function sendBulkNotifications(array $userIds, string $type, string $title, string $message, array $data = [], int $senderId = null): array {
        // Only SYSTEM can send bulk without limits
        if ($senderId !== self::SYSTEM_USER_ID) {
            return ['success' => false, 'error' => 'Bulk notifications require SYSTEM sender'];
        }
        
        $results = [
            'success' => true,
            'total' => count($userIds),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($userIds as $userId) {
            $result = $this->sendNotification($userId, $type, $title, $message, $data, $senderId);
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "User {$userId}: " . $result['error'];
            }
        }
        
        return $results;
    }
    
    /**
     * Send notification to all users (SYSTEM only)
     */
    public function broadcastNotification(string $type, string $title, string $message, array $data = [], array $excludeUserIds = []): array {
        try {
            // Get all active user IDs
            $placeholders = implode(',', array_fill(0, count($excludeUserIds), '?'));
            $sql = "SELECT id FROM users WHERE status = 'active'";
            if (!empty($excludeUserIds)) {
                $sql .= " AND id NOT IN ({$placeholders})";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($excludeUserIds);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $this->sendBulkNotifications($userIds, $type, $title, $message, $data, self::SYSTEM_USER_ID);
            
        } catch (PDOException $e) {
            error_log("Broadcast Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get notifications for a user
     * Includes infinite SYSTEM notifications
     */
    public function getNotifications(int $userId, int $limit = 50, int $offset = 0, bool $unreadOnly = false): array {
        try {
            $sql = "
                SELECT 
                    n.*,
                    u.username as sender_username,
                    u.role as sender_role
                FROM notifications n
                LEFT JOIN users u ON n.sender_id = u.id
                WHERE n.user_id = ?
            ";
            
            if ($unreadOnly) {
                $sql .= " AND n.is_read = FALSE";
            }
            
            // SYSTEM notifications never expire and are always included
            $sql .= " ORDER BY n.is_system DESC, n.created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $limit, $offset]);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON data
            foreach ($notifications as &$notification) {
                $notification['data'] = json_decode($notification['data'], true);
                $notification['is_system'] = (bool)$notification['is_system'];
            }
            
            return [
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications),
                'has_more' => count($notifications) === $limit
            ];
            
        } catch (PDOException $e) {
            error_log("Get Notifications Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get unread count for a user
     */
    public function getUnreadCount(int $userId): int {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Unread Count Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
            // Update cache
            $this->updateUnreadCount($userId);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Mark Read Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW()
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            
            // Update cache
            $this->updateUnreadCount($userId);
            
            return true;
        } catch (PDOException $e) {
            error_log("Mark All Read Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete old non-system notifications
     * SYSTEM notifications are NEVER deleted
     */
    public function cleanupOldNotifications(int $daysOld = 30): array {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE is_system = FALSE 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND is_read = TRUE
            ");
            $stmt->execute([$daysOld]);
            
            $deleted = $stmt->rowCount();
            error_log("[NOTIFICATION CLEANUP] Deleted {$deleted} old notifications");
            
            return ['success' => true, 'deleted_count' => $deleted];
        } catch (PDOException $e) {
            error_log("Cleanup Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Check rate limit for regular users
     * SYSTEM bypasses all limits
     */
    private function checkRateLimit(int $userId): array {
        // SYSTEM is always allowed
        if ($userId === self::SYSTEM_USER_ID) {
            return ['allowed' => true];
        }
        
        try {
            // Define valid intervals (whitelist) to prevent SQL injection
            $validIntervals = ['MINUTE', 'HOUR', 'DAY'];
            $checks = [
                ['interval' => 'MINUTE', 'limit' => self::RATE_LIMIT_PER_MINUTE],
                ['interval' => 'HOUR', 'limit' => self::RATE_LIMIT_PER_HOUR],
                ['interval' => 'DAY', 'limit' => self::RATE_LIMIT_PER_DAY]
            ];
            
            foreach ($checks as $check) {
                // Validate interval against whitelist
                if (!in_array($check['interval'], $validIntervals, true)) {
                    continue; // Skip invalid intervals
                }
                
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM notifications 
                    WHERE sender_id = ? 
                    AND sender_id != ?
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 {$check['interval']})
                ");
                $stmt->execute([$userId, self::SYSTEM_USER_ID]);
                $count = (int)$stmt->fetchColumn();
                
                if ($count >= $check['limit']) {
                    return [
                        'allowed' => false,
                        'reason' => "Exceeded {$check['limit']} notifications per {$check['interval']}"
                    ];
                }
            }
            
            return ['allowed' => true];
            
        } catch (PDOException $e) {
            error_log("Rate Limit Check Error: " . $e->getMessage());
            return ['allowed' => false, 'reason' => 'Rate limit check failed'];
        }
    }
    
    /**
     * Update unread count cache
     */
    private function updateUnreadCount(int $userId): void {
        try {
            $count = $this->getUnreadCount($userId);
            
            // Store in session for quick access
            if (isset($_SESSION)) {
                $_SESSION['unread_notifications_count'] = $count;
            }
            
            // Could also store in Redis/Memcached for better performance
        } catch (Exception $e) {
            error_log("Update Unread Count Error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if user exists
     */
    private function userExists(int $userId): bool {
        // SYSTEM always exists
        if ($userId === self::SYSTEM_USER_ID) {
            return true;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Create notifications table if it doesn't exist
     */
    public function createTable(): bool {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT,
                    data JSON,
                    sender_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_read BOOLEAN DEFAULT FALSE,
                    read_at TIMESTAMP NULL,
                    is_system BOOLEAN DEFAULT FALSE,
                    INDEX idx_user_read (user_id, is_read),
                    INDEX idx_created_at (created_at),
                    INDEX idx_sender (sender_id),
                    INDEX idx_system (is_system)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Create Table Error: " . $e->getMessage());
            return false;
        }
    }
}

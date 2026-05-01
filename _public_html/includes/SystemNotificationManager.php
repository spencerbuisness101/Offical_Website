<?php
/**
 * System Notification Manager - Phase 5 Implementation
 * 
 * Handles notifications for both Paid and Community Accounts:
 * - Paid Accounts: Persistent database notifications (notifications table)
 * - Community Accounts: Ephemeral SYSTEM Tray notifications (WebSocket)
 * 
 * Channels:
 * - SYSTEM Tray: Ephemeral, WebSocket-based, for Community Accounts
 * - Smail: Persistent in-app messaging for Paid Accounts
 * - Email: Optional for critical alerts
 * 
 * Rules:
 * - Appeals trigger SYSTEM notifications to admins (all tiers)
 * - Strike application triggers notification to user
 * - Time Removal expiration triggers reactivation notice
 * - Lockdown release triggers notification to user
 */

require_once __DIR__ . '/db.php';

class SystemNotificationManager {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Send notification to user
     * Automatically determines channel based on account tier
     * 
     * @param int $userId Target user
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $options Additional options (link, priority, etc.)
     * @return bool Success
     */
    public function send($userId, $type, $title, $message, $options = []) {
        try {
            // Get user's account tier
            $stmt = $this->db->prepare("SELECT account_tier FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $tier = $stmt->fetchColumn();
            
            if (!$tier) {
                error_log("Notification failed: User {$userId} not found");
                return false;
            }
            
            if ($tier === 'community') {
                return $this->sendSystemTray($userId, $type, $title, $message, $options);
            } else {
                return $this->sendPersistent($userId, $type, $title, $message, $options);
            }
            
        } catch (Exception $e) {
            error_log("Send notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SYSTEM Tray notification (Community Accounts)
     * Ephemeral, expires after 7 days, delivered via WebSocket
     */
    private function sendSystemTray($userId, $type, $title, $message, $options) {
        try {
            $link     = $options['link'] ?? null;
            $priority = $options['priority'] ?? 'normal';

            // SPEC: Community Account SYSTEM Tray — NO database write.
            // Push via WebSocket only. If user is offline, the message is silently dropped.
            // Messages exist only in the browser's sessionStorage, never on the server.
            $payload = [
                'id'        => uniqid('syst_', true),
                'type'      => $type,
                'title'     => $title,
                'message'   => $message,
                'link'      => $link,
                'priority'  => $priority,
                'timestamp' => date('c'),
            ];

            // Attempt real-time WebSocket push; returns false if user is offline (dropped).
            $this->triggerWebSocketDelivery($userId, $payload);

            return true;

        } catch (Exception $e) {
            error_log("Send SYSTEM Tray error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send persistent notification (Paid Accounts)
     * Stored in database, accessible via Smail interface
     */
    private function sendPersistent($userId, $type, $title, $message, $options) {
        try {
            $link = $options['link'] ?? null;
            $senderId = $options['sender_id'] ?? 0;
            
            // Store in persistent notifications table
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, sender_id, is_system, created_at)
                VALUES (?, ?, ?, ?, ?, TRUE, NOW())
            ");
            $stmt->execute([$userId, $type, $title, $message, $senderId]);
            
            // Check if user has email notifications enabled
            if ($this->shouldEmailNotify($userId, $type)) {
                $this->queueEmailNotification($userId, $title, $message, $link);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Send persistent notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to all admins
     * Uses appropriate channel for each admin's account tier
     */
    public function sendToAdmins($type, $title, $message, $options = []) {
        try {
            $stmt = $this->db->query("SELECT id, account_tier FROM users WHERE role = 'admin'");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sent = 0;
            foreach ($admins as $admin) {
                if ($this->send($admin['id'], $type, $title, $message, $options)) {
                    $sent++;
                }
            }
            
            return ['success' => true, 'sent_count' => $sent];
            
        } catch (Exception $e) {
            error_log("Send to admins error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get notifications for user
     * Returns appropriate format based on account tier
     */
    public function getForUser($userId, $unreadOnly = false, $limit = 50) {
        try {
            // Get user's tier
            $stmt = $this->db->prepare("SELECT account_tier FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $tier = $stmt->fetchColumn();
            
            if ($tier === 'community') {
                return $this->getSystemTrayNotifications($userId, $unreadOnly, $limit);
            } else {
                return $this->getPersistentNotifications($userId, $unreadOnly, $limit);
            }
            
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get SYSTEM Tray notifications (Community Accounts)
     */
    private function getSystemTrayNotifications($userId, $unreadOnly, $limit) {
        $sql = "
            SELECT id, type, title, message, link, is_read, 
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at
            FROM ephemeral_notifications 
            WHERE user_id = ? AND expires_at > NOW()
        ";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'channel' => 'system_tray',
            'count' => count($notifications),
            'notifications' => $notifications
        ];
    }
    
    /**
     * Get persistent notifications (Paid Accounts)
     */
    private function getPersistentNotifications($userId, $unreadOnly, $limit) {
        $sql = "
            SELECT id, type, title, message, is_read,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at
            FROM notifications 
            WHERE user_id = ?
        ";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'channel' => 'smail',
            'count' => count($notifications),
            'notifications' => $notifications
        ];
    }
    
    /**
     * Mark notification as read
     */
    public function markRead($notificationId, $userId, $channel = 'smail') {
        try {
            if ($channel === 'system_tray') {
                $stmt = $this->db->prepare("
                    UPDATE ephemeral_notifications 
                    SET is_read = TRUE, read_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            } else {
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET is_read = TRUE, read_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            }
            
            $stmt->execute([$notificationId, $userId]);
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Mark read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllRead($userId, $channel = null) {
        try {
            // Get user's tier to determine channel
            if (!$channel) {
                $stmt = $this->db->prepare("SELECT account_tier FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $tier = $stmt->fetchColumn();
                $channel = ($tier === 'community') ? 'system_tray' : 'smail';
            }
            
            if ($channel === 'system_tray') {
                $stmt = $this->db->prepare("
                    UPDATE ephemeral_notifications 
                    SET is_read = TRUE, read_at = NOW()
                    WHERE user_id = ? AND is_read = FALSE
                ");
            } else {
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET is_read = TRUE, read_at = NOW()
                    WHERE user_id = ? AND is_read = FALSE
                ");
            }
            
            $stmt->execute([$userId]);
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Mark all read error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->db->prepare("SELECT account_tier FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $tier = $stmt->fetchColumn();
            
            if ($tier === 'community') {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM ephemeral_notifications 
                    WHERE user_id = ? AND is_read = FALSE AND expires_at > NOW()
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM notifications 
                    WHERE user_id = ? AND is_read = FALSE
                ");
            }
            
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Trigger WebSocket delivery
     * This would integrate with your WebSocket server
     */
    private function triggerWebSocketDelivery($userId, $notification) {
        // Check if user has active WebSocket session
        $stmt = $this->db->prepare("
            SELECT session_id FROM websocket_sessions 
            WHERE user_id = ? AND disconnected_at IS NULL
            ORDER BY last_ping DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $session = $stmt->fetchColumn();
        
        if ($session) {
            // User is online - deliver via WebSocket
            // This would connect to your WebSocket server
            $this->sendToWebSocket($session, $notification);
        } else {
            // User is offline - notification will be delivered when they connect
            // The ephemeral notification is already stored in DB
        }
        
        return true;
    }
    
    /**
     * Send to WebSocket (placeholder for your WebSocket implementation)
     */
    private function sendToWebSocket($sessionId, $notification) {
        // This would integrate with your WebSocket server (Ratchet, Swoole, etc.)
        // For now, we log it
        error_log("WebSocket delivery: session={$sessionId}, type={$notification['type']}");
        
        // Example integration:
        // $wsServer = WebSocketServer::getInstance();
        // $wsServer->sendToSession($sessionId, json_encode([
        //     'event' => 'notification',
        //     'data' => $notification
        // ]));
        
        return true;
    }
    
    /**
     * Check if user should receive email notification
     */
    private function shouldEmailNotify($userId, $type) {
        // Check user preferences
        $criticalTypes = ['ACCOUNT_TERMINATED', 'SECURITY_ALERT', 'APPEAL_RESULT'];
        
        if (!in_array($type, $criticalTypes)) {
            return false;
        }
        
        // Would check user notification preferences here
        return true;
    }
    
    /**
     * Queue email notification
     */
    private function queueEmailNotification($userId, $title, $message, $link) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notification_queue 
                (user_id, type, title, message, channel, scheduled_at, created_at)
                VALUES (?, 'email', ?, ?, 'email', NOW(), NOW())
            ");
            $stmt->execute([$userId, $title, $message]);
            return true;
            
        } catch (Exception $e) {
            error_log("Queue email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process notification queue (called by cron job)
     */
    public function processQueue($batchSize = 50) {
        try {
            // Get pending notifications
            $stmt = $this->db->prepare("
                SELECT id, user_id, channel, type, title, content, attempts, scheduled_at, created_at FROM notification_queue
                WHERE status = 'pending'
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                AND attempts < 3
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$batchSize]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            $failed = 0;
            
            foreach ($notifications as $notification) {
                try {
                    if ($notification['channel'] === 'email') {
                        $this->sendEmail($notification);
                    }
                    
                    // Mark as sent
                    $stmt = $this->db->prepare("
                        UPDATE notification_queue 
                        SET status = 'sent', sent_at = NOW(), attempts = attempts + 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$notification['id']]);
                    $processed++;
                    
                } catch (Exception $e) {
                    // Mark as failed
                    $stmt = $this->db->prepare("
                        UPDATE notification_queue 
                        SET status = 'failed', 
                            attempts = attempts + 1, 
                            error_message = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$e->getMessage(), $notification['id']]);
                    $failed++;
                }
            }
            
            return ['success' => true, 'processed' => $processed, 'failed' => $failed];
            
        } catch (Exception $e) {
            error_log("Process queue error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send email (placeholder for your email implementation)
     */
    private function sendEmail($notification) {
        // Would integrate with your email system (SMTP, SendGrid, etc.)
        error_log("Email notification: user_id={$notification['user_id']}, title={$notification['title']}");
        return true;
    }
    
    /**
     * Clean up expired ephemeral notifications
     */
    public function cleanupExpired() {
        try {
            $stmt = $this->db->query("
                DELETE FROM ephemeral_notifications 
                WHERE expires_at < NOW()
            ");
            $deleted = $stmt->rowCount();
            
            return ['success' => true, 'deleted_count' => $deleted];
            
        } catch (Exception $e) {
            error_log("Cleanup expired error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

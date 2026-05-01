<?php
/**
 * Smail Helper Functions — Spencer's Website v7.0
 *
 * Provides cached unread message counts to reduce DB load on every page render.
 * The identity bar calls getUnreadSmailCount() which caches in $_SESSION with a TTL.
 * When the user visits the smail page, the cache is invalidated to ensure freshness.
 */

if (!function_exists('getUnreadSmailCount')) {
    /**
     * Get unread smail message count for a user.
     * Uses session cache with 30-second TTL to avoid DB query on every page load.
     *
     * @param PDO $db Database connection
     * @param int $userId User ID
     * @param int $ttlSeconds Cache TTL (default 30s)
     * @return int Unread message count
     */
    function getUnreadSmailCount(PDO $db, int $userId, int $ttlSeconds = 30): int {
        // Session key format
        $cacheKey = 'smail_unread_count_' . $userId;
        $timestampKey = $cacheKey . '_ts';

        // Check if we have a fresh cached value
        if (isset($_SESSION[$cacheKey], $_SESSION[$timestampKey])) {
            $age = time() - $_SESSION[$timestampKey];
            if ($age < $ttlSeconds) {
                return (int)$_SESSION[$cacheKey];
            }
        }

        // Cache miss or stale — query DB
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE receiver_id = ? AND read_status = FALSE");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();

            // Store in session with timestamp
            $_SESSION[$cacheKey] = $count;
            $_SESSION[$timestampKey] = time();

            return $count;
        } catch (Exception $e) {
            error_log("getUnreadSmailCount error: " . $e->getMessage());
            // Return cached value even if stale, or 0 if no cache
            return (int)($_SESSION[$cacheKey] ?? 0);
        }
    }
}

if (!function_exists('invalidateSmailCache')) {
    /**
     * Invalidate the unread smail count cache for a user.
     * Call this when: messages are read/sent/deleted, or user visits smail page.
     *
     * @param int $userId User ID (defaults to current session user)
     * @return void
     */
    function invalidateSmailCache(?int $userId = null): void {
        $userId = $userId ?? ($_SESSION['user_id'] ?? 0);
        if (!$userId) return;

        $cacheKey = 'smail_unread_count_' . $userId;
        $timestampKey = $cacheKey . '_ts';

        unset($_SESSION[$cacheKey], $_SESSION[$timestampKey]);
    }
}

if (!function_exists('markSmailReadAndInvalidate')) {
    /**
     * Mark smail messages as read and invalidate cache atomically.
     *
     * @param PDO $db Database connection
     * @param int $userId User ID
     * @param int|array|null $messageIds Specific message ID(s) or null for all
     * @return int Number of messages marked as read
     */
    function markSmailReadAndInvalidate(PDO $db, int $userId, $messageIds = null): int {
        try {
            if ($messageIds === null) {
                // Mark all as read
                $stmt = $db->prepare("UPDATE smail_messages SET read_status = TRUE WHERE receiver_id = ? AND read_status = FALSE");
                $stmt->execute([$userId]);
            } elseif (is_array($messageIds)) {
                // Mark specific IDs as read
                $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
                $stmt = $db->prepare("UPDATE smail_messages SET read_status = TRUE WHERE receiver_id = ? AND id IN ($placeholders) AND read_status = FALSE");
                $stmt->execute(array_merge([$userId], $messageIds));
            } else {
                // Single ID
                $stmt = $db->prepare("UPDATE smail_messages SET read_status = TRUE WHERE receiver_id = ? AND id = ? AND read_status = FALSE");
                $stmt->execute([$userId, $messageIds]);
            }

            $affected = $stmt->rowCount();

            // Invalidate cache so next load is fresh
            invalidateSmailCache($userId);

            return $affected;
        } catch (Exception $e) {
            error_log("markSmailReadAndInvalidate error: " . $e->getMessage());
            return 0;
        }
    }
}

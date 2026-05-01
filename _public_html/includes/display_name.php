<?php
/**
 * Display Name Helper - Spencer's Website v7.0
 * Returns nickname if set, otherwise username.
 * Used globally for dynamic greetings and user display.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Get display name for a user.
 * @param int|array $userIdOrData — user ID (int) or pre-fetched user row (array with 'nickname' and 'username')
 * @return string
 */
function getDisplayName($userIdOrData): string {
    if (is_array($userIdOrData)) {
        return !empty($userIdOrData['nickname']) ? $userIdOrData['nickname'] : ($userIdOrData['username'] ?? 'User');
    }

    // If session has the current user's data cached, use it
    if (is_int($userIdOrData) || is_numeric($userIdOrData)) {
        $userId = (int) $userIdOrData;

        // Fast path: if it's the current session user
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            if (!empty($_SESSION['nickname'])) return $_SESSION['nickname'];
            return $_SESSION['username'] ?? 'User';
        }

        // DB lookup
        try {
            if (file_exists(__DIR__ . '/../config/database.php')) {
                require_once __DIR__ . '/../config/database.php';
                if (class_exists('Database')) {
                    $db = (new Database())->getConnection();
                    // Try with nickname column first, fall back to username-only if column missing
                    try {
                        $stmt = $db->prepare("SELECT nickname, username FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            return !empty($row['nickname']) ? $row['nickname'] : $row['username'];
                        }
                    } catch (Exception $colErr) {
                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) return $row['username'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("getDisplayName error: " . $e->getMessage());
        }
    }

    return 'User';
}

/**
 * Get the current session user's display name (fast, no DB query).
 * @return string
 */
function getCurrentDisplayName(): string {
    if (!empty($_SESSION['nickname'])) return $_SESSION['nickname'];
    return $_SESSION['username'] ?? 'User';
}

<?php
/**
 * Active Sessions API
 * Returns active user sessions - requires admin authentication
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json');

// Require admin role for session access
requireApiRole('admin');

try {
    $db = (new Database())->getConnection();

    $stmt = $db->query("
        SELECT
            us.session_id,
            u.username,
            us.current_page as page,
            us.last_activity,
            us.ip_address,
            FROM_UNIXTIME(us.last_activity) as last_activity_formatted
        FROM user_sessions us
        LEFT JOIN users u ON us.user_id = u.id
        WHERE us.last_activity > UNIX_TIMESTAMP() - 300
        ORDER BY us.last_activity DESC
        LIMIT 20
    ");

    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for display
    $formatted = [];
    foreach ($sessions as $session) {
        $formatted[] = [
            'username' => $session['username'] ?? 'Guest',
            'page' => basename($session['page'] ?? ''),
            'time' => date('H:i', $session['last_activity'] ?? time()),
            'ip' => $session['ip_address'] ?? '',
            'last_activity' => $session['last_activity_formatted'] ?? ''
        ];
    }

    echo json_encode(['success' => true, 'sessions' => $formatted]);

} catch (Exception $e) {
    error_log("Get active sessions error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve sessions']);
}
?>
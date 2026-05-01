<?php
/**
 * System Logs API
 * Returns system logs - requires admin authentication
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json');

// Require admin role for log access
requireApiRole('admin');

try {
    $db = (new Database())->getConnection();

    $stmt = $db->query("
        SELECT
            l.id, l.channel, l.level, l.message, l.context,
            l.request_id, l.user_id, l.ip, l.uri, l.method, l.created_at,
            u.username
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 100
    ");

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'logs' => $logs]);

} catch (Exception $e) {
    error_log("Get logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve logs']);
}
?>
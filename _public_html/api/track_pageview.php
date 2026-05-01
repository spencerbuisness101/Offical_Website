<?php
/**
 * Page View Tracking API
 * Tracks page views with authenticated user ID
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Get authenticated user ID (optional - allows anonymous tracking)
    $userId = authenticateApiRequest(false);

    try {
        $db = (new Database())->getConnection();

        $stmt = $db->prepare("
            INSERT INTO page_views (user_id, page_url, session_id)
            VALUES (:user_id, :page_url, :session_id)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':page_url' => $input['page_url'] ?? '',
            ':session_id' => $input['session_id'] ?? ''
        ]);

        // Update user session
        $stmt = $db->prepare("
            INSERT INTO user_sessions
            (session_id, user_id, ip_address, user_agent, last_activity, current_page, page_views)
            VALUES (
                :session_id,
                :user_id,
                :ip_address,
                :user_agent,
                UNIX_TIMESTAMP(),
                :current_page,
                1
            )
            ON DUPLICATE KEY UPDATE
                last_activity = UNIX_TIMESTAMP(),
                current_page = :current_page,
                page_views = page_views + 1
        ");

        $stmt->execute([
            ':session_id' => $input['session_id'] ?? '',
            ':user_id' => $userId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ':current_page' => $input['page_url'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Page view tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
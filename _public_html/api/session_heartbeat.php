<?php
/**
 * Session Heartbeat API
 * Updates session activity with authenticated user ID
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
            UPDATE user_sessions
            SET last_activity = UNIX_TIMESTAMP(), current_page = :current_page
            WHERE session_id = :session_id
        ");

        $stmt->execute([
            ':session_id' => $input['session_id'] ?? '',
            ':current_page' => $input['current_page'] ?? ''
        ]);

        // If no rows affected, insert new session
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("
                INSERT INTO user_sessions
                (session_id, user_id, ip_address, user_agent, last_activity, current_page)
                VALUES (:session_id, :user_id, :ip_address, :user_agent, UNIX_TIMESTAMP(), :current_page)
            ");

            $stmt->execute([
                ':session_id' => $input['session_id'] ?? '',
                ':user_id' => $userId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':current_page' => $input['current_page'] ?? ''
            ]);
        }

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Session heartbeat error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
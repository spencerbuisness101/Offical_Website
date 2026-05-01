<?php
/**
 * Performance Tracking API
 * Tracks page performance with authenticated user ID
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
            INSERT INTO performance_metrics (page_url, load_time, user_id, session_id)
            VALUES (:page_url, :load_time, :user_id, :session_id)
        ");

        $stmt->execute([
            ':page_url' => $input['page_url'] ?? '',
            ':load_time' => $input['load_time'] ?? 0,
            ':user_id' => $userId,
            ':session_id' => $input['session_id'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Performance tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
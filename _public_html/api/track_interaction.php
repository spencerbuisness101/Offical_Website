<?php
/**
 * Interaction Tracking API
 * Tracks user interactions with authenticated user ID
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
            INSERT INTO user_interactions
            (user_id, page_url, interaction_type, x_coordinate, y_coordinate, element, session_id)
            VALUES (:user_id, :page_url, :interaction_type, :x, :y, :element, :session_id)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':page_url' => $input['page_url'] ?? '',
            ':interaction_type' => $input['interaction_type'] ?? 'click',
            ':x' => $input['x'] ?? 0,
            ':y' => $input['y'] ?? 0,
            ':element' => $input['element'] ?? '',
            ':session_id' => $input['session_id'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Interaction tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
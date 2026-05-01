<?php
/**
 * Game Tracking API
 * Tracks game plays with authenticated user ID
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

        $game_name = basename($input['game'] ?? '', '.php');

        $stmt = $db->prepare("
            INSERT INTO game_analytics (game_name, user_id, session_id, action_type)
            VALUES (:game_name, :user_id, :session_id, :action_type)
        ");

        $stmt->execute([
            ':game_name' => $game_name,
            ':user_id' => $userId,
            ':session_id' => $input['session_id'] ?? '',
            ':action_type' => $input['action'] ?? 'view'
        ]);

        // Also log the feature usage
        $stmt = $db->prepare("
            INSERT INTO feature_tracking (feature_name, user_id, session_id)
            VALUES (:feature_name, :user_id, :session_id)
        ");

        $stmt->execute([
            ':feature_name' => 'game_' . $game_name,
            ':user_id' => $userId,
            ':session_id' => $input['session_id'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Game tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
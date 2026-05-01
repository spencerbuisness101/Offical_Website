<?php
/**
 * Feature Tracking API
 * Tracks feature usage with authenticated user ID
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/suspension_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

requireNotSuspended(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Get authenticated user ID (optional - allows anonymous tracking)
    $userId = authenticateApiRequest(false);

    try {
        $db = (new Database())->getConnection();

        $stmt = $db->prepare("
            INSERT INTO feature_tracking (feature_name, user_id, session_id)
            VALUES (:feature_name, :user_id, :session_id)
        ");

        $stmt->execute([
            ':feature_name' => $input['feature'] ?? '',
            ':user_id' => $userId,
            ':session_id' => $input['session_id'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Feature tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
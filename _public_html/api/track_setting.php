<?php
/**
 * Setting Tracking API
 * Tracks setting changes with authenticated user ID
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

        // Log setting change
        $stmt = $db->prepare("
            INSERT INTO system_logs (level, message, user_id, ip_address, user_agent)
            VALUES ('info', :message, :user_id, :ip_address, :user_agent)
        ");

        $setting = $input['setting'] ?? 'unknown';
        $value = $input['value'] ?? '';
        $message = "User changed setting: {$setting} to {$value}";

        $stmt->execute([
            ':message' => $message,
            ':user_id' => $userId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log("Setting tracking error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
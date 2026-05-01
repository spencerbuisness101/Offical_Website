<?php
/**
 * Game Report API - Spencer's Website v7.0
 * Allows users to flag broken game assets directly to system_logs.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$gameName = trim($_POST['game_name'] ?? '');
$issueType = trim($_POST['issue_type'] ?? 'broken');
$description = trim($_POST['description'] ?? '');

if (empty($gameName)) {
    echo json_encode(['success' => false, 'error' => 'Game name is required']);
    exit;
}
if (strlen($description) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Description must be under 1000 characters']);
    exit;
}

$allowedTypes = ['broken', 'slow', 'audio', 'visual', 'crash', 'other'];
if (!in_array($issueType, $allowedTypes)) {
    $issueType = 'other';
}

try {
    $db = (new Database())->getConnection();

    $db->exec("CREATE TABLE IF NOT EXISTS game_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        game_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(50) DEFAULT 'broken',
        description TEXT,
        status ENUM('open','reviewed','resolved','dismissed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_game (game_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $db->prepare("INSERT INTO game_reports (user_id, game_name, issue_type, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $gameName, $issueType, $description]);

    error_log("GAME REPORT: User {$userId} reported '{$gameName}' - type: {$issueType}");

    echo json_encode(['success' => true, 'message' => 'Report submitted! An admin will review it.']);
} catch (Exception $e) {
    error_log("Game report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to submit report. Please try again.']);
}

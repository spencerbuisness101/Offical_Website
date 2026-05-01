<?php
/**
 * Smail Unread Count API - Spencer's Website v7.0
 * Returns unread Smail count as JSON for AJAX polling.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['count' => 0]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'community';

if ($role === 'community') {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE receiver_id = ? AND read_status = FALSE");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['count' => $count]);
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}

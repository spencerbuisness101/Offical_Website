<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Not logged in']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}
$announcementId = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
if (!$announcementId) {
    http_response_code(400);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Invalid announcement ID']));
}
header('Content-Type: application/json');
try {
    $db = db();
    $stmt = $db->prepare("SELECT 1 FROM user_announcements WHERE user_id = ? AND announcement_id = ?");
    $stmt->execute([$_SESSION['user_id'], $announcementId]);
    if ($stmt->rowCount() === 0) {
        $stmt = $db->prepare("INSERT INTO user_announcements (user_id, announcement_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $announcementId]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark announcement read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

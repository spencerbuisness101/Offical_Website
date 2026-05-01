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
$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['announcement_ids'] ?? [];
if (empty($ids)) {
    http_response_code(400);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'No announcement IDs provided']));
}
header('Content-Type: application/json');
try {
    $db = db();
    foreach ($ids as $id) {
        $stmt = $db->prepare("SELECT 1 FROM user_announcements WHERE user_id = ? AND announcement_id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("INSERT INTO user_announcements (user_id, announcement_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $id]);
        }
    }
    echo json_encode(['success' => true, 'count' => count($ids)]);
} catch (Exception $e) {
    error_log("Mark all announcements read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

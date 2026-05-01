<?php
/**
 * User Notes API — Spencer's Website v7.0
 * CRUD for admin notes on user accounts
 */
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false]); exit;
}

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS admin_user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = intval($_GET['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success' => false]); exit; }
    $stmt = $db->prepare("SELECT n.*, u.username as admin_name FROM admin_user_notes n LEFT JOIN users u ON n.admin_id = u.id WHERE n.user_id = ? ORDER BY n.created_at DESC");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'notes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
}

switch ($action) {
    case 'add':
    default:
        $userId = intval($_POST['user_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if (!$userId || !$note) { echo json_encode(['success' => false, 'message' => 'Missing data']); exit; }
        $stmt = $db->prepare("INSERT INTO admin_user_notes (user_id, admin_id, note) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $_SESSION['user_id'] ?? 0, $note]);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $noteId = intval($_POST['note_id'] ?? 0);
        if (!$noteId) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("DELETE FROM admin_user_notes WHERE id = ?");
        $stmt->execute([$noteId]);
        echo json_encode(['success' => true]);
        break;
}

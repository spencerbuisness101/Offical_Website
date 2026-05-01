<?php
/**
 * Chat Action API — Spencer's Website v7.0
 * Delete messages, mute users
 */
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false]); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'delete':
        $msgId = intval($_POST['message_id'] ?? 0);
        if (!$msgId) { echo json_encode(['success' => false]); exit; }
        // Try both table names
        foreach (['chat_messages', 'yaps_messages'] as $table) {
            try {
                $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$msgId]);
                if ($stmt->rowCount() > 0) break;
            } catch (Exception $e) {}
        }
        try {
            $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'delete_chat_message', ?, ?)");
            $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Message ID: $msgId", $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        break;

    case 'mute':
        $userId = intval($_POST['user_id'] ?? 0);
        if (!$userId) { echo json_encode(['success' => false]); exit; }
        // Add a 3-day chat ban via access_restrictions
        $expiresAt = date('Y-m-d H:i:s', time() + 259200);
        $stmt = $db->prepare("INSERT INTO access_restrictions (type, value, reason, created_by) VALUES ('chat_mute', ?, '3-day chat mute by admin', ?)");
        $stmt->execute([$userId, $_SESSION['username'] ?? 'admin']);
        try {
            $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, 'mute_user', ?, '3-day chat mute', ?)");
            $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', $userId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {}
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

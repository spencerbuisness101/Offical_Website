<?php
/**
 * User Analytics API — Spencer's Website v7.0
 * Deep user profiles, IP lookups, chat viewing, admin actions
 */
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$action = $_POST['action'] ?? '';

// GET requests for data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = intval($_GET['user_id'] ?? 0);
    $ipLookup = $_GET['ip_lookup'] ?? '';
    $chatId = intval($_GET['chat_id'] ?? 0);
    $smailId = intval($_GET['smail_id'] ?? 0);

    // User profile data
    if ($userId) {
        try {
            $stmt = $db->prepare("SELECT id, username, role, is_suspended, created_at, last_login FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) { echo json_encode(['success' => false]); exit; }

            $stmt = $db->prepare("SELECT ip_address, current_page, last_activity FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC LIMIT 10");
            $stmt->execute([$userId]); $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT amount_cents, plan_type, status, ip_address, created_at FROM payment_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$userId]); $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT n.note, n.created_at, u.username as admin_name FROM admin_user_notes n LEFT JOIN users u ON n.admin_id = u.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 20");
            $stmt->execute([$userId]); $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'user' => $user, 'sessions' => $sessions, 'payments' => $payments, 'notes' => $notes]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit;
    }

    // IP lookup
    if ($ipLookup) {
        try {
            $stmt = $db->prepare("SELECT ps.amount_cents, ps.status, ps.created_at, u.username FROM payment_sessions ps LEFT JOIN users u ON ps.user_id = u.id WHERE ps.ip_address = ? ORDER BY ps.created_at DESC LIMIT 20");
            $stmt->execute([$ipLookup]); $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT DISTINCT u.id, u.username, u.role FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.ip_address = ? LIMIT 20");
            $stmt->execute([$ipLookup]); $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'transactions' => $transactions, 'accounts' => $accounts]);
        } catch (Exception $e) { echo json_encode(['success' => false]); }
        exit;
    }

    // Chat messages
    if ($chatId) {
        try {
            $stmt = $db->prepare("SELECT c.id, c.user_id, c.title, c.personality_id, c.created_at, c.updated_at, u.username FROM ai_chats c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ?");
            $stmt->execute([$chatId]); $chat = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT id, chat_id, role, content, created_at FROM ai_messages WHERE chat_id = ? ORDER BY created_at ASC LIMIT 100");
            $stmt->execute([$chatId]); $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'chat' => $chat, 'messages' => $messages]);
        } catch (Exception $e) { echo json_encode(['success' => false]); }
        exit;
    }

    // Smail message
    if ($smailId) {
        try {
            $stmt = $db->prepare("SELECT s.id, s.sender_id, s.receiver_id, s.title, s.message_body, s.color_code, s.urgency_level, s.read_status, s.created_at, su.username as sender_name, ru.username as receiver_name FROM smail_messages s LEFT JOIN users su ON s.sender_id = su.id LEFT JOIN users ru ON s.receiver_id = ru.id WHERE s.id = ?");
            $stmt->execute([$smailId]); $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) { echo json_encode(['success' => false]); }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'No query parameter']);
    exit;
}

// POST actions
$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF mismatch']);
    exit;
}

switch ($action) {
    case 'suspend':
        $uid = intval($_POST['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("SELECT is_suspended FROM users WHERE id = ?"); $stmt->execute([$uid]);
        $current = $stmt->fetchColumn();
        $newVal = $current ? 0 : 1;
        $stmt = $db->prepare("UPDATE users SET is_suspended = ? WHERE id = ?"); $stmt->execute([$newVal, $uid]);
        logAdminAction($db, $newVal ? 'suspend_user' : 'unsuspend_user', $uid);
        echo json_encode(['success' => true, 'message' => $newVal ? 'Suspended' : 'Unsuspended']);
        break;

    case 'force_logout':
        $uid = intval($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?"); $stmt->execute([$uid]);
        logAdminAction($db, 'force_logout', $uid);
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        break;

    case 'kill_session':
        $sid = $_POST['session_id'] ?? '';
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_id = ?"); $stmt->execute([$sid]);
        logAdminAction($db, 'kill_session', null, "Session: $sid");
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $uid = intval($_POST['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?"); $stmt->execute([$uid]);
        logAdminAction($db, 'delete_user', $uid);
        echo json_encode(['success' => true, 'message' => 'Account deleted']);
        break;

    case 'delete_chat':
        $cid = intval($_POST['chat_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM ai_messages WHERE chat_id = ?"); $stmt->execute([$cid]);
        $stmt = $db->prepare("DELETE FROM ai_chats WHERE id = ?"); $stmt->execute([$cid]);
        logAdminAction($db, 'delete_ai_chat', null, "Chat ID: $cid");
        echo json_encode(['success' => true]);
        break;

    case 'delete_smail':
        $sid = intval($_POST['smail_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM smail_messages WHERE id = ?"); $stmt->execute([$sid]);
        logAdminAction($db, 'delete_smail', null, "Smail ID: $sid");
        echo json_encode(['success' => true]);
        break;

    case 'update_idea':
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!$id || !$status) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("UPDATE contributor_ideas SET status = ? WHERE id = ?"); $stmt->execute([$status, $id]);
        logAdminAction($db, 'update_idea', null, "Idea $id → $status");
        echo json_encode(['success' => true]);
        break;

    case 'update_background':
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $stmt = $db->prepare("UPDATE designer_backgrounds SET status = ? WHERE id = ?"); $stmt->execute([$status, $id]);
        logAdminAction($db, 'update_background', null, "Background $id → $status");
        echo json_encode(['success' => true]);
        break;

    case 'pfp_approve':
    case 'pfp_decline':
        $uid = intval($_POST['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false]); exit; }
        if ($action === 'pfp_approve') {
            $stmt = $db->prepare("UPDATE users SET pfp_type = 'approved', pfp_url = COALESCE(pfp_pending_url, pfp_url) WHERE id = ?"); $stmt->execute([$uid]);
        } else {
            $stmt = $db->prepare("UPDATE users SET pfp_type = 'declined' WHERE id = ?"); $stmt->execute([$uid]);
        }
        logAdminAction($db, $action, $uid);
        echo json_encode(['success' => true]);
        break;

    case 'add_restriction':
        $type = $_POST['type'] ?? '';
        $value = $_POST['value'] ?? '';
        $reason = $_POST['reason'] ?? '';
        if (!$type || !$value) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("INSERT INTO access_restrictions (type, value, reason, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$type, $value, $reason, $_SESSION['username'] ?? 'admin']);
        logAdminAction($db, 'add_restriction', null, "$type: $value");
        echo json_encode(['success' => true]);
        break;

    case 'delete_restriction':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM access_restrictions WHERE id = ?"); $stmt->execute([$id]);
        logAdminAction($db, 'delete_restriction', null, "Restriction ID: $id");
        echo json_encode(['success' => true]);
        break;

    case 'create_announcement':
        $title = $_POST['title'] ?? '';
        $type = $_POST['type'] ?? 'info';
        $message = $_POST['message'] ?? '';
        if (!$title) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("INSERT INTO announcements (title, type, message, created_by, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$title, $type, $message, $_SESSION['user_id'] ?? 0]);
        logAdminAction($db, 'create_announcement', null, $title);
        echo json_encode(['success' => true]);
        break;

    case 'toggle_announcement':
        $id = intval($_POST['id'] ?? 0);
        $active = intval($_POST['is_active'] ?? 0);
        $stmt = $db->prepare("UPDATE announcements SET is_active = ? WHERE id = ?"); $stmt->execute([$active, $id]);
        logAdminAction($db, 'toggle_announcement', null, "ID: $id → " . ($active ? 'active' : 'inactive'));
        echo json_encode(['success' => true]);
        break;

    case 'delete_announcement':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?"); $stmt->execute([$id]);
        logAdminAction($db, 'delete_announcement', null, "ID: $id");
        echo json_encode(['success' => true]);
        break;

    case 'refund_approve':
    case 'refund_deny':
        $rid = intval($_POST['refund_id'] ?? 0);
        $status = $action === 'refund_approve' ? 'approved' : 'denied';
        $stmt = $db->prepare("UPDATE refund_requests SET status = ? WHERE id = ?"); $stmt->execute([$status, $rid]);
        logAdminAction($db, 'refund_' . $status, null, "Refund ID: $rid");
        echo json_encode(['success' => true, 'message' => 'Refund ' . $status]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}

function logAdminAction($db, $action, $targetId = null, $details = '') {
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'unknown', $action, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

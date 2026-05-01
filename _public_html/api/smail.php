<?php
/**
 * Smail API - Spencer's Website v7.0
 * Internal mail system API. Actions: send, mark_read, delete, get_inbox, get_outbox
 * Rate limits: Community blocked, standard users 25/day, contributor/designer/admin unlimited.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'community';

if ($role === 'community') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Smail is not available for community accounts.']);
    exit;
}

// Time Removal enforcement: suspended users cannot send messages
require_once __DIR__ . '/../includes/suspension_guard.php';
requireNotSuspended(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Auto-create table
    $db->exec("CREATE TABLE IF NOT EXISTS smail_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message_body TEXT NOT NULL,
        color_code VARCHAR(7) DEFAULT '#3b82f6',
        urgency_level ENUM('low','normal','high','urgent') DEFAULT 'normal',
        read_status BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_receiver (receiver_id),
        INDEX idx_sender (sender_id),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit;
}

$action = $_POST['action'] ?? '';

// IP-based rate limiting for send action: 10 messages per minute per IP
if ($action === 'send') {
    enforceIpRateLimit($db, 'smail_send', 10, 60);
}

switch ($action) {

    case 'send':
        $receiverUsername = trim($_POST['receiver'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['message_body'] ?? '');
        $colorCode = $_POST['color_code'] ?? '#3b82f6';
        $urgency = $_POST['urgency_level'] ?? 'normal';

        if (empty($receiverUsername) || empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Recipient, title, and message are required.']);
            exit;
        }
        if (strlen($title) > 255) {
            echo json_encode(['success' => false, 'error' => 'Title must be 255 characters or less.']);
            exit;
        }
        if (strlen($body) > 5000) {
            echo json_encode(['success' => false, 'error' => 'Message must be 5000 characters or less.']);
            exit;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorCode)) {
            $colorCode = '#3b82f6';
        }
        if (!in_array($urgency, ['low', 'normal', 'high', 'urgent'])) {
            $urgency = 'normal';
        }

        // Find receiver
        $stmt = $db->prepare("SELECT id, role FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$receiverUsername]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$receiver) {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }
        // SYSTEM account protection - User ID 0 is reserved for automated moderation
        if ((int)$receiver['id'] === 0) {
            echo json_encode(['success' => false, 'error' => 'SYSTEM is an automated account and does not accept messages. Please visit the Help Center.']);
            exit;
        }
        if ($receiver['role'] === 'community') {
            echo json_encode(['success' => false, 'error' => 'Cannot send Smail to community accounts.']);
            exit;
        }
        if ((int)$receiver['id'] === $userId) {
            echo json_encode(['success' => false, 'error' => 'You cannot send Smail to yourself.']);
            exit;
        }

        // Rate limit: standard users = 25/day, elevated = unlimited
        $elevatedRoles = ['contributor', 'designer', 'admin'];
        if (!in_array($role, $elevatedRoles)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE sender_id = ? AND created_at >= CURDATE()");
            $stmt->execute([$userId]);
            $todaySent = (int)$stmt->fetchColumn();
            if ($todaySent >= 25) {
                echo json_encode(['success' => false, 'error' => 'Daily send limit reached (25/day). Try again tomorrow.']);
                exit;
            }
        }

        $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $stmt = $db->prepare("INSERT INTO smail_messages (sender_id, receiver_id, title, message_body, color_code, urgency_level) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $receiver['id'], $title, $body, $colorCode, $urgency]);

        echo json_encode(['success' => true, 'message' => 'Smail sent!']);
        break;

    case 'get_inbox':
        $stmt = $db->prepare("SELECT sm.id, sm.sender_id, sm.receiver_id, sm.title, sm.message_body, sm.color_code, sm.urgency_level, sm.read_status, sm.created_at, u.username as sender_name FROM smail_messages sm LEFT JOIN users u ON sm.sender_id = u.id WHERE sm.receiver_id = ? ORDER BY sm.created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread = 0;
        foreach ($messages as $m) { if (!$m['read_status']) $unread++; }

        echo json_encode(['success' => true, 'messages' => $messages, 'unread' => $unread]);
        break;

    case 'get_outbox':
        $stmt = $db->prepare("SELECT sm.id, sm.sender_id, sm.receiver_id, sm.title, sm.message_body, sm.color_code, sm.urgency_level, sm.read_status, sm.created_at, u.username as receiver_name FROM smail_messages sm LEFT JOIN users u ON sm.receiver_id = u.id WHERE sm.sender_id = ? ORDER BY sm.created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'mark_read':
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $stmt = $db->prepare("UPDATE smail_messages SET read_status = TRUE WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$msgId, $userId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid message ID.']);
        }
        break;

    case 'mark_unread':
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $stmt = $db->prepare("UPDATE smail_messages SET read_status = FALSE WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$msgId, $userId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid message ID.']);
        }
        break;

    case 'delete':
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $stmt = $db->prepare("DELETE FROM smail_messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
            $stmt->execute([$msgId, $userId, $userId]);
            echo json_encode(['success' => true, 'message' => 'Message deleted.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid message ID.']);
        }
        break;

    case 'search_users':
        $query = trim($_POST['query'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }
        $stmt = $db->prepare("SELECT username FROM users WHERE username LIKE ? AND role != 'community' AND is_active = 1 AND id != ? LIMIT 10");
        $stmt->execute(['%' . $query . '%', $userId]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'users' => $results]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}

<?php
/**
 * Chat Feed API — Spencer's Website v7.0
 * Returns recent chat messages for Live Chat Monitor
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

$limit = min(50, max(1, intval($_GET['limit'] ?? 50)));
$since = $_GET['since'] ?? '';

try {
    // Try chat_messages table first, then yaps_messages
    $tableFound = false;
    foreach (['chat_messages', 'yaps_messages'] as $table) {
        try {
            $check = $db->query("SELECT 1 FROM $table LIMIT 1");
            if ($check) { $tableFound = true; $chatTable = $table; break; }
        } catch (Exception $e) {}
    }

    if (!$tableFound) {
        echo json_encode(['success' => true, 'messages' => []]);
        exit;
    }

    $where = "1=1";
    $params = [];
    if ($since) {
        $where .= " AND m.created_at > ?";
        $params[] = $since;
    }

    $stmt = $db->prepare("SELECT m.id, m.message, m.content, m.created_at, u.username, u.role FROM $chatTable m LEFT JOIN users u ON m.user_id = u.id WHERE $where ORDER BY m.created_at DESC LIMIT ?");
    $params[] = $limit;
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function($m) {
        return [
            'id' => $m['id'],
            'username' => $m['username'] ?? 'Unknown',
            'role' => $m['role'] ?? 'user',
            'message' => $m['message'] ?? $m['content'] ?? '',
            'time' => date('g:i:s A', strtotime($m['created_at']))
        ];
    }, $messages);

    echo json_encode(['success' => true, 'messages' => array_reverse($result)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}

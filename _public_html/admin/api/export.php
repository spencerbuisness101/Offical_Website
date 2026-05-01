<?php
/**
 * Export Engine API — Spencer's Website v7.0
 * Export data tables as CSV or JSON (CSRF-protected)
 */
require_once __DIR__ . '/../../includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo 'Unauthorized'; exit;
}

// CSRF check — accept both POST (with body) and GET (with query param)
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403); echo 'CSRF token mismatch'; exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo 'DB error'; exit;
}

$table = preg_replace('/[^a-z_]/', '', $_GET['table'] ?? $_POST['table'] ?? 'users');
$format = $_GET['format'] ?? $_POST['format'] ?? 'csv';
$userId = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);

$allowedTables = ['users', 'user_sessions', 'payment_sessions', 'donations', 'system_logs', 'admin_audit_log', 'device_fingerprints', 'smail_messages', 'ai_chats', 'ai_messages', 'blocked_ips', 'contributor_ideas', 'designer_backgrounds', 'announcements', 'refund_requests', 'access_restrictions'];

if (!in_array($table, $allowedTables)) {
    echo 'Invalid table'; exit;
}

try {
    $where = "1=1";
    $params = [];
    if ($userId) {
        $userCols = $db->query("SHOW COLUMNS FROM $table LIKE '%user_id%'")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($userCols)) {
            $where = "$userCols[0] = ?";
            $params[] = $userId;
        }
    }

    $stmt = $db->prepare("SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT 10000");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mask password hashes if present in users table
    if ($table === 'users') {
        foreach ($data as &$row) {
            if (isset($row['password'])) $row['password'] = '[REDACTED]';
        }
        unset($row);
    }

    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
    } else {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $row) fputcsv($out, $row);
        }
        fclose($out);
    }

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'export_data', ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Exported $table as $format" . ($userId ? " (user $userId)" : ''), $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
} catch (Exception $e) {
    echo 'Export error: ' . $e->getMessage();
}

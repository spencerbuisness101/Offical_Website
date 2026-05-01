<?php
/**
 * Merge Accounts API — Spencer's Website v7.0
 * Transfer data from source to target account, then terminate source
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

$sourceId = intval($_POST['source_id'] ?? 0);
$targetId = intval($_POST['target_id'] ?? 0);

if (!$sourceId || !$targetId) { echo json_encode(['success' => false, 'message' => 'Missing IDs']); exit; }
if ($sourceId === $targetId) { echo json_encode(['success' => false, 'message' => 'Cannot merge same account']); exit; }

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Verify both accounts exist
    $stmt = $db->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
    $stmt->execute([$sourceId, $targetId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($accounts) !== 2) { echo json_encode(['success' => false, 'message' => 'One or both accounts not found']); exit; }

    // Block merging admin accounts
    foreach ($accounts as $acc) {
        $stmt2 = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt2->execute([$acc['id']]);
        $roleCheck = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($roleCheck && $roleCheck['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot merge admin accounts']); exit;
        }
    }

    $sourceName = $accounts[0]['id'] === $sourceId ? $accounts[0]['username'] : $accounts[1]['username'];
    $targetName = $accounts[0]['id'] === $targetId ? $accounts[0]['username'] : $accounts[1]['username'];

    $db->beginTransaction();

    // Transfer tables that have user_id columns
    $transferTables = [
        'payment_sessions', 'donations', 'ai_chats', 'smail_messages',
        'user_sessions', 'device_fingerprints', 'admin_audit_log',
        'admin_user_notes', 'refund_requests', 'contributor_ideas',
        'designer_backgrounds', 'idea_votes'
    ];

    $transferred = 0;
    foreach ($transferTables as $table) {
        try {
            // Check if table has user_id column
            $cols = $db->query("SHOW COLUMNS FROM $table LIKE 'user_id'")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($cols)) continue;

            $stmt = $db->prepare("UPDATE $table SET user_id = ? WHERE user_id = ?");
            $stmt->execute([$targetId, $sourceId]);
            $transferred += $stmt->rowCount();
        } catch (Exception $e) { continue; }
    }

    // Also transfer sender_id in smail
    try {
        $stmt = $db->prepare("UPDATE smail_messages SET sender_id = ? WHERE sender_id = ?");
        $stmt->execute([$targetId, $sourceId]);
    } catch (Exception $e) {}

    // Terminate source account
    $stmt = $db->prepare("UPDATE users SET is_suspended = 1, username = CONCAT(username, '_merged_', ?) WHERE id = ?");
    $stmt->execute([$targetId, $sourceId]);

    $db->commit();

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, 'merge_accounts', ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', $targetId, "Merged $sourceName ($sourceId) into $targetName ($targetId). $transferred rows transferred.", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => "Merged $transferred rows. Source account terminated."]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

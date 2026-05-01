<?php
/**
 * Impersonate API — Spencer's Website v7.0
 * Start/stop user impersonation (15-min auto-expire)
 */
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'stop') {
    // CSRF check for stop action — no bypass, even for non-AJAX
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
    }

    // End impersonation
    if (!empty($_SESSION['impersonating'])) {
        $impersonatorId = $_SESSION['impersonator_id'] ?? 0;
        // Restore admin session
        $_SESSION['user_id'] = $impersonatorId;
        $_SESSION['role'] = 'admin';
        $_SESSION['impersonating'] = false;
        $_SESSION['impersonator_id'] = null;
        $_SESSION['impersonate_expires'] = null;

        try {
            require_once __DIR__ . '/../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$impersonatorId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['username'] = $admin['username'] ?? 'admin';
            $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, ip_address) VALUES (?, ?, 'impersonate_stop', ?)");
            $stmt->execute([$impersonatorId, $_SESSION['username'], $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {}
    }
    // Return to admin via redirect
    header('Location: admin.php');
    exit;
}

if ($action === 'start') {
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
    }

    // Prevent nested impersonation (admin -> user -> another user escalation)
    if (!empty($_SESSION['impersonating'])) {
        echo json_encode(['success' => false, 'message' => 'Already impersonating. Stop current session first.']);
        exit;
    }

    $userId = intval($_POST['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success' => false, 'message' => 'No user ID']); exit; }

    try {
        require_once __DIR__ . '/../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }

        // Block impersonating admin accounts
        if ($user['role'] === 'admin') { echo json_encode(['success' => false, 'message' => 'Cannot impersonate admin accounts']); exit; }

        // Save current admin info
        $adminId = $_SESSION['user_id'];
        $adminUsername = $_SESSION['username'];

        // Set impersonation
        $_SESSION['impersonating'] = true;
        $_SESSION['impersonator_id'] = $adminId;
        $_SESSION['impersonate_expires'] = time() + 900; // 15 minutes
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Audit
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, 'impersonate_user', ?, ?, ?)");
        $stmt->execute([$adminId, $adminUsername, $user['id'], "Impersonating {$user['username']}", $_SERVER['REMOTE_ADDR'] ?? '']);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

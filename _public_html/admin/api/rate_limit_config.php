<?php
/**
 * Rate Limit Config API — Spencer's Website v7.0
 * Save rate limit configuration to site_settings
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

$limitsJson = $_POST['limits'] ?? '{}';
$limits = json_decode($limitsJson, true);
if (!$limits || !is_array($limits)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('rate_limits', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$limitsJson]);

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'update_rate_limits', ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Updated rate limits: $limitsJson", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'Rate limits updated. Changes take effect on next request.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

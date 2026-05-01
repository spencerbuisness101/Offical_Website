<?php
/**
 * Threat Data API — Spencer's Website v7.0
 * Block/unblock IPs, geo-blocking, threat data
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF validation for all state-changing actions
$stateChanging = ['block','unblock','manual_block','add_country','remove_country','add_isp','remove_isp','clear_old'];
if (in_array($action, $stateChanging, true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

switch ($action) {
    case 'block':
        $ip = $_POST['ip'] ?? '';
        if (!$ip) { echo json_encode(['success' => false, 'message' => 'No IP']); exit; }
        $expiresAt = date('Y-m-d H:i:s', time() + 1800);
        $stmt = $db->prepare("INSERT INTO blocked_ips (ip_address, reason, expires_at, blocked_by) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)");
        $stmt->execute([$ip, "Admin-blocked via Threat Monitor", $expiresAt]);
        logAdminAction($db, 'block_ip', null, "Blocked IP: $ip");
        echo json_encode(['success' => true]);
        break;

    case 'unblock':
        $ip = $_POST['ip'] ?? '';
        if (!$ip) { echo json_encode(['success' => false, 'message' => 'No IP']); exit; }
        require_once __DIR__ . '/../../includes/threat_detector.php';
        unblockIp($db, $ip);
        logAdminAction($db, 'unblock_ip', null, "Unblocked IP: $ip");
        echo json_encode(['success' => true]);
        break;

    case 'manual_block':
        $ip = $_POST['ip'] ?? '';
        $duration = max(5, intval($_POST['duration'] ?? 30));
        if (!$ip) { echo json_encode(['success' => false, 'message' => 'No IP']); exit; }
        $expiresAt = date('Y-m-d H:i:s', time() + ($duration * 60));
        $stmt = $db->prepare("INSERT INTO blocked_ips (ip_address, reason, expires_at, blocked_by) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)");
        $stmt->execute([$ip, "Admin manual block ({$duration}min)", $expiresAt]);
        logAdminAction($db, 'manual_block_ip', null, "Blocked IP: $ip for {$duration}min");
        echo json_encode(['success' => true]);
        break;

    case 'add_country':
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (strlen($code) !== 2) { echo json_encode(['success' => false, 'message' => 'Invalid country code']); exit; }
        $current = json_decode(getSetting($db, 'blocked_countries', '[]'), true) ?: [];
        if (!in_array($code, $current)) {
            $current[] = $code;
            setSetting($db, 'blocked_countries', json_encode($current));
        }
        logAdminAction($db, 'geo_block_add', null, "Blocked country: $code");
        echo json_encode(['success' => true]);
        break;

    case 'remove_country':
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $current = json_decode(getSetting($db, 'blocked_countries', '[]'), true) ?: [];
        $current = array_values(array_filter($current, fn($c) => $c !== $code));
        setSetting($db, 'blocked_countries', json_encode($current));
        logAdminAction($db, 'geo_block_remove', null, "Unblocked country: $code");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

function getSetting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['setting_value'] : $default;
    } catch (Exception $e) { return $default; }
}

function setSetting($db, $key, $value) {
    try {
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) { return false; }
}

function logAdminAction($db, $action, $targetId = null, $details = '') {
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'unknown', $action, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

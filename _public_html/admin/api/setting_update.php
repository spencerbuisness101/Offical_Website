<?php
/**
 * Setting Update API — Spencer's Website v7.0
 * Actually updates site_settings table (unlike track_setting.php which only logs)
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
    echo json_encode(['success' => false, 'message' => 'DB error']); exit;
}

// Ensure site_settings table exists
$db->exec("CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$setting = $_POST['setting'] ?? '';
$value = $_POST['value'] ?? '';

// Allowed settings with validation
$allowedSettings = [
    'payments_enabled' => ['type' => 'bool', 'default' => '1'],
    'maintenance_mode' => ['type' => 'bool', 'default' => '0'],
    'maintenance_title' => ['type' => 'string', 'max' => 200],
    'maintenance_message' => ['type' => 'string', 'max' => 1000],
    'maintenance_eta' => ['type' => 'string', 'max' => 100],
    'blocked_countries' => ['type' => 'json'],
    'rate_limits' => ['type' => 'json'],
    'feature_ai_chat' => ['type' => 'bool', 'default' => '1'],
    'feature_yaps_chat' => ['type' => 'bool', 'default' => '1'],
    'feature_registration' => ['type' => 'bool', 'default' => '1'],
    'feature_donations' => ['type' => 'bool', 'default' => '1'],
    'feature_feedback' => ['type' => 'bool', 'default' => '1'],
];

if (!isset($allowedSettings[$setting])) {
    echo json_encode(['success' => false, 'message' => 'Unknown setting']); exit;
}

$spec = $allowedSettings[$setting];

// Handle toggle shorthand
if ($value === 'toggle') {
    $current = '';
    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$setting]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $current = $r ? $r['setting_value'] : ($spec['default'] ?? '0');
    } catch (Exception $e) { $current = $spec['default'] ?? '0'; }
    $value = ($current === '1') ? '0' : '1';
}

// Validate value
if ($spec['type'] === 'bool') {
    if (!in_array($value, ['0', '1'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid boolean value']); exit;
    }
} elseif ($spec['type'] === 'string') {
    $maxLen = $spec['max'] ?? 255;
    if (strlen($value) > $maxLen) {
        echo json_encode(['success' => false, 'message' => "Value too long (max $maxLen)"]); exit;
    }
    // No newlines
    if (preg_match('/[\r\n]/', $value)) {
        echo json_encode(['success' => false, 'message' => 'No newlines allowed']); exit;
    }
} elseif ($spec['type'] === 'json') {
    $decoded = json_decode($value, true);
    if ($decoded === null && $value !== '[]' && $value !== '{}') {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit;
    }
}

// Update the setting
try {
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$setting, $value]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update setting']); exit;
}

// Audit log
try {
    $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'update_setting', ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Set $setting = $value", $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (Exception $e) {}

echo json_encode(['success' => true, 'setting' => $setting, 'value' => $value]);

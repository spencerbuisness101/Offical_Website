<?php
/**
 * API Key Rotate API — Spencer's Website v7.0
 * Updates .env file with new key values
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

$key = $_POST['key'] ?? '';
$value = $_POST['value'] ?? '';

$allowedKeys = ['GROQ_API_KEY', 'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'];

if (!in_array($key, $allowedKeys)) {
    echo json_encode(['success' => false, 'message' => 'Invalid key name']); exit;
}

if (empty($value)) {
    echo json_encode(['success' => false, 'message' => 'Empty value']); exit;
}

// Sanitize value — no newlines, quotes, or shell-injection chars
if (preg_match('/[\r\n"\']/', $value)) {
    echo json_encode(['success' => false, 'message' => 'Value contains invalid characters (no newlines or quotes)']); exit;
}
if (strlen($value) > 500) {
    echo json_encode(['success' => false, 'message' => 'Value too long (max 500 chars)']); exit;
}

$envPath = __DIR__ . '/../../.env';
if (!file_exists($envPath)) {
    echo json_encode(['success' => false, 'message' => '.env file not found']); exit;
}

$envContent = file_get_contents($envPath);
$pattern = '/^' . preg_quote($key, '/') . '=.*/m';
$replacement = $key . '=' . $value;
$newContent = preg_replace($pattern, $replacement, $envContent);

if ($newContent === $envContent && !preg_match($pattern, $envContent)) {
    // Key doesn't exist yet, append it
    $newContent = $envContent . "\n" . $replacement . "\n";
}

$result = file_put_contents($envPath, $newContent);
if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write .env']);
    exit;
}

// Audit
try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'rotate_api_key', ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Rotated key: $key", $_SERVER['REMOTE_ADDR'] ?? '']);
} catch (Exception $e) {}

echo json_encode(['success' => true, 'message' => 'Key updated. Changes take effect on next request.']);

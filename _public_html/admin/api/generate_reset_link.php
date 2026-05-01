<?php
/**
 * Generate Password Reset Link API — Spencer's Website v7.0
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

$userId = intval($_POST['user_id'] ?? 0);
if (!$userId) { echo json_encode(['success' => false, 'message' => 'No user ID']); exit; }

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Verify user exists
    $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }

    // Create password_resets table
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used_at TIMESTAMP NULL,
        created_by INT NULL,
        INDEX idx_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt, $_SESSION['user_id'] ?? 0]);

    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $url = $baseUrl . '/reset_password.php?token=' . $token;

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) VALUES (?, ?, 'generate_reset_link', ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', $userId, "Reset link for {$user['username']}", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'url' => $url]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

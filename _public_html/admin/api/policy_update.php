<?php
/**
 * Policy Update API — Spencer's Website v7.0
 * Save/rollback policy content with versioning
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

$policyName = $_POST['policy_name'] ?? '';
$rollbackVersion = intval($_POST['rollback_version'] ?? 0);

$policies = [
    'terms' => 'terms.php', 'privacy' => 'privacy.php',
    'refund-policy' => 'refund-policy.php', 'community-standards' => 'community-standards.php',
    'dmca' => 'dmca.php'
];

if (!isset($policies[$policyName])) {
    echo json_encode(['success' => false, 'message' => 'Invalid policy']); exit;
}

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS policy_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(50) NOT NULL,
    content LONGTEXT,
    changed_by VARCHAR(50),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    version INT DEFAULT 1,
    INDEX idx_policy (policy_name),
    INDEX idx_version (policy_name, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($rollbackVersion) {
    // Rollback: load the old version and write it to the file
    $stmt = $db->prepare("SELECT content FROM policy_versions WHERE policy_name = ? AND version = ?");
    $stmt->execute([$policyName, $rollbackVersion]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) { echo json_encode(['success' => false, 'message' => 'Version not found']); exit; }

    $filePath = __DIR__ . '/../../' . $policies[$policyName];
    file_put_contents($filePath, $old['content']);

    // Save as new version
    $stmt = $db->prepare("SELECT MAX(version) as max_v FROM policy_versions WHERE policy_name = ?");
    $stmt->execute([$policyName]); $maxV = (int)$stmt->fetchColumn();
    $newV = $maxV + 1;
    $stmt = $db->prepare("INSERT INTO policy_versions (policy_name, content, changed_by, version) VALUES (?, ?, ?, ?)");
    $stmt->execute([$policyName, $old['content'], $_SESSION['username'] ?? 'admin', $newV]);

    echo json_encode(['success' => true, 'version' => $newV]);
} else {
    // Save new content
    $content = $_POST['content'] ?? '';
    if (empty($content)) { echo json_encode(['success' => false, 'message' => 'Empty content']); exit; }

    // Write to file
    $filePath = __DIR__ . '/../../' . $policies[$policyName];
    $result = file_put_contents($filePath, $content);
    if ($result === false) { echo json_encode(['success' => false, 'message' => 'Failed to write file']); exit; }

    // Save version
    $stmt = $db->prepare("SELECT MAX(version) as max_v FROM policy_versions WHERE policy_name = ?");
    $stmt->execute([$policyName]); $maxV = (int)$stmt->fetchColumn();
    $newV = $maxV + 1;
    $stmt = $db->prepare("INSERT INTO policy_versions (policy_name, content, changed_by, version) VALUES (?, ?, ?, ?)");
    $stmt->execute([$policyName, $content, $_SESSION['username'] ?? 'admin', $newV]);

    // Audit log
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'policy_update', ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'unknown', "Updated $policyName to v$newV", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'version' => $newV]);
}

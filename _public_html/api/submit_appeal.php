<?php
/**
 * Submit Appeal API - Spencer's Website v7.0
 * Endpoint for submitting appeals from lockdown mode
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/system_mailer.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get appeal text
$appealText = trim($_POST['appeal_text'] ?? '');

// Validate appeal text
if (strlen($appealText) < 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Appeal must be at least 50 characters']);
    exit;
}

if (strlen($appealText) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Appeal must not exceed 5000 characters']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check user is in lockdown mode
    $stmt = $db->prepare("SELECT id, username, status, lockdown_reason FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    if ($user['status'] !== 'restricted') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User is not in lockdown mode']);
        exit;
    }
    
    // Ensure appeals table exists
    $db->exec("CREATE TABLE IF NOT EXISTS user_appeals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        strike_id INT NULL,
        appeal_text TEXT NOT NULL,
        status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
        reviewed_by INT NULL,
        review_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB");
    
    // Get active lockdown strike
    $stmt = $db->prepare("SELECT id FROM user_strikes WHERE user_id = ? AND is_active = TRUE AND punishment_type = 'lockdown' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $strike = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check for existing pending appeal
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_appeals WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You already have a pending appeal']);
        exit;
    }
    
    // Insert appeal
    $stmt = $db->prepare("INSERT INTO user_appeals (user_id, strike_id, appeal_text, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([
        $_SESSION['user_id'],
        $strike['id'] ?? null,
        $appealText
    ]);
    
    $appealId = $db->lastInsertId();
    
    // Notify admins
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($admins as $adminId) {
        sendSystemNotification(
            $db,
            $adminId,
            'APPEAL_SUBMITTED',
            'Lockdown Appeal Submitted',
            "User {$user['username']} (ID: {$_SESSION['user_id']}) has submitted an appeal from lockdown mode.\n\n" .
            "Reason: {$user['lockdown_reason']}\n" .
            "Appeal Preview: " . substr($appealText, 0, 200) . "...\n\n" .
            "Review in admin panel under User Moderation > Appeals."
        );
    }
    
    // Send confirmation to user
    sendSystemNotification(
        $db,
        $_SESSION['user_id'],
        'APPEAL_RECEIVED',
        'Your Appeal Has Been Received',
        "Your appeal has been submitted successfully and is now pending review.\n\n" .
        "Appeal ID: #{$appealId}\n" .
        "Submitted: " . date('Y-m-d H:i:s') . "\n\n" .
        "What happens next:\n" .
        "• An administrator will review your appeal within 72 hours\n" .
        "• You will receive a response via Smail\n" .
        "• If approved, your account will be released from lockdown\n" .
        "• If denied, the lockdown will continue or escalate\n\n" .
        "Do not submit multiple appeals for the same violation."
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Appeal submitted successfully',
        'appeal_id' => $appealId
    ]);
    
} catch (PDOException $e) {
    error_log("Submit appeal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

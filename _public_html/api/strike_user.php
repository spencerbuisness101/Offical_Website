<?php
/**
 * Strike User API - Spencer's Website v7.0
 * Admin endpoint for applying strikes and punishments
 * POST only, requires admin authentication
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

// Check admin role
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get parameters
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$ruleId = trim($_POST['rule_id'] ?? '');
$evidence = trim($_POST['evidence'] ?? '');
$overrideAction = $_POST['override_action'] ?? '';

// Validate required fields
if (!$userId || !$ruleId || !$evidence) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: user_id, rule_id, evidence']);
    exit;
}

// Validate rule ID format
if (!preg_match('/^[A-E][1-3]$/', $ruleId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid rule_id format. Must be A1, B2, etc.']);
    exit;
}

// Prevent self-striking
if ($userId === (int)$_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot apply strikes to yourself']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify target user exists
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Prevent striking other admins (optional - can be removed if needed)
    if ($targetUser['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot apply strikes to admin accounts']);
        exit;
    }
    
    // Table schema is maintained by migrations/013_consolidate_user_strikes.sql

    // Apply strike
    $result = applyStrike($db, $userId, $ruleId, $evidence, $_SESSION['user_id']);
    
    if ($result['success']) {
        // Log admin action
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_user_id, details, ip_address) 
                              VALUES (?, ?, 'apply_strike', ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['username'],
            $userId,
            "Rule: {$ruleId}, Strike: {$result['strike_number']}/3, Punishment: {$result['punishment']['type']}",
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Strike applied successfully',
            'strike' => [
                'id' => $result['strike_id'],
                'rule_id' => $ruleId,
                'strike_number' => $result['strike_number'],
                'punishment_type' => $result['punishment']['type'],
                'punishment_duration' => $result['punishment']['duration'],
                'expires_at' => $result['expires_at']
            ],
            'user' => [
                'id' => $targetUser['id'],
                'username' => $targetUser['username']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to apply strike']);
    }
    
} catch (PDOException $e) {
    error_log("Strike API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

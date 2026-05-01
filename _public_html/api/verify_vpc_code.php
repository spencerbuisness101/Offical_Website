<?php
/**
 * API: Verify VPC Code (Step 2)
 * 
 * Parent enters 6-digit code on website.
 * Upon success, consent link is sent to parent email.
 */

session_start();
header('Content-Type: application/json');

define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/VPCManager.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';

// Must be logged in as Community Account user
if (!isset($_SESSION['is_community_account']) || $_SESSION['is_community_account'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Validate CSRF token
$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

// Get verification code
$verificationCode = $_POST['verification_code'] ?? '';

// Validate code format (6 digits)
if (!preg_match('/^\d{6}$/', $verificationCode)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code.']);
    exit;
}

// Get child user ID from session
$childUserId = $_SESSION['community_session_id'] ?? null;

if (!$childUserId) {
    echo json_encode(['success' => false, 'message' => 'Unable to identify account.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    enforceIpRateLimit($db, 'vpc_verify_' . (int)$childUserId, 3, 900);

    $vpcManager = new VPCManager();
    $result = $vpcManager->verifyCode($childUserId, $verificationCode);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("VPC code verification API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

<?php
/**
 * API: Request Verifiable Parental Consent (Step 1)
 * 
 * Called when under-13 Community Account user requests to upgrade.
 * Sends 6-digit verification code to parent email.
 */

session_start();
header('Content-Type: application/json');

define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/VPCManager.php';

// Must be logged in as Community Account user (under 13)
if (!isset($_SESSION['is_community_account']) || $_SESSION['is_community_account'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Validate CSRF token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

// Get parent email
$parentEmail = $_POST['parent_email'] ?? '';

if (empty($parentEmail) || !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid parent email address.']);
    exit;
}

// Get child user ID from session
// Note: For Community Accounts, we don't have a user_id in the traditional sense
// This would need to be handled differently - perhaps using the community session ID
// For now, we'll assume there's a way to identify the child's account

$childUserId = $_SESSION['community_session_id'] ?? null;

if (!$childUserId) {
    echo json_encode(['success' => false, 'message' => 'Unable to identify account.']);
    exit;
}

try {
    $vpcManager = new VPCManager();
    $result = $vpcManager->requestConsent($childUserId, $parentEmail);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("VPC request API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

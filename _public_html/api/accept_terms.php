<?php
/**
 * Accept Terms API - Spencer's Website v7.0
 * POST endpoint for users to accept Terms of Service, Privacy Policy, and Refund Policy.
 * Phase 1 Compliance: Hard-stop gatekeeping for existing users.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

// Must have explicitly agreed
if (empty($_POST['agree']) || $_POST['agree'] !== '1') {
    echo json_encode(['success' => false, 'error' => 'You must agree to the policies to continue.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Ensure column exists (may not exist if migration hasn't run)
    try {
        $db->exec("ALTER TABLE users ADD COLUMN terms_accepted_at TIMESTAMP NULL DEFAULT NULL");
    } catch (Exception $colErr) {
        // Column already exists — safe to ignore
    }

    $stmt = $db->prepare("UPDATE users SET terms_accepted_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);

    // Update session flag
    $_SESSION['terms_accepted'] = true;

    echo json_encode(['success' => true, 'message' => 'Terms accepted successfully.']);
} catch (Exception $e) {
    error_log("Accept terms error for user $userId: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred. Please try again.']);
}

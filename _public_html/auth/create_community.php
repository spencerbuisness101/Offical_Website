<?php
/**
 * Create Community Account (13+ users who chose free option)
 * 
 * Similar to under-13 flow, but user has already passed age verification.
 * Creates a Community Account session without collecting PII.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/CommunityAuth.php';

// Check if user has passed age gate with age 13+
if (!isset($_SESSION['calculated_age']) || $_SESSION['calculated_age'] < 13) {
    header('Location: /auth/age_gate.php');
    exit;
}

// Check if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true) {
    header('Location: /main.php');
    exit;
}

// Check if already has Community Account
if (isset($_COOKIE['community_session']) && !empty($_COOKIE['community_session'])) {
    header('Location: /main.php');
    exit;
}

// Get user info for logging
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$birthdate = $_SESSION['declared_birthdate'] ?? null;
$age = $_SESSION['calculated_age'] ?? 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $pepper = $_ENV['PEPPER_SECRET'] ?? '';
    $ipHash = hash('sha256', $ip . $pepper);
    
    // Log the verification (as reverification since user is 13+ choosing Community)
    $stmt = $db->prepare("INSERT INTO age_verification_log (declared_date, calculated_age, routing_decision, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$birthdate, $age, 'reverification', $ipHash, $userAgent]);
    
    // Create Community Account session
    $communityAuth = new CommunityAuth();
    $sessionToken = $communityAuth->createSession($ip, $userAgent);
    
    // Set secure cookie
    setcookie('community_session', $sessionToken, [
        'expires' => time() + (30 * 24 * 60 * 60), // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    // Clear age verification session data (privacy)
    unset($_SESSION['declared_birthdate']);
    unset($_SESSION['calculated_age']);
    
    // Redirect to main page
    header('Location: /main.php');
    exit;
    
} catch (Exception $e) {
    error_log("Community account creation error: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred. Please try again.';
    header('Location: /auth/select_account_type.php');
    exit;
}

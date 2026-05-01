<?php
/**
 * Registration with Security Checks - Phase 4 Implementation
 * 
 * Enhanced registration that includes:
 * - Ban evasion detection
 * - Email hash ban list check
 * - Device fingerprint collection
 * - Rate limiting
 * - Human verification (honeypot)
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BanEvasionDetector.php';
require_once __DIR__ . '/../includes/EmailHasher.php';
require_once __DIR__ . '/../includes/DeviceFingerprint.php';
require_once __DIR__ . '/../includes/RateLimit.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Honeypot check (hidden field - bots fill it, humans don't)
$honeypot = $_POST['website'] ?? '';
if (!empty($honeypot)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Rate limiting
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimit = new RateLimit();
// v7.1: pass true to log this attempt — preserves prior behavior of counting every registration request.
if (!$rateLimit->check('registration', $ipAddress, 3, 3600, true)) { // 3 attempts per hour
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'error' => 'Too many registration attempts. Please try again later.',
        'retry_after' => $rateLimit->getRetryAfter('registration', $ipAddress)
    ]);
    exit;
}

// Get registration data
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$birthDate = $_POST['birth_date'] ?? '';
$accountType = $_POST['account_type'] ?? 'paid'; // 'paid' or 'community'

// Validation
$errors = [];

if (strlen($username) < 3 || strlen($username) > 30) {
    $errors[] = 'Username must be 3-30 characters';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
}

if (empty($birthDate)) {
    $errors[] = 'Birth date is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check age
$age = date_diff(date_create($birthDate), date_create())->y;

// Under 13 - redirect to Community Account flow
if ($age < 13) {
    $_SESSION['pending_community'] = [
        'birth_date' => $birthDate,
        'ip' => $ipAddress,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    echo json_encode([
        'success' => true,
        'redirect' => '/auth/create_community.php',
        'message' => 'Redirecting to Community Account creation'
    ]);
    exit;
}

// Generate device fingerprint (for Paid Accounts)
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$pepper = $_ENV['PEPPER_SECRET'] ?? '';
$fingerprintHash = DeviceFingerprint::generate(0, $ipAddress, $userAgent, $pepper);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // === PHASE 4: BAN EVASION DETECTION ===
    $banDetector = new BanEvasionDetector();
    $evasionCheck = $banDetector->checkRegistration($email, $username, $fingerprintHash, $ipAddress);
    
    if ($evasionCheck['is_evasion']) {
        // Log the detection
        $banDetector->logDetection($email, $username, $evasionCheck, $evasionCheck['action']);
        
        // Action based on confidence level
        if ($evasionCheck['action'] === 'terminate') {
            // Create the account but immediately terminate it
            $emailHash = EmailHasher::hash($email);
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            
            $stmt = $db->prepare("
                INSERT INTO users 
                (username, email_hash, password_hash, birth_date, account_tier, 
                 account_status, ip_address, user_agent, created_at, terminated_at, 
                 termination_reason, ban_evasion_detected, ban_evasion_confidence)
                VALUES (?, ?, ?, ?, ?, 'terminated', ?, ?, NOW(), NOW(), 
                'Ban evasion - registration blocked', TRUE, ?)
            ");
            $stmt->execute([
                $username, $emailHash, $passwordHash, $birthDate, $accountType,
                $ipAddress, $userAgent, $evasionCheck['confidence']
            ]);
            
            $newUserId = $db->lastInsertId();
            
            // Escalate original account punishment
            foreach ($evasionCheck['matches'] as $match) {
                if (isset($match['matched_account']['id'])) {
                    $banDetector->escalateOriginalAccount($match['matched_account']['id'], $newUserId);
                }
            }
            
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Registration not permitted.',
                'code' => 'BAN_EVASION_BLOCKED'
            ]);
            exit;
        }
        
        if ($evasionCheck['action'] === 'block') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Registration blocked. Please contact support if you believe this is an error.',
                'code' => 'SUSPICIOUS_ACTIVITY'
            ]);
            exit;
        }
    }
    
    // Check if email is banned (simple check)
    $banCheck = EmailHasher::checkBanList($email, $db);
    if ($banCheck['is_banned']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'This email address is not permitted to register.',
            'code' => 'EMAIL_BANNED'
        ]);
        exit;
    }
    
    // Check username availability
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already taken']);
        exit;
    }
    
    // Create user
    $emailHash = EmailHasher::hash($email);
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    
    $stmt = $db->prepare("
        INSERT INTO users 
        (username, email_hash, password_hash, birth_date, account_tier, 
         account_status, ip_address, user_agent, created_at, 
         device_fingerprint, last_login, last_ip)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW(), ?, NOW(), ?)
    ");
    $stmt->execute([
        $username, $emailHash, $passwordHash, $birthDate, $accountType,
        $ipAddress, $userAgent, $fingerprintHash, $ipAddress
    ]);
    
    $userId = $db->lastInsertId();
    
    // Store email patterns for ban evasion detection
    EmailHasher::storePatterns($userId, $email, $db);
    
    // For Paid Accounts, record the device
    if ($accountType === 'paid') {
        DeviceFingerprint::recordDevice($userId, $fingerprintHash, $ipAddress, $userAgent, $db);
    }
    
    // Log successful registration
    error_log("New registration: user_id={$userId}, username={$username}, tier={$accountType}");
    
    // Success response
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'redirect' => '/auth/login.php',
        'message' => 'Account created successfully. Please log in.'
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
}

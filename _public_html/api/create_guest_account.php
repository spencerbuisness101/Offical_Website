<?php
/**
 * Create Guest Account API - Spencer's Website
 * Creates a temporary passwordless community account with IP rate limiting
 * Max 3 guest accounts per IP address
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Google reCAPTCHA v3 verification (invisible, frictionless)
// Fail-OPEN: guest account creation is already protected by CSRF + IP rate
// limiting (3 per IP). Blocking on missing reCAPTCHA (ad-blockers, tracking
// prevention) was a UX bug — login.php already handles it gracefully.
$recaptchaToken = $_POST['recaptcha_token'] ?? $_POST['g-recaptcha-response'] ?? '';
$recaptchaSecret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
if ($recaptchaSecret && !empty($recaptchaToken)) {
    // Token present → verify it; reject only on explicit bot detection.
    if (!verifyRecaptcha($recaptchaToken, 0.3)) {
        echo json_encode(['success' => false, 'error' => 'Security verification failed. Please refresh and try again.']);
        exit;
    }
} elseif ($recaptchaSecret && empty($recaptchaToken)) {
    // Token absent (tracking prevention / ad-blocker) — log, then proceed.
    error_log('Guest creation reCAPTCHA token missing from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Get client IP
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// Hash IP for storage (privacy)
$ipHash = hash('sha256', $clientIp . ($_ENV['PEPPER_SECRET'] ?? ''));

try {
    $database = new Database();
    $db = $database->getConnection();

    // Ensure guest_accounts table exists
    $db->exec("CREATE TABLE IF NOT EXISTS guest_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_hash VARCHAR(64) NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_hash (ip_hash),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure users table has is_guest column
    try {
        $db->exec("ALTER TABLE users ADD COLUMN is_guest TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {
        // Column may already exist
    }
    try {
        $db->exec("ALTER TABLE users ADD COLUMN guest_created_at TIMESTAMP NULL");
    } catch (PDOException $e) {
        // Column may already exist
    }

    // Check IP limit (max 3 guest accounts per IP)
    $stmt = $db->prepare("SELECT COUNT(*) FROM guest_accounts WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$ipHash]);
    $existingCount = (int)$stmt->fetchColumn();

    if ($existingCount >= 3) {
        echo json_encode([
            'success' => false,
            'error' => 'Maximum guest accounts reached for this device. Please create a permanent account.',
            'limit_reached' => true
        ]);
        exit;
    }

    // Generate unique guest username
    $guestNumber = random_int(100000, 999999);
    $username = "Guest_{$guestNumber}";

    // Check if username exists, if so regenerate
    $attempts = 0;
    while ($attempts < 10) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $guestNumber = random_int(100000, 999999);
        $username = "Guest_{$guestNumber}";
        $attempts++;
    }

    if ($attempts >= 10) {
        echo json_encode(['success' => false, 'error' => 'Unable to generate unique username']);
        exit;
    }

    // Create passwordless guest user
    // Use a random impossible-to-guess password hash since login is session-only
    $randomPassword = bin2hex(random_bytes(32));
    $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (
        username, password_hash, email, role, is_active, 
        created_at, last_login, is_guest, guest_created_at
    ) VALUES (?, ?, ?, 'community', 1, NOW(), NOW(), 1, NOW())");

    // Guest accounts have no email (null)
    $stmt->execute([$username, $passwordHash, null]);
    $userId = (int)$db->lastInsertId();

    // Log guest account creation for IP tracking
    $stmt = $db->prepare("INSERT INTO guest_accounts (ip_hash, user_id) VALUES (?, ?)");
    $stmt->execute([$ipHash, $userId]);

    // Create session (auto-login)
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'community';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['is_guest'] = true;
    $_SESSION['terms_accepted'] = true; // Guest accounts auto-accept terms for access

    // Log the creation
    error_log("Guest account created: {$username} (ID: {$userId}) from IP hash: " . substr($ipHash, 0, 16));

    echo json_encode([
        'success' => true,
        'username' => $username,
        'redirect' => 'main.php',
        'message' => 'Welcome! You now have temporary access.'
    ]);

} catch (Exception $e) {
    error_log("Guest account creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
}

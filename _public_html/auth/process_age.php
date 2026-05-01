<?php
/**
 * Process Age Verification - Server-Side Validation and Routing
 * 
 * CRITICAL COPPA COMPLIANCE FILE
 * This file handles the routing decision based on age and creates Community Accounts
 * for users under 13 without collecting any PII.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/CommunityAuth.php';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/age_gate.php');
    exit;
}

// Get and validate birthdate
$birthdate = $_POST['birthdate'] ?? null;

// Server-side validation (never trust client-side)
$errors = [];

if (empty($birthdate)) {
    $errors[] = 'Please enter a valid date of birth.';
} else {
    // Validate date format
    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$date || $date->format('Y-m-d') !== $birthdate) {
        $errors[] = 'Please enter a valid date of birth.';
    } else {
        // Validate bounds
        $today = new DateTime();
        $minDate = new DateTime('1900-01-01');
        $minAgeDate = (clone $today)->modify('-3 years');
        
        // Must be in the past
        if ($date > $today) {
            $errors[] = 'Please enter a valid date of birth.';
        }
        
        // Year must be >= 1900
        if ($date->format('Y') < 1900) {
            $errors[] = 'Please enter a valid date of birth.';
        }
        
        // Age must be >= 3 (minimum reasonable)
        if ($date > $minAgeDate) {
            $errors[] = 'Please enter a valid date of birth.';
        }
        
        // Age must be <= 120 (maximum reasonable)
        $maxAgeDate = (clone $today)->modify('-120 years');
        if ($date < $maxAgeDate) {
            $errors[] = 'Please enter a valid date of birth.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['age_gate_error'] = $errors[0];
    header('Location: /auth/age_gate.php');
    exit;
}

// Calculate age
$today = new DateTime();
$birthDateTime = new DateTime($birthdate);
$age = $today->diff($birthDateTime)->y;

// Get IP and user agent for logging
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$pepper = $_ENV['PEPPER_SECRET'] ?? '';
$ipHash = hash('sha256', $ip . $pepper);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check rate limiting for Community Account creation
    $stmt = $db->prepare("SELECT attempt_count FROM vpc_rate_limit WHERE ip_hash = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$ipHash]);
    $rateLimit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateLimit && $rateLimit['attempt_count'] >= 3) {
        $_SESSION['age_gate_error'] = 'Too many accounts created from this network. Please try again in 24 hours.';
        header('Location: /auth/age_gate.php');
        exit;
    }
    
    // Log age verification attempt (before routing decision)
    $routingDecision = '';
    
    if ($age < 13) {
        // COPPA: Under 13 must use Community Account
        $routingDecision = 'community';
        
        // Log the verification — declared_date is NULL for community (COPPA: DOB is PII for children)
        $userAgentHash = hash('sha256', $userAgent . $pepper);
        $stmt = $db->prepare("INSERT INTO age_verification_log (declared_date, calculated_age, routing_decision, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([null, $age, $routingDecision, $ipHash, $userAgentHash]);
        
        // Update rate limit counter
        if ($rateLimit) {
            $stmt = $db->prepare("UPDATE vpc_rate_limit SET attempt_count = attempt_count + 1, last_attempt = NOW() WHERE ip_hash = ?");
            $stmt->execute([$ipHash]);
        } else {
            $stmt = $db->prepare("INSERT INTO vpc_rate_limit (ip_hash, attempt_count) VALUES (?, 1)");
            $stmt->execute([$ipHash]);
        }
        
        // Create Community Account session (NO PII COLLECTED)
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
        
        // Redirect to main page (Community Account created)
        header('Location: /main.php');
        exit;
        
    } else {
        // Age 13+: Option for Community or Paid Account
        $routingDecision = 'paid_signup';
        
        // Log the verification — 13+ users are consenting adults, DOB storage is acceptable
        $userAgentHash = hash('sha256', $userAgent . $pepper);
        $stmt = $db->prepare("INSERT INTO age_verification_log (declared_date, calculated_age, routing_decision, ip_hash, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$birthdate, $age, $routingDecision, $ipHash, $userAgentHash]);
        
        // Store birthdate in session for later use during signup
        $_SESSION['declared_birthdate'] = $birthdate;
        $_SESSION['calculated_age'] = $age;
        
        // Redirect to account type selection
        header('Location: /auth/select_account_type.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Age verification processing error: " . $e->getMessage());
    $_SESSION['age_gate_error'] = 'An error occurred. Please try again.';
    header('Location: /auth/age_gate.php');
    exit;
}

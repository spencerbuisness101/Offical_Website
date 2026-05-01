<?php
/**
 * Google OAuth Handler
 *
 * Handles Google Identity Services OAuth login flow.
 * Exchanges access token for user info, creates/updates user account.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json');

// Check if debug mode is enabled
$debugMode = strtolower(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? 'false')) === 'true';

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
    exit;
}

$oauthState = $_POST['oauth_state'] ?? '';
$expectedOauthState = $_SESSION['google_oauth_state'] ?? '';
if ($expectedOauthState === '' || !hash_equals($expectedOauthState, $oauthState)) {
    echo json_encode(['success' => false, 'message' => 'Google sign-in session expired. Please refresh and try again.']);
    exit;
}

$googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: '');
if ($googleClientId === '') {
    echo json_encode(['success' => false, 'message' => 'Google sign-in is not configured.']);
    exit;
}

// Get Google access token
$accessToken = $_POST['access_token'] ?? '';
if (empty($accessToken)) {
    echo json_encode(['success' => false, 'message' => 'Google authentication token missing']);
    exit;
}

// Rate limit OAuth attempts (10 per minute per IP)
require_once __DIR__ . '/../includes/rate_limit_ip.php';
$dbForRateLimit = null;
try {
    require_once __DIR__ . '/../config/database.php';
    $dbForRateLimit = (new Database())->getConnection();
} catch (Exception $e) {
    error_log("Rate limit DB connection failed: " . $e->getMessage());
}
if ($dbForRateLimit && !checkIpRateLimit($dbForRateLimit, 'google_oauth', 10, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many authentication attempts. Please wait a minute and try again.']);
    exit;
}

try {
    $tokenInfoCh = curl_init('https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($accessToken));
    curl_setopt($tokenInfoCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($tokenInfoCh, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($tokenInfoCh, CURLOPT_TIMEOUT, 10);

    $tokenInfoResponse = curl_exec($tokenInfoCh);
    $tokenInfoHttpCode = curl_getinfo($tokenInfoCh, CURLINFO_HTTP_CODE);
    curl_close($tokenInfoCh);

    if ($tokenInfoHttpCode !== 200 || empty($tokenInfoResponse)) {
        error_log("Google token introspection error: HTTP $tokenInfoHttpCode");
        echo json_encode(['success' => false, 'message' => 'Failed to verify Google account. Please try again.']);
        exit;
    }

    $tokenInfo = json_decode($tokenInfoResponse, true);
    $issuedTo = $tokenInfo['issued_to'] ?? ($tokenInfo['audience'] ?? '');
    if ($issuedTo !== $googleClientId) {
        error_log('Google OAuth client ID mismatch during token verification');
        echo json_encode(['success' => false, 'message' => 'Failed to verify Google account. Please try again.']);
        exit;
    }

    // Verify token with Google
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        error_log("Google OAuth API error: HTTP $httpCode");
        echo json_encode(['success' => false, 'message' => 'Failed to verify Google account. Please try again.']);
        exit;
    }

    $googleUser = json_decode($response, true);
    if (empty($googleUser['email'])) {
        echo json_encode(['success' => false, 'message' => 'Could not retrieve email from Google account']);
        exit;
    }

    $emailVerified = filter_var($googleUser['email_verified'] ?? ($tokenInfo['verified_email'] ?? false), FILTER_VALIDATE_BOOLEAN);
    if (!$emailVerified) {
        echo json_encode(['success' => false, 'message' => 'Google account email is not verified']);
        exit;
    }

    $email = strtolower(trim($googleUser['email']));
    $googleId = $googleUser['sub'] ?? null;
    $name = $googleUser['name'] ?? '';

    // Connect to database
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id, username, role, is_suspended, terms_accepted_at, is_active, google_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Existing user - check if suspended
        if (!empty($user['is_suspended'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact an administrator.',
                'suspended' => true
            ]);
            exit;
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            echo json_encode([
                'success' => false,
                'message' => 'Account is not active. Please contact support.'
            ]);
            exit;
        }

        if (!empty($user['google_id']) && !empty($googleId) && !hash_equals((string)$user['google_id'], (string)$googleId)) {
            error_log('Google OAuth account link mismatch for email: ' . $email);
            echo json_encode([
                'success' => false,
                'message' => 'Google account verification failed. Please contact support if this continues.'
            ]);
            exit;
        }

        // Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['is_suspended'] = (bool)($user['is_suspended'] ?? false);
        $_SESSION['terms_accepted'] = !empty($user['terms_accepted_at']);

        // Update last login and store google_id if not already set (link account)
        $db->prepare("UPDATE users SET last_login = NOW(), google_id = COALESCE(google_id, ?) WHERE id = ?")
            ->execute([$googleId, $user['id']]);
        unset($_SESSION['google_oauth_state']);

        error_log("Google OAuth login successful for user ID: " . $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => 'main.php'
        ]);
        exit;
    }

    // New user - create account from Google profile
    // Generate username from email (before @) or name
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]);
    if (empty($baseUsername)) {
        $baseUsername = 'user';
    }

    // Ensure unique username
    $username = $baseUsername;
    $counter = 1;
    while (true) {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if (!$checkStmt->fetch()) {
            break;
        }
        $username = $baseUsername . $counter;
        $counter++;
        if ($counter > 100) {
            throw new Exception('Could not generate unique username');
        }
    }

    // Create user with random password (they'll use Google to login)
    $randomPassword = bin2hex(random_bytes(16));
    $passwordHash = password_hash($randomPassword, PASSWORD_ARGON2ID);

    $insertStmt = $db->prepare("
        INSERT INTO users (username, email, google_id, password_hash, role, is_active, created_at, last_login, terms_accepted_at)
        VALUES (?, ?, ?, ?, 'community', 1, NOW(), NOW(), NOW())
    ");
    $insertStmt->execute([$username, $email, $googleId, $passwordHash]);

    $userId = $db->lastInsertId();

    // Log them in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'community';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['is_suspended'] = false;
    $_SESSION['terms_accepted'] = true; // Auto-accept via OAuth
    unset($_SESSION['google_oauth_state']);

    error_log("New user created via Google OAuth: ID $userId, username $username");

    echo json_encode([
        'success' => true,
        'message' => 'Account created and logged in!',
        'redirect' => 'main.php'
    ]);

} catch (Exception $e) {
    error_log("GOOGLE OAUTH ERROR: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during Google sign-in. Please try again later.'
    ]);
}
?>

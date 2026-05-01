<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/csrf.php';

// CSRF protection: accept token via GET/POST/header, or verify same-origin via Referer/Origin
$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$validCsrf = validateCsrfToken($token);

// Same-origin check as fallback for JavaScript fetch() calls
$validOrigin = false;
$expectedHost = $_SERVER['HTTP_HOST'] ?? '';
if ($expectedHost) {
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $validOrigin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) === $expectedHost;
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $validOrigin = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $expectedHost;
    }
}

if (!$validCsrf && !$validOrigin) {
    http_response_code(403);
    die('Invalid or missing CSRF token. Please log out from the website.');
}

// Guest account policy: delete guest account upon sign-out (per Community Standards).
// Guest accounts are ephemeral — only live during active session or up to 24h inactivity.
if (!empty($_SESSION['is_guest']) && !empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../includes/db.php';
        $__db = db();
        if ($__db) {
            $__gid = (int)$_SESSION['user_id'];
            // Remove guest tracking row first (FK-safe: no cascades between these)
            try {
                $stmt = $__db->prepare("DELETE FROM guest_accounts WHERE user_id = ?");
                $stmt->execute([$__gid]);
            } catch (Exception $e) { /* table may not exist on older schemas */ }
            // Remove the user row itself
            $stmt = $__db->prepare("DELETE FROM users WHERE id = ? AND is_guest = 1");
            $stmt->execute([$__gid]);
            error_log("Guest account deleted on logout: id={$__gid}");
        }
    } catch (Exception $e) {
        error_log("Guest logout cleanup error: " . $e->getMessage());
    }
}

$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
}

session_destroy();
header('Location: ../login.php');
exit;
?>

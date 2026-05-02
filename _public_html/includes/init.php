<?php
// includes/init.php
// PHP 8.4 initialization: .env loader, session hardening, headers, optional caching.
// Put this file at includes/init.php and require it from top-level PHP files.
define('INIT_LOADED', true);

// Prevent direct access to this file
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Site version constant — single source of truth for displayed version string.
// Used by page titles, footers, and anywhere the version is displayed.
define('SITE_VERSION', '7.6');

// Development mode: loads unminified JS/CSS assets (set false for production)
define('DEBUG', true);

// Set timezone to Central Standard Time
date_default_timezone_set('America/Chicago');

// --- Load .env into environment (if present) ---
// Check multiple locations for .env file
$envPaths = [
    __DIR__ . '/../../.env',        // Outside htdocs (local dev)
    __DIR__ . '/../.env',           // Inside htdocs root
    __DIR__ . '/../config/.env',    // Inside config folder
];

foreach ($envPaths as $envPath) {
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Skip comments starting with # or ;
            if (str_starts_with($line, '#') || str_starts_with($line, ';')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') continue;
            // Strip surrounding single or double quotes (standard dotenv behavior).
            // Without this, DB_PASS="secret" is loaded as: "secret" (literal quotes
            // included), which MySQL rejects as an invalid password.
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last  = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    // Unescape common escape sequences inside double-quoted values
                    if ($first === '"') {
                        $value = str_replace(['\\"', '\\\\', '\\n', '\\r', '\\t'], ['"', '\\', "\n", "\r", "\t"], $value);
                    }
                }
            }
            // Strip inline comments (unquoted values only): FOO=bar # comment
            // We already stripped quotes, so only apply to the raw tail.
            // (Skipped to avoid breaking values that legitimately contain '#'.)

            // Do not overwrite existing non-empty environment variables
            $existing = getenv($key);
            if ($existing === false || $existing === '') {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        break; // Stop after first successful load
    }
}

// --- PEPPER_SECRET critical security check ---
// If PEPPER_SECRET is missing every PII hash falls back to a hardcoded constant
// making ban-lists and IP hashes trivially reversible.
if (empty($_ENV['PEPPER_SECRET']) && empty(getenv('PEPPER_SECRET'))) {
    error_log('SECURITY CRITICAL: PEPPER_SECRET is not set in .env. All PII hashing is using a hardcoded fallback. Set PEPPER_SECRET immediately.');
}

// --- Google reCAPTCHA v3 constants (loaded from .env) ---
if (!defined('RECAPTCHA_SITE_KEY')) {
    define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? getenv('RECAPTCHA_SITE_KEY') ?: '');
}
if (!defined('RECAPTCHA_SECRET_KEY')) {
    define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? getenv('RECAPTCHA_SECRET_KEY') ?: '');
}

// --- Google OAuth Configuration (optional) ---
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: '');
}

// NOTE: Cloudflare Turnstile removed in v7.0 — Google reCAPTCHA v3 is the active captcha.

// --- Secure session cookie params (24-hour persistence, 2-hour idle timeout) ---
// SEC-L3: Detect HTTPS from server vars; force Secure=true on non-localhost hosts
$_detectSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$_isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');
// On production domains, always set Secure=true regardless of proxy detection
$secure = $_isLocalhost ? $_detectSecure : true;
$__cookie_lifetime = 86400;   // 24 hours — reduces stale session risk
$__idle_timeout = 7200;       // 2 hours — idle sessions expire sooner
ini_set('session.gc_maxlifetime', $__cookie_lifetime);
$cookieParams = [
    'lifetime' => $__cookie_lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
];
session_set_cookie_params($cookieParams);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// --- 2-hour idle timeout: graceful redirect instead of 403 ---
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $__idle_timeout) {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    }
    session_destroy();
    if (!headers_sent()) {
        header('Location: ' . (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/Games/') ? '../index.php' : 'index.php'));
        exit;
    }
}
$_SESSION['last_activity'] = time();

// --- Impersonation expiry check (15 min auto-revert) ---
if (!empty($_SESSION['impersonating']) && isset($_SESSION['impersonate_expires']) && time() > $_SESSION['impersonate_expires']) {
    $impersonatorId = $_SESSION['impersonator_id'] ?? 0;
    $_SESSION['user_id'] = $impersonatorId;
    $_SESSION['role'] = 'admin';
    $_SESSION['impersonating'] = false;
    $_SESSION['impersonator_id'] = null;
    $_SESSION['impersonate_expires'] = null;
    try {
        if (file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getStaticConnection();
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$impersonatorId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $_SESSION['username'] = $admin['username'] ?? 'admin';
            $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'impersonate_auto_expire', 'Auto-expired after 15 min', ?)");
            $stmt->execute([$impersonatorId, $_SESSION['username'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $stmt->closeCursor();
        }
    } catch (Exception $e) {
        // Log but don't fail - impersonation expiration is non-critical
        error_log("Impersonation auto-expire logging failed: " . $e->getMessage());
    }
}

// --- Community Account Detection (COPPA Compliant) ---
// Check if user has a valid Community Account session (no PII collected)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['community_session']) && !empty($_COOKIE['community_session'])) {
    try {
        if (file_exists(__DIR__ . '/CommunityAuth.php')) {
            require_once __DIR__ . '/CommunityAuth.php';
            if (class_exists('CommunityAuth')) {
                $communityAuth = new CommunityAuth();
                $communitySession = $communityAuth->validateSession($_COOKIE['community_session']);
                
                if ($communitySession !== false) {
                    // Set Community Account session variables (anonymous)
                    // MUST set logged_in = true so protected pages don't redirect-loop
                    $_SESSION['logged_in'] = true;
                    $_SESSION['is_community_account'] = true;
                    $_SESSION['community_session_id'] = $communitySession['id'];
                    $_SESSION['account_tier'] = 'community';
                    $_SESSION['role'] = 'community';
                    $_SESSION['username'] = 'Guest';
                    // user_id = 0 is a sentinel meaning "no persistent user record"
                    $_SESSION['user_id'] = 0;
                    $_SESSION['can_message'] = false;
                    $_SESSION['can_post'] = false;
                    $_SESSION['has_profile'] = false;
                    
                    // Log new device detection if IP changed (for security)
                    if ($communitySession['ip_changed']) {
                        // This will be picked up by the SYSTEM Tray on next page load
                        $_SESSION['new_device_detected'] = true;
                    }
                } else {
                    // Invalid/expired session - clear cookie
                    setcookie('community_session', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                    unset($_COOKIE['community_session']);
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail - Community Account is optional feature
        error_log("Community Account detection error: " . $e->getMessage());
    }
}

// --- Guest account activity tracking (24h inactivity policy) ---
// Throttle: update at most every 5 minutes to avoid write storm.
if (!empty($_SESSION['is_guest']) && !empty($_SESSION['user_id'])) {
    $__last_touch = $_SESSION['__guest_last_touch'] ?? 0;
    if (time() - $__last_touch > 300) { // 5 minutes
        try {
            if (file_exists(__DIR__ . '/../config/database.php')) {
                require_once __DIR__ . '/../config/database.php';
                $__guest_db = Database::getStaticConnection();
                $stmt = $__guest_db->prepare("UPDATE users SET last_login = NOW() WHERE id = ? AND is_guest = 1");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $stmt->closeCursor();
                $_SESSION['__guest_last_touch'] = time();
            }
        } catch (Exception $e) { /* non-critical, keep going */ }
    }
}

// --- Lockdown Mode Check: Prevent access for restricted/suspended users ---
// Skip Community Accounts (user_id = 0 sentinel) — they have no users table row
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    try {
        // Session-cache the user status check (5s TTL) to avoid a DB round-trip on every page load
        $__lockdown_ttl = 5;
        $__lockdown_last = $_SESSION['_lockdown_check_at'] ?? 0;
        $user = $_SESSION['_lockdown_cached_user'] ?? null;
        $__lockdown_stale = (!$user || (time() - $__lockdown_last) >= $__lockdown_ttl);

        if ($__lockdown_stale && file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            $_lockdown_db = Database::getStaticConnection();

            // Get user status, tier, and age verification flag
            $stmt = $_lockdown_db->prepare("SELECT account_status, account_tier, restriction_until, age_verified_at FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($user) {
                $_SESSION['_force_logout_last_check'] = time(); // Update force logout check too
                $_SESSION['_lockdown_check_at'] = time();
                $_SESSION['_lockdown_cached_user'] = $user;
                // Sync age_verified_at from DB into session so the escape hatch below
                // works immediately without waiting for the cache TTL to expire.
                if (!empty($user['age_verified_at']) && empty($_SESSION['age_verified_at'])) {
                    $_SESSION['age_verified_at'] = $user['age_verified_at'];
                }
            }
        }
                
        if ($user) {
            // Check if Time Removal has expired and should be lifted
            if ($user['account_status'] === 'suspended' && $user['restriction_until'] && strtotime($user['restriction_until']) <= time()) {
                if ($__lockdown_stale) {
                    // Reactivate the account (only when we actually queried the DB)
                    $stmt = $_lockdown_db->prepare("
                        UPDATE users
                        SET account_status = 'active', restriction_until = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $stmt->closeCursor();
                }
                // Notify user
                $_SESSION['account_reactivated'] = true;
                $user['account_status'] = 'active';
                $_SESSION['_lockdown_cached_user'] = $user;
            }
                    
                    // Track active Time Removal (suspension) in session for guards
                    if ($user['account_status'] === 'suspended') {
                        $_SESSION['is_suspended_punishment'] = true;
                        $_SESSION['restriction_until'] = $user['restriction_until'];
                    } else {
                        // Clear stale suspension flags when account is back to normal
                        unset($_SESSION['is_suspended_punishment'], $_SESSION['restriction_until']);
                    }

                    // Check for lockdown mode (triggered by B1/NSFW or C1/Doxxing)
                    if ($user['account_status'] === 'restricted') {
                        $currentScript = basename($_SERVER['PHP_SELF']);
                        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
                        
                        // Allow access to compliance/appeal.php and api/submit_appeal.php only
                        $allowedPaths = ['appeal.php', 'submit_appeal.php', 'logout.php'];
                        $isAllowed = false;
                        
                        foreach ($allowedPaths as $allowed) {
                            if (str_contains($currentPath, $allowed) || $currentScript === $allowed) {
                                $isAllowed = true;
                                break;
                            }
                        }
                        
                        if (!$isAllowed && !headers_sent()) {
                            header('Location: /compliance/appeal.php');
                            exit;
                        }
                    }
                    
                    // Set account tier in session for easy access
                    $_SESSION['account_tier'] = $user['account_tier'];

                    // --- One-time age reverification for existing Paid users ---
                    // NOTE: Hard redirect to /auth/verify_age_existing.php has been DISABLED
                    // because it caused ERR_TOO_MANY_REDIRECTS in the Hostinger/LiteSpeed
                    // environment (session cookie params, output caching, and relative-path
                    // redirects all conspired to create an infinite loop).
                    //
                    // Instead, we set a session flag so individual pages can show a soft
                    // prompt / banner directing the user to verify their age voluntarily.
                    // The verify_age_existing.php page remains fully functional.
                    $__ageVerified = !empty($_SESSION['age_verified_at']) || !empty($user['age_verified_at']);
                    if (!$__ageVerified && $user['account_status'] === 'active') {
                        $_SESSION['_age_verification_needed'] = true;
                    } else {
                        unset($_SESSION['_age_verification_needed']);
                    }
                    
                    // Auto-restore accounts in pending_deletion grace period if user logs back in
                    if ($user['account_status'] === 'pending_deletion') {
                        if ($__lockdown_stale) {
                            $stmt = $_lockdown_db->prepare("
                                UPDATE users
                                SET account_status = 'active',
                                    deletion_scheduled_at = NULL,
                                    deletion_requested_at = NULL
                                WHERE id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $stmt->closeCursor();
                        }
                        $user['account_status'] = 'active';
                        $_SESSION['account_restored'] = true;
                        $_SESSION['_lockdown_cached_user'] = $user;
                    }

                    // Check for terminated accounts
                    if ($user['account_status'] === 'terminated') {
                        // Log out the user
                        session_destroy();
                        
                        if (!headers_sent()) {
                            header('Location: /auth/account_terminated.php');
                            exit;
                        }
                    }
                }
        } catch (Exception $e) {
        // Fail open - don't block legitimate users on DB errors
        error_log("Lockdown check error: " . $e->getMessage());
    }
}

// --- Live Threat Detector: block malicious IPs ---
try {
    if (file_exists(__DIR__ . '/threat_detector.php')) {
        require_once __DIR__ . '/threat_detector.php';
        require_once __DIR__ . '/../config/database.php';
        $_td_db = Database::getStaticConnection();
        checkBlockedIp($_td_db);
    }
} catch (Exception $e) {
    // Fail open — don't block legitimate users on DB errors
}

// Performance headers for static assets
if (!headers_sent()) {
    // Cache control for static assets
    $extension = pathinfo($_SERVER['REQUEST_URI'] ?? '', PATHINFO_EXTENSION);
    $staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg'];
    
    if (in_array(strtolower($extension), $staticExtensions)) {
        // Cache static assets for 1 week
        header('Cache-Control: public, max-age=604800');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');
        header('Pragma: public');
    } else {
        // Don't cache dynamic pages
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('Pragma: no-cache');
    }
    
    // Enable compression
    if (extension_loaded('zlib') && !ob_get_level()) {
        ini_set('zlib.output_compression', 'On');
        ini_set('zlib.output_compression_level', 6);
    }
}

// Include enhanced cache system
require_once __DIR__ . '/cache.php';

// Include display name helper (nicknames/greetings)
if (file_exists(__DIR__ . '/display_name.php')) {
    require_once __DIR__ . '/display_name.php';
}

// v7.0: Include internationalization system
if (file_exists(__DIR__ . '/i18n.php')) {
    require_once __DIR__ . '/i18n.php';
}

// Include payment helpers (if available)
if (file_exists(__DIR__ . '/payment.php')) {
    require_once __DIR__ . '/payment.php';
}

// Include subscription helpers (if available)
if (file_exists(__DIR__ . '/subscription.php')) {
    require_once __DIR__ . '/subscription.php';
}

// v5.0 maintenance_check.php REMOVED — superseded by v7.0 maintenance.php (loaded at line ~410)
// Both defined isMaintenanceMode(), causing Fatal error: Cannot redeclare

// v5.0: Automatic server-side page view tracking
// Tracks page views even when JavaScript is disabled
if (file_exists(__DIR__ . '/track_pageview.php')) {
    require_once __DIR__ . '/track_pageview.php';
}

// Cache GC is handled by cron/cleanup_cache.php

// Check for forced logout - validates user session against database
// Optimized: Cache result in session, only re-check database every 5 minutes
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
    // Only check if database config exists and we're not on login-related pages
    $current_script = basename($_SERVER['SCRIPT_FILENAME']);
    $skip_pages = ['index.php', 'register.php', 'logout.php'];

    if (!in_array($current_script, $skip_pages) && file_exists(__DIR__ . '/../config/database.php')) {
        // Check if we need to query the database (every 5 minutes)
        $__force_logout_check_interval = 300; // 5 minutes in seconds
        $__last_check = isset($_SESSION['_force_logout_last_check']) ? (int)$_SESSION['_force_logout_last_check'] : 0;
        $__current_time = time();

        // Only query database if enough time has passed since last check
        if (($__current_time - $__last_check) >= $__force_logout_check_interval) {
            try {
                require_once __DIR__ . '/../config/database.php';
                $__conn_check = Database::getStaticConnection();
                if ($__conn_check) {
                        // Check if user has been force logged out
                        $__stmt_check = $__conn_check->prepare("SELECT force_logout_at FROM users WHERE id = ?");
                        $__stmt_check->execute([$_SESSION['user_id']]);
                        $__user_check = $__stmt_check->fetch(PDO::FETCH_ASSOC);

                        // Update last check time
                        $_SESSION['_force_logout_last_check'] = $__current_time;

                        if ($__user_check && $__user_check['force_logout_at']) {
                            $force_logout_time = strtotime($__user_check['force_logout_at']);
                            $login_time = $_SESSION['login_time'];

                            // If force_logout_at is after login_time, invalidate session
                            if ($force_logout_time > $login_time) {
                                // Destroy session
                                $_SESSION = array();
                                if (isset($_COOKIE[session_name()])) {
                                    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
                                }
                                session_destroy();

                                // Redirect to login with message
                                if (!headers_sent()) {
                                    header('Location: index.php?forced_logout=1');
                                    exit;
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently fail - don't break the site if check fails
                error_log("Force logout check error: " . $e->getMessage());
            }
        }
    }
}

// --- Lazy Subscription Status Check (every 15 min) ---
// Only for 'user' role — skip community, contributor, designer, admin
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
    && isset($_SESSION['role']) && $_SESSION['role'] === 'user'
    && isset($_SESSION['user_id'])) {

    $__sub_skip_pages = ['index.php', 'register.php', 'logout.php', 'suspended.php'];
    $__sub_current_script = basename($_SERVER['SCRIPT_FILENAME']);

    if (!in_array($__sub_current_script, $__sub_skip_pages)) {
        $__sub_check_interval = 3600; // 60 minutes (cron is primary check)
        $__sub_last_check = isset($_SESSION['_sub_check_at']) ? (int)$_SESSION['_sub_check_at'] : 0;
        $__sub_now = time();

        if (($__sub_now - $__sub_last_check) >= $__sub_check_interval) {
            try {
                if (!isset($__conn_check)) {
                    require_once __DIR__ . '/../config/database.php';
                    $__conn_sub = Database::getStaticConnection();
                } else {
                    $__conn_sub = $__conn_check;
                }

                if ($__conn_sub && function_exists('checkSubscriptionStatus')) {
                    $__sub_status = checkSubscriptionStatus($__conn_sub, $_SESSION['user_id']);
                    $_SESSION['_sub_check_at'] = $__sub_now;
                    $_SESSION['_sub_status'] = $__sub_status;

                    if ($__sub_status === 'lifetime') {
                        // Always active, nothing to do
                    } elseif ($__sub_status === 'active') {
                        // All good
                    } elseif ($__sub_status === 'grace') {
                        // Show warning but allow access — handled in main.php UI
                        $_SESSION['_sub_grace_warning'] = true;
                    } elseif ($__sub_status === 'suspended' || $__sub_status === 'none') {
                        // Suspend the user and redirect
                        if (function_exists('suspendUser')) {
                            suspendUser($__conn_sub, $_SESSION['user_id'], 'Subscription expired');
                        }
                        $_SESSION['is_suspended'] = true;
                        if (!headers_sent()) {
                            header('Location: suspended.php');
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Subscription check error: " . $e->getMessage());
            }
        }

        // Also check if user is flagged as suspended in session
        if (!empty($_SESSION['is_suspended']) && $__sub_current_script !== 'suspended.php') {
            if (!headers_sent()) {
                header('Location: suspended.php');
                exit;
            }
        }
    }
}

// Include fingerprint helpers (v7.0)
if (file_exists(__DIR__ . '/fingerprint_helpers.php')) {
    require_once __DIR__ . '/fingerprint_helpers.php';
}

// --- Security headers ---
// NOTE: Core headers (HSTS, X-Frame-Options, X-Content-Type-Options, CSP,
// Referrer-Policy, Permissions-Policy, CORS) are now set globally in .htaccess
// to cover both dynamic PHP pages AND static files served by LiteSpeed.
// Only add headers here that need PHP-level dynamic values.
if (!headers_sent()) {
    // HSTS reinforcement for PHP-rendered pages (belt + suspenders with .htaccess)
    if ($secure) {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload', false);
    }
}

// --- Optional: simple output caching (non-invasive) ---
// Enabled only when ENV var ENABLE_CACHE = 'true' (case-insensitive)
$__enable_cache = (strtolower((string)getenv('ENABLE_CACHE')) === 'true');
$__cache_ttl = intval(getenv('CACHE_TTL') ?: 60);

if ($__enable_cache && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $is_admin_path = isset($_SERVER['REQUEST_URI']) && (str_contains($_SERVER['REQUEST_URI'], '/admin') || str_contains($_SERVER['REQUEST_URI'], 'admin.php'));
    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    // Pages that contain CSRF tokens or dynamic session content must never be cached
    $__cache_skip_pages = ['index.php', 'register.php', 'admin.php', 'feedback.php'];
    $__current_page = basename($_SERVER['SCRIPT_FILENAME']);
    if (!$is_ajax && !$is_admin_path && !$is_logged_in && !in_array($__current_page, $__cache_skip_pages)) {
        // Use REQUEST_URI (including query) as key
        $key = 'page_' . md5($_SERVER['REQUEST_URI']);
        $cacheFile = __DIR__ . '/../cache/' . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.cache';
        $metaFile = $cacheFile . '.meta';
        if (is_readable($cacheFile) && is_readable($metaFile)) {
            $meta = json_decode(@file_get_contents($metaFile), true);
            if (isset($meta['expires']) && time() <= (int)$meta['expires']) {
                // Serve cached copy
                echo @file_get_contents($cacheFile);
                // End script (cached content served)
                exit;
            }
        }
        // Start output buffering and register shutdown to save cache
        ob_start();
        register_shutdown_function(function() use ($cacheFile, $metaFile, $__cache_ttl) {
            $content = @ob_get_contents();
            if ($content !== false && $content !== null && strlen($content) > 0) {
                @file_put_contents($cacheFile, $content, LOCK_EX);
                $meta = ['expires' => time() + $__cache_ttl];
                @file_put_contents($metaFile, json_encode($meta), LOCK_EX);
            }
            @ob_end_flush();
        });
    }
}

// --- Global Rate Limiting (v5.0) ---
// Rate limiting for sensitive pages (30 requests/minute default)
/**
 * @deprecated Use checkIpRateLimit() from rate_limit_ip.php instead.
 *             Session-based rate limiting is bypassable by clearing cookies.
 *             Kept for backward compatibility — do not add new call sites.
 */
function checkGlobalRateLimit($identifier = null, $max_requests = 30, $window_seconds = 60) {
    $identifier = $identifier ?? ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $cache_key = 'rate_limit_' . md5($identifier);

    // Use session-based rate limiting (simple approach that works everywhere)
    if (!isset($_SESSION['_rate_limits'])) {
        $_SESSION['_rate_limits'] = [];
    }

    $now = time();
    $limits = &$_SESSION['_rate_limits'];

    // Clean up old entries
    foreach ($limits as $key => $data) {
        if ($now - $data['window_start'] > $window_seconds) {
            unset($limits[$key]);
        }
    }

    // Check current limit
    if (!isset($limits[$cache_key])) {
        $limits[$cache_key] = [
            'count' => 1,
            'window_start' => $now
        ];
        return true;
    }

    // Check if window expired
    if ($now - $limits[$cache_key]['window_start'] > $window_seconds) {
        $limits[$cache_key] = [
            'count' => 1,
            'window_start' => $now
        ];
        return true;
    }

    // Check if limit exceeded
    if ($limits[$cache_key]['count'] >= $max_requests) {
        return false;
    }

    // Increment and allow
    $limits[$cache_key]['count']++;
    return true;
}

/**
 * Enforce rate limit - returns 429 if exceeded
 * @deprecated Use enforceIpRateLimit() from rate_limit_ip.php instead.
 *             Session-based rate limiting is bypassable by clearing cookies.
 *             Kept for backward compatibility — do not add new call sites.
 * @param int $max_requests Max requests per window
 * @param int $window_seconds Window size in seconds
 * @param string|null $identifier Optional custom identifier
 */
function enforceRateLimit($max_requests = 30, $window_seconds = 60, $identifier = null) {
    if (!checkGlobalRateLimit($identifier, $max_requests, $window_seconds)) {
        http_response_code(429);
        if (!headers_sent()) {
            header('Retry-After: ' . $window_seconds);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $window_seconds
        ]);
        exit;
    }
}

// --- v7.0: Fingerprint Ban Check ---
// Check if the current fingerprint or IP is banned (Phase 4: banned_devices + banned_ips)
function checkFingerprintBan() {
    // Only check for logged-in users or if fingerprint is provided
    $fingerprintHash = $_SESSION['fingerprint_hash'] ?? ($_POST['fingerprint_hash'] ?? null);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if ($fingerprintHash || $ipAddress !== 'unknown') {
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            require_once __DIR__ . '/fingerprint_ban.php';
            $banStatus = isFingerprintBanned($db, $fingerprintHash ?: '', $ipAddress);

            if ($banStatus['banned']) {
                // Destroy session if user is logged in
                if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                    $_SESSION = [];
                    session_destroy();
                }

                // Show ban page
                http_response_code(403);
                require_once __DIR__ . '/../errors/403_banned.php';
                exit;
            }
        } catch (Exception $e) {
            error_log("Error checking fingerprint ban: " . $e->getMessage());
        }
    }
}

// Check fingerprint ban on every request
checkFingerprintBan();

// --- v7.0: Maintenance Mode Check ---
// Check if maintenance mode is enabled
require_once __DIR__ . '/maintenance.php';
checkMaintenanceMode();

// --- v7.0: Access Restrictions Check ---
// Block users from specific paths based on admin-configured restrictions
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && ($_SESSION['role'] ?? '') !== 'admin') {
    try {
        $__ar_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($__ar_path && file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            if (class_exists('Database')) {
                $__ar_db = (new Database())->getConnection();
                $__ar_stmt = $__ar_db->prepare("SELECT reason FROM access_restrictions WHERE (user_id = ? OR user_id IS NULL) AND ? LIKE CONCAT(path_pattern, '%') LIMIT 1");
                $__ar_stmt->execute([$_SESSION['user_id'] ?? null, $__ar_path]);
                $__ar_restriction = $__ar_stmt->fetch(PDO::FETCH_ASSOC);
                $__ar_stmt->closeCursor();
                if ($__ar_restriction) {
                    http_response_code(403);
                    $__ar_reason = $__ar_restriction['reason'] ?? '';
                    if (file_exists(__DIR__ . '/../forbidden.php')) {
                        $_ar_block_reason = $__ar_reason;
                        include __DIR__ . '/../forbidden.php';
                    } else {
                        echo '<h1>403 Forbidden</h1><p>Access to this page has been restricted.</p>';
                        if ($__ar_reason) echo '<p>Reason: ' . htmlspecialchars($__ar_reason) . '</p>';
                    }
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        // Fail open — don't block users on DB errors
    }
}

// --- v5.1: User Settings Session Cache ---
// Cache user settings in session to reduce DB queries
function getUserSettings($user_id, $force_refresh = false) {
    // Check session cache first (valid for 5 minutes)
    $cache_key = '_user_settings_' . $user_id;
    $cache_time_key = '_user_settings_time_' . $user_id;
    $cache_ttl = 300; // 5 minutes

    if (!$force_refresh &&
        isset($_SESSION[$cache_key]) &&
        isset($_SESSION[$cache_time_key]) &&
        (time() - $_SESSION[$cache_time_key]) < $cache_ttl) {
        return $_SESSION[$cache_key];
    }

    // Load from database
    $settings = [];
    try {
        if (file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            if (class_exists('Database')) {
                $db = new Database();
                $conn = $db->getConnection();
                if ($conn) {
                    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $decoded = json_decode($row['setting_value'], true);
                        $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE)
                            ? $decoded
                            : $row['setting_value'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading user settings: " . $e->getMessage());
    }

    // Cache in session
    $_SESSION[$cache_key] = $settings;
    $_SESSION[$cache_time_key] = time();

    return $settings;
}

// Invalidate user settings cache (call after settings change)
function invalidateUserSettingsCache($user_id = null) {
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    if ($user_id) {
        unset($_SESSION['_user_settings_' . $user_id]);
        unset($_SESSION['_user_settings_time_' . $user_id]);
    }
}

// Feature flags helper
require_once __DIR__ . '/feature_flags.php';

// End of init file — intentionally no closing PHP tag to prevent accidental output

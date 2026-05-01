<?php
/**
 * Login Handler
 *
 * Handles user authentication via AJAX POST requests.
 * Debug mode controlled by APP_DEBUG environment variable.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

// Check if debug mode is enabled via environment variable
$debugMode = strtolower(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? 'false')) === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include and validate CSRF token
    require_once __DIR__ . '/../includes/csrf.php';

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
        exit;
    }

    // Google reCAPTCHA v3 verification (invisible, non-blocking)
    require_once __DIR__ . '/../includes/payment.php';
    $recaptchaToken = $_POST['recaptcha_token'] ?? $_POST['g-recaptcha-response'] ?? '';
    $recaptchaSecret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    $recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
    if ($recaptchaSecret && $recaptchaSiteKey) {
        if (!empty($recaptchaToken)) {
            if (!verifyRecaptcha($recaptchaToken, 0.3)) {
                error_log("reCAPTCHA verification failed from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
        } else {
            error_log("reCAPTCHA token missing from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // SEC-L4: Log only a sanitized, hashed reference
    error_log("Login attempt hash:" . substr(hash('sha256', $identifier), 0, 8));

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username or email and password are required']);
        exit;
    }

    try {
        require_once __DIR__ . '/../includes/db.php';
        $db = db();
        $ipForLimit = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Tier 1: short-window IP burst limiter
        require_once __DIR__ . '/../includes/rate_limit_ip.php';
        try {
            if (!checkIpRateLimit($db, 'login', 10, 60)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many login attempts. Please wait about a minute and try again.',
                    'retry_after_seconds' => 60
                ]);
                exit;
            }
        } catch (Throwable $t1Err) {
            error_log('Tier 1 rate limit DB error (fail-open): ' . $t1Err->getMessage());
        }

        // Tier 2: long-window failure limiter
        $rateLimit = null;
        try {
            require_once __DIR__ . '/../includes/RateLimit.php';
            $rateLimit = new RateLimit();
            if (!$rateLimit->check('login', $ipForLimit, 5, 300)) {
                $retryAfter = $rateLimit->getRetryAfter('login', $ipForLimit);
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many failed login attempts. Please wait '
                        . max(1, (int)ceil($retryAfter / 60)) . ' minute(s) and try again.',
                    'retry_after_seconds' => $retryAfter
                ]);
                exit;
            }
        } catch (Throwable $rlErr) {
            error_log('Phase 4 RateLimit fallback (non-fatal): ' . $rlErr->getMessage());
            $rateLimit = null;
        }

        // Get user with optional column check
        $hasIsActive = false;
        $hasTermsAccepted = false;
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetchAll();
            $hasIsActive = (count($colCheck) > 0);
            
            $colCheck2 = $db->query("SHOW COLUMNS FROM users LIKE 'terms_accepted_at'")->fetchAll();
            $hasTermsAccepted = (count($colCheck2) > 0);
        } catch (Exception $e) { /* fail-safe */ }

        $selectCols = "id, username, password_hash, role, is_suspended, login_attempts, last_failed_login, locked_until";
        if ($hasTermsAccepted) { $selectCols .= ", terms_accepted_at"; }
        
        $isEmail = strpos($identifier, '@') !== false;
        if ($isEmail) {
            require_once __DIR__ . '/../includes/EmailHasher.php';
            $emailHash = EmailHasher::hash($identifier);
            $query = "SELECT {$selectCols} FROM users WHERE email_hash = :identifier";
            $params = [':identifier' => $emailHash];
        } else {
            $query = "SELECT {$selectCols} FROM users WHERE username = :identifier";
            $params = [':identifier' => $identifier];
        }
        
        if ($hasIsActive) { $query .= " AND is_active = 1"; }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($user) {
            // Lockout check
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remainingTime = ceil((strtotime($user['locked_until']) - time()) / 60);
                echo json_encode([
                    'success' => false,
                    'message' => "Account locked. Try again in {$remainingTime} minutes.",
                    'locked' => true,
                    'lockout_minutes' => $remainingTime
                ]);
                exit;
            }
            
            // Auto-reset expired lockout
            if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
                $up = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
                $up->execute([$user['id']]);
                $up->closeCursor();
                $user['login_attempts'] = 0;
                $user['locked_until'] = null;
            }
            
            // Password verification
            if (password_verify($password, $user['password_hash'])) {
                if (!empty($user['is_suspended'])) {
                    echo json_encode(['success' => false, 'message' => 'Your account has been suspended.', 'suspended' => true]);
                    exit;
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['is_suspended'] = (bool)($user['is_suspended'] ?? false);

                // Nickname
                try {
                    $nst = $db->prepare("SELECT nickname FROM users WHERE id = ?");
                    $nst->execute([$user['id']]);
                    $_SESSION['nickname'] = $nst->fetchColumn() ?: null;
                    $nst->closeCursor();
                } catch (Exception $e) { $_SESSION['nickname'] = null; }
                
                // Fingerprint
                try {
                    $fingerprintHash = $_POST['fingerprint_hash'] ?? null;
                    if ($fingerprintHash) {
                        $_SESSION['fingerprint_hash'] = $fingerprintHash;
                        $fpup = $db->prepare("UPDATE users SET device_fingerprint_hash = ?, device_fingerprint = ? WHERE id = ?");
                        $fpup->execute([$fingerprintHash, $fingerprintHash, $user['id']]);
                        $fpup->closeCursor();
                        
                        if (file_exists(__DIR__ . '/../includes/fingerprint_helpers.php')) {
                            require_once __DIR__ . '/../includes/fingerprint_helpers.php';
                            if (function_exists('storeFingerprint')) {
                                storeFingerprint($db, [
                                    'user_id' => $user['id'],
                                    'device_uuid' => $_POST['device_uuid'] ?? '',
                                    'fingerprint_hash' => $fingerprintHash,
                                    'screen_resolution' => $_POST['screen_resolution'] ?? '',
                                    'gpu_renderer' => $_POST['gpu_renderer'] ?? '',
                                    'canvas_hash' => $_POST['canvas_hash'] ?? '',
                                    'font_list_hash' => $_POST['font_list_hash'] ?? '',
                                    'timezone' => $_POST['timezone'] ?? '',
                                    'language' => $_POST['language'] ?? '',
                                    'platform' => $_POST['platform'] ?? '',
                                    'user_agent_hash' => $_POST['user_agent_hash'] ?? '',
                                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
                                ]);
                                linkFingerprintToUser($db, $fingerprintHash, $user['id']);
                            }
                        }
                    }
                } catch (Throwable $fpErr) { error_log('LOGIN non-fatal fingerprint error: ' . $fpErr->getMessage()); }

                // Resets
                try {
                    $rst = $db->prepare("UPDATE users SET login_attempts = 0, last_failed_login = NULL, locked_until = NULL WHERE id = ?");
                    $rst->execute([$user['id']]);
                    $rst->closeCursor();
                } catch (Throwable $e) { error_log('LOGIN non-fatal reset error: ' . $e->getMessage()); }

                if ($rateLimit) { $rateLimit->reset('login', $ipForLimit); }
                try {
                    $rlcl = $db->prepare("DELETE FROM rate_limit_ip WHERE ip_address = ? AND endpoint = 'login'");
                    $rlcl->execute([hash('sha256', $ipForLimit . ($_ENV['PEPPER_SECRET'] ?? getenv('PEPPER_SECRET') ?? ''))]);
                    $rlcl->closeCursor();
                } catch (Throwable $e) { /* skip */ }
                
                // Last Login
                try {
                    $upd = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $upd->execute([$user['id']]);
                    $upd->closeCursor();
                } catch (Throwable $e) { /* skip */ }

                // Notify
                try {
                    if (file_exists(__DIR__ . '/../includes/system_mailer.php')) {
                        require_once __DIR__ . '/../includes/system_mailer.php';
                        if (function_exists('logLoginAndNotify')) {
                            logLoginAndNotify($db, $user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
                        }
                    }
                } catch (Throwable $mailErr) { /* skip */ }

                $_SESSION['terms_accepted'] = !empty($user['terms_accepted_at']);

                // Remember me
                try {
                    if (($_POST['remember_me'] ?? '') === '1' && !headers_sent()) {
                        $lifetime = 30 * 24 * 60 * 60;
                        session_set_cookie_params(['lifetime' => $lifetime, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                        setcookie(session_name(), session_id(), ['expires' => time() + $lifetime, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                    }
                } catch (Throwable $e) { /* skip */ }

                regenerateCsrfToken();
                session_write_close();
                error_log("Login successful for user ID: " . $user['id']);

                try {
                    $logS = $db->prepare("INSERT INTO login_attempts (user_id, username, ip_address, user_agent, success, attempted_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    $logS->execute([$user['id'], $user['username'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    $logS->closeCursor();
                } catch (Throwable $e) { /* skip */ }

                $resp = ['success' => true, 'message' => 'Login successful!', 'redirect' => '/main.php'];
                if (empty($user['terms_accepted_at'])) { $resp['terms_required'] = true; }
                
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode($resp);
                } else {
                    header('Location: /main.php');
                    exit;
                }
            } else {
                // Failed password
                $lastFailed = date('Y-m-d H:i:s');
                $attempts = (int)($user['login_attempts'] ?? 0) + 1;
                $lockedUntil = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;

                try {
                    $up = $db->prepare("UPDATE users SET login_attempts = ?, last_failed_login = ?, locked_until = ? WHERE id = ?");
                    $up->execute([$attempts, $lastFailed, $lockedUntil, $user['id']]);
                    $up->closeCursor();
                } catch (Throwable $e) { /* skip */ }

                if ($rateLimit) { $rateLimit->log('login', $ipForLimit); }
                try {
                    $logS = $db->prepare("INSERT INTO login_attempts (user_id, username, ip_address, user_agent, success, attempted_at) VALUES (?, ?, ?, ?, 0, NOW())");
                    $logS->execute([$user['id'], substr($user['username'], 0, 50), $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    $logS->closeCursor();
                } catch (Throwable $e) { /* skip */ }

                echo json_encode([
                    'success' => false,
                    'message' => $lockedUntil ? "Account locked for 15 minutes." : "Invalid username or password.",
                    'locked' => (bool)$lockedUntil
                ]);
            }
        } else {
            // User not found
            $dummyHash = '$argon2id$v=19$m=65536,t=4,p=3$dGVzdHNhbHQ$dGVzdHBhc3N3b3JkaGFzaA';
            password_verify($password, $dummyHash);
            if ($rateLimit) { $rateLimit->log('login', $ipForLimit); }
            try {
                $logS = $db->prepare("INSERT INTO login_attempts (user_id, username, ip_address, user_agent, success, attempted_at) VALUES (NULL, ?, ?, ?, 0, NOW())");
                $logS->execute([substr($identifier, 0, 50), $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                $logS->closeCursor();
            } catch (Throwable $e) { /* skip */ }

            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } catch (Throwable $e) {
        error_log("LOGIN CRITICAL ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        $errorMsg = 'An unexpected error occurred: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
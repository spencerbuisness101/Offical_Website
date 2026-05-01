<?php
/**
 * Security Helper Functions - Spencer's Website v7.0
 * Provides comprehensive input validation, XSS protection, and security utilities
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Sanitize input to prevent XSS attacks
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    
    // Strip HTML tags first
    $input = strip_tags($input);
    
    // Remove dangerous protocol/event keywords BEFORE encoding
    $dangerous = ['javascript:', 'vbscript:', 'data:', 'onload=', 'onerror=', 'onfocus=', 'onclick=', 'onmouseover=', 'onmouseout=', 'onsubmit='];
    $input = str_ireplace($dangerous, '', $input);
    
    // Encode special characters last (HTML context safety)
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $input;
}

/**
 * Validate email address
 */
function validateEmail($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username (alphanumeric, underscore, hyphen, 3-30 chars)
 */
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username);
}

/**
 * Validate password strength (min 8 chars, at least one number, one letter, one special char)
 */
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $password);
}

/**
 * @deprecated DANGEROUS ANTI-PATTERN â€” DO NOT USE THIS FUNCTION.
 *
 * Stripping SQL keywords from input is NOT a valid SQL injection defence.
 * It provides false security confidence while silently corrupting legitimate
 * user content containing words like SELECT, INSERT, OR, AND, etc.
 *
 * CORRECT approach: use PDO prepared statements with ? placeholders for ALL
 * user-supplied values. See config/database.php for the PDO connection.
 *
 * This function is preserved only to avoid breaking any deployed code that
 * may call it. It now logs a warning and returns input UNCHANGED so callers
 * that relied on its output continue to function, and so unexpected calls are
 * surfaced in the error log during testing.
 *
 * TO REMOVE: grep the entire codebase for 'sanitizeSql(' to confirm zero
 * call sites, then delete this function.
 */
function sanitizeSql($input) {
    error_log('SECURITY WARNING: sanitizeSql() called â€” this is a deprecated anti-pattern. Use PDO prepared statements instead. Caller: ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? 'unknown') . ':' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? '?'));
    return $input;
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check for suspicious activity
 */
function isSuspiciousActivity($input) {
    $suspicious = [
        'script', 'iframe', 'object', 'embed', 'link', 'meta',
        'javascript:', 'vbscript:', 'data:', 'file:', 'ftp:',
        'alert(', 'confirm(', 'prompt(', 'eval(', 'expression(',
        'setTimeout(', 'setInterval(', 'XMLHttpRequest', 'fetch(',
        'document.cookie', 'window.location', 'document.write'
    ];
    
    $inputLower = strtolower($input);
    foreach ($suspicious as $pattern) {
        if (strpos($inputLower, $pattern) !== false) {
            return true;
        }
    }
    
    // H4: Catch any HTML event handler attribute (onclick=, onerror=, onload=, etc.)
    // Covers all ~70+ DOM event handlers without needing an explicit list
    if (preg_match('/\bon\w+\s*=/i', $input)) {
        return true;
    }
    
    return false;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large';
    }
    
    // Check file type
    if (!empty($allowedTypes)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Invalid file type';
        }
    }
    
    // Check for dangerous file extensions
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'bat', 'exe', 'com', 'scr'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($extension, $dangerousExtensions)) {
        $errors[] = 'Dangerous file extension';
    }
    
    return $errors;
}

/**
 * Secure file name
 */
function secureFileName($filename) {
    $filename = sanitizeInput($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    $filename = trim($filename, '_');
    
    // Add timestamp to prevent overwrites
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    
    return $basename . '_' . time() . '.' . $extension;
}

/**
 * Rate limiting check (APCu-backed with session fallback).
 * @deprecated Call site name changed to checkRateLimitApcu() to avoid collision
 *             with the DB-backed checkRateLimit() defined in ai_panel.php.
 *             Do not add new call sites â€” use checkIpRateLimit() from rate_limit_ip.php instead.
 */
function checkRateLimitApcu($identifier, $maxRequests = 10, $timeWindow = 60) {
    $cacheKey = "rate_limit_{$identifier}";

    // Fallback: if APCu is not available, use session-based counting
    if (!function_exists('apcu_fetch') || !apcu_enabled()) {
        error_log("checkRateLimitApcu: APCu not available, using session fallback for identifier: " . substr($identifier, 0, 30));
        $sessionKey = '_rl_' . md5($cacheKey);
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['requests' => 0, 'first_request' => time()];
        }
        if (time() - $_SESSION[$sessionKey]['first_request'] > $timeWindow) {
            $_SESSION[$sessionKey] = ['requests' => 0, 'first_request' => time()];
        }
        $_SESSION[$sessionKey]['requests']++;
        return $_SESSION[$sessionKey]['requests'] <= $maxRequests;
    }

    $current = apcu_fetch($cacheKey) ?: ['requests' => 0, 'first_request' => time()];
    
    // Reset if time window has passed
    if (time() - $current['first_request'] > $timeWindow) {
        $current = ['requests' => 0, 'first_request' => time()];
    }
    
    $current['requests']++;
    
    if ($current['requests'] > $maxRequests) {
        return false;
    }
    
    apcu_store($cacheKey, $current, $timeWindow);
    return true;
}

/**
 * Log security event
 */
function logSecurityEvent($event, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? null,
        'details' => $details
    ];
    
    error_log('SECURITY: ' . json_encode($logEntry));
}



/**
 * Generate Content Security Policy header
 *
 * v7.1: Font Awesome is now self-hosted at /assets/vendor/fontawesome/, so
 * cdnjs.cloudflare.com is removed from font-src. It stays in script-src /
 * style-src because pages like ai_panel.php still load highlight.js, marked,
 * and DOMPurify from cdnjs. When those are self-hosted later, drop cdnjs
 * everywhere.
 */
function generateCSPHeader() {
    $csp  = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://challenges.cloudflare.com https://rawcdn.githack.com https://www.google.com https://www.gstatic.com https://accounts.google.com https://apis.google.com; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://challenges.cloudflare.com https://fonts.googleapis.com https://www.google.com; ";
    $csp .= "img-src 'self' data: blob: https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
    $csp .= "connect-src 'self' https://api.groq.com https://challenges.cloudflare.com https://www.google.com https://accounts.google.com https://cdn.jsdelivr.net; ";
    $csp .= "frame-src 'self' https://challenges.cloudflare.com https://searchprox.global.ssl.fastly.net https://thespencergamingwebsite.com https://*.voidnetwork.space.cdn.cloudflare.net https://c.voidnetwork.space.cdn.cloudflare.net https://archive.org https://rawcdn.githack.com https://www.google.com https://accounts.google.com; ";
    $csp .= "worker-src 'self' blob:; ";
    $csp .= "frame-ancestors 'none'; ";
    $csp .= "base-uri 'self'; ";
    $csp .= "object-src 'none'; ";
    $csp .= "form-action 'self'; ";
    $csp .= "report-uri /api/csp-violation;";

    return $csp;
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header("Content-Security-Policy: " . generateCSPHeader());
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self), usb=(), xr-spatial-tracking=(), interest-cohort=(), browsing-topics=(), attribution-reporting=(), run-ad-auction=(), join-ad-interest-group=()");
}

/**
 * Validate and sanitize form data
 */
function validateFormData($data, $rules) {
    $errors = [];
    $sanitized = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Check if required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = ucfirst($field) . ' is required';
            continue;
        }
        
        // Skip validation if field is empty and not required
        if (empty($value) && !isset($rule['required'])) {
            $sanitized[$field] = '';
            continue;
        }
        
        // Type validation
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!validateEmail($value)) {
                        $errors[$field] = 'Invalid email address';
                    }
                    break;
                case 'username':
                    if (!validateUsername($value)) {
                        $errors[$field] = 'Invalid username (3-30 chars, alphanumeric, underscore, hyphen)';
                    }
                    break;
                case 'password':
                    if (!validatePassword($value)) {
                        $errors[$field] = 'Password must be at least 8 characters with one number, one letter, and one special character';
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$field] = 'Must be a number';
                    }
                    break;
            }
        }
        
        // Length validation
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $errors[$field] = 'Must be at least ' . $rule['min_length'] . ' characters';
        }
        
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            $errors[$field] = 'Must be no more than ' . $rule['max_length'] . ' characters';
        }
        
        // Check for suspicious activity
        if (isSuspiciousActivity($value)) {
            $errors[$field] = 'Invalid characters detected';
            logSecurityEvent('SUSPICIOUS_INPUT', ['field' => $field, 'value' => substr($value, 0, 50)]);
        }
        
        // Sanitize the value
        $sanitized[$field] = sanitizeInput($value);
    }
    
    return ['errors' => $errors, 'data' => $sanitized];
}

/**
 * Validate a URL for SSRF safety.
 * Resolves DNS and ensures the target IP is not private/reserved.
 * Returns ['safe' => true, 'host' => $host, 'resolved_ip' => $ip] on success.
 * Returns ['safe' => false, 'error' => $reason] on failure.
 */
function validateUrlSsrf($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['safe' => false, 'error' => 'Invalid URL format'];
    }

    $parsed = parse_url($url);
    if (!$parsed) {
        return ['safe' => false, 'error' => 'Invalid URL'];
    }

    // Only allow http/https
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'])) {
        return ['safe' => false, 'error' => 'URL must use http or https'];
    }

    $host = strtolower($parsed['host'] ?? '');
    if (empty($host)) {
        return ['safe' => false, 'error' => 'No host in URL'];
    }

    // Block literal private hostnames before DNS resolution
    $blockedLiterals = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
    if (in_array($host, $blockedLiterals) || str_ends_with($host, '.local')) {
        return ['safe' => false, 'error' => 'Blocked host'];
    }

    // Resolve hostname to IP
    $resolvedIp = gethostbyname($host);
    if ($resolvedIp === $host) {
        return ['safe' => false, 'error' => 'Could not resolve hostname'];
    }

    // Block private and reserved IP ranges
    if (!filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return ['safe' => false, 'error' => 'Access to internal network resources is not allowed'];
    }

    return ['safe' => true, 'host' => $host, 'resolved_ip' => $resolvedIp];
}
?>



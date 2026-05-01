<?php
/**
 * CSRF Protection Helper
 *
 * Provides functions for generating and validating CSRF tokens
 * using standard session-based tokens.
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Generate a new CSRF token (stored in session)
 *
 * @return string The CSRF token (64-char hex string)
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    // Regenerate if older than 1 hour
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate a hidden input field with the CSRF token
 * Use this in forms to add CSRF protection
 *
 * @return string HTML hidden input element
 */
if (!function_exists('csrfField')) {
    function csrfField() {
        $token = generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Validate a CSRF token against the session
 *
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
if (!function_exists('validateCsrfToken')) {
    /**
     * Validate the CSRF token.
     * SEC-M1: Pass $rotate = true on state-changing endpoints to regenerate the token
     * after validation, preventing token replay within the 1-hour window.
     */
    function validateCsrfToken($token, bool $rotate = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        if ($valid && $rotate) {
            // Rotate: issue a fresh token immediately after a successful check
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $valid;
    }
}

/**
 * Automatically validate CSRF token for POST requests
 * Call this at the beginning of form processing scripts
 *
 * @param bool $exitOnFailure If true, sends 403 and exits on invalid token
 * @return bool True if valid or not a POST request, false if invalid
 */
function requireCsrfToken($exitOnFailure = true) {
    // Only validate for POST, PUT, DELETE requests
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        return true;
    }

    // Get token from POST data or headers (for AJAX)
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!validateCsrfToken($token)) {
        if ($exitOnFailure) {
            http_response_code(403);

            // Check if it's an AJAX request
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token']);
            } else {
                echo 'Invalid or missing CSRF token. Please refresh the page and try again.';
            }
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Get the current CSRF token from session
 *
 * @return string|null The current token, or null if not set
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Regenerate the CSRF token (creates a fresh token)
 */
function regenerateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

<?php
/**
 * API Authentication Helper
 *
 * Provides centralized authentication for API endpoints.
 * Validates that requests come from authenticated users
 * and returns the authenticated user ID (ignoring client-provided user_id).
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Authenticate API request and get user ID
 *
 * @param bool $required If true, exits with 401 if not authenticated
 * @return int|null The authenticated user ID or null if not logged in
 */
function authenticateApiRequest($required = true) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['logged_in']) &&
                  $_SESSION['logged_in'] === true &&
                  isset($_SESSION['user_id']);

    if (!$isLoggedIn) {
        if ($required) {
            sendApiAuthError('Authentication required. Please log in.');
        }
        return null;
    }

    // Return the authenticated user ID from session (not from client input)
    return (int)$_SESSION['user_id'];
}

/**
 * Get authenticated user data
 *
 * @param bool $required If true, exits with 401 if not authenticated
 * @return array|null User data array or null if not logged in
 */
function getAuthenticatedUser($required = true) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['logged_in']) &&
                  $_SESSION['logged_in'] === true &&
                  isset($_SESSION['user_id']);

    if (!$isLoggedIn) {
        if ($required) {
            sendApiAuthError('Authentication required. Please log in.');
        }
        return null;
    }

    return [
        'user_id' => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? 'community'
    ];
}

/**
 * Send authentication error response and exit
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code (default 401)
 */
function sendApiAuthError($message = 'Unauthorized', $statusCode = 401) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'unauthorized',
        'message' => $message
    ]);
    exit;
}

/**
 * Send API error response and exit
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code (default 400)
 */
function sendApiError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

/**
 * Send API success response
 *
 * @param mixed $data Data to include in response
 * @param string $message Optional success message
 */
function sendApiSuccess($data = null, $message = null) {
    header('Content-Type: application/json');
    $response = ['success' => true];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
}

/**
 * Require specific role(s) for API access
 *
 * @param array|string $allowedRoles Single role or array of allowed roles
 * @param bool $exitOnFailure If true, sends 403 and exits on unauthorized
 * @return bool True if authorized, false otherwise
 */
function requireApiRole($allowedRoles, $exitOnFailure = true) {
    $user = getAuthenticatedUser(true);

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    $hasAccess = in_array($user['role'], $allowedRoles);

    if (!$hasAccess && $exitOnFailure) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'forbidden',
            'message' => 'You do not have permission to access this resource.'
        ]);
        exit;
    }

    return $hasAccess;
}

/**
 * Validate and get JSON input from request body
 *
 * @return array|null Decoded JSON data or null on failure
 */
function getJsonInput() {
    $input = file_get_contents('php://input');

    if (empty($input)) {
        return null;
    }

    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

/**
 * Override user_id in input data with authenticated user ID
 * This ensures client can't spoof user_id
 *
 * @param array &$data Reference to input data array
 * @return int The authenticated user ID
 */
function secureUserId(&$data) {
    $userId = authenticateApiRequest(true);
    $data['user_id'] = $userId;
    return $userId;
}

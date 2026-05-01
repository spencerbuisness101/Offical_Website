<?php
/**
 * API Endpoint: Get Current User Role
 * GET /api/get_user_role.php
 * Returns the current user's role for UI permissions
 */

header('Content-Type: application/json');

// Prevent direct access
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'role' => 'guest',
        'is_admin' => false,
        'is_logged_in' => false
    ]);
    exit;
}

// Return user role info
echo json_encode([
    'success' => true,
    'role' => $_SESSION['role'] ?? 'community',
    'is_admin' => ($_SESSION['role'] ?? '') === 'admin',
    'is_logged_in' => true,
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null
]);

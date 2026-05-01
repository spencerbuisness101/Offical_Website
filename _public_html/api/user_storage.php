<?php
/**
 * User Storage API - Spencer's Website v5.0
 *
 * Provides personalized storage for users (excludes community accounts).
 * Supports GET, SET, DELETE, LIST operations.
 *
 * Endpoints:
 * POST action=get    - Get a storage value
 * POST action=set    - Set a storage value
 * POST action=delete - Delete a storage key
 * POST action=list   - List all keys for user
 *
 * Limits:
 * - 1MB max per key
 * - Community users excluded
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Include init for session handling
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Response helper
function jsonResponse($success, $data = null, $message = null, $code = 200) {
    http_response_code($code);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message !== null) $response['message'] = $message;
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    jsonResponse(false, null, 'Authentication required', 401);
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'community';

// Community users cannot use storage API
if ($user_role === 'community') {
    jsonResponse(false, null, 'Storage API not available for community accounts', 403);
}

if (!$user_id) {
    jsonResponse(false, null, 'Invalid session', 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    jsonResponse(false, null, 'Invalid CSRF token', 403);
}

// Get action
$action = $_POST['action'] ?? '';
if (empty($action)) {
    jsonResponse(false, null, 'Action required', 400);
}

// Connect to database
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Create table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS user_storage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        storage_key VARCHAR(100) NOT NULL,
        storage_value LONGTEXT,
        storage_type ENUM('json', 'text') DEFAULT 'json',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_storage (user_id, storage_key),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (Exception $e) {
    error_log("User storage API DB error: " . $e->getMessage());
    jsonResponse(false, null, 'Database error', 500);
}

// Max storage value size (1MB)
define('MAX_VALUE_SIZE', 1048576);

switch ($action) {
    case 'get':
        $key = trim($_POST['key'] ?? '');
        if (empty($key) || strlen($key) > 100) {
            jsonResponse(false, null, 'Invalid key', 400);
        }

        try {
            $stmt = $conn->prepare("SELECT storage_value, storage_type FROM user_storage WHERE user_id = ? AND storage_key = ?");
            $stmt->execute([$user_id, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                jsonResponse(false, null, 'Key not found', 404);
            }

            $value = $row['storage_value'];
            if ($row['storage_type'] === 'json') {
                $value = json_decode($value, true);
            }

            jsonResponse(true, ['key' => $key, 'value' => $value]);
        } catch (Exception $e) {
            error_log("User storage get error: " . $e->getMessage());
            jsonResponse(false, null, 'Failed to retrieve value', 500);
        }
        break;

    case 'set':
        $key = trim($_POST['key'] ?? '');
        $value = $_POST['value'] ?? '';
        $type = $_POST['type'] ?? 'json';

        if (empty($key) || strlen($key) > 100) {
            jsonResponse(false, null, 'Invalid key (max 100 chars)', 400);
        }

        // Validate key format
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $key)) {
            jsonResponse(false, null, 'Invalid key format (alphanumeric, underscore, dash, dot only)', 400);
        }

        // Process value based on type
        if ($type === 'json') {
            // If value is already a string, try to decode it first
            if (is_string($value)) {
                $decoded = json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = json_encode($decoded);
                } else {
                    // Not valid JSON, encode it as a string
                    $value = json_encode($value);
                }
            } else {
                $value = json_encode($value);
            }
        }

        // Check size limit
        if (strlen($value) > MAX_VALUE_SIZE) {
            jsonResponse(false, null, 'Value too large (max 1MB)', 413);
        }

        try {
            $stmt = $conn->prepare("INSERT INTO user_storage (user_id, storage_key, storage_value, storage_type)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE storage_value = VALUES(storage_value), storage_type = VALUES(storage_type)");
            $stmt->execute([$user_id, $key, $value, $type]);

            jsonResponse(true, ['key' => $key], 'Value saved successfully');
        } catch (Exception $e) {
            error_log("User storage set error: " . $e->getMessage());
            jsonResponse(false, null, 'Failed to save value', 500);
        }
        break;

    case 'delete':
        $key = trim($_POST['key'] ?? '');
        if (empty($key)) {
            jsonResponse(false, null, 'Key required', 400);
        }

        try {
            $stmt = $conn->prepare("DELETE FROM user_storage WHERE user_id = ? AND storage_key = ?");
            $stmt->execute([$user_id, $key]);

            if ($stmt->rowCount() > 0) {
                jsonResponse(true, ['key' => $key], 'Key deleted successfully');
            } else {
                jsonResponse(false, null, 'Key not found', 404);
            }
        } catch (Exception $e) {
            error_log("User storage delete error: " . $e->getMessage());
            jsonResponse(false, null, 'Failed to delete key', 500);
        }
        break;

    case 'list':
        try {
            $stmt = $conn->prepare("SELECT storage_key, storage_type, created_at, updated_at FROM user_storage WHERE user_id = ? ORDER BY storage_key");
            $stmt->execute([$user_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $keys = array_map(function($row) {
                return [
                    'key' => $row['storage_key'],
                    'type' => $row['storage_type'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }, $rows);

            jsonResponse(true, ['keys' => $keys, 'count' => count($keys)]);
        } catch (Exception $e) {
            error_log("User storage list error: " . $e->getMessage());
            jsonResponse(false, null, 'Failed to list keys', 500);
        }
        break;

    default:
        jsonResponse(false, null, 'Invalid action. Use: get, set, delete, list', 400);
}

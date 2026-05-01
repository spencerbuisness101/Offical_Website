<?php
// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';


// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page.']);
            exit;
        }
        header('Location: yaps.php?error=4');
        exit;
    }

    $message = trim($_POST['message']);

    // Validate message
    if (empty($message)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }
        header('Location: yaps.php?error=2');
        exit;
    }

    if (strlen($message) > 500) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Message too long (max 500 characters)']);
            exit;
        }
        header('Location: yaps.php?error=2');
        exit;
    }

    // Rate limiting - max 1 message per 2 seconds
    if (isset($_SESSION['last_message_time']) && (time() - $_SESSION['last_message_time']) < 2) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Please wait before sending another message']);
            exit;
        }
        header('Location: yaps.php?error=3');
        exit;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Table schema is maintained by migrations/016_create_missing_tables.sql

        $stmt = $db->prepare("INSERT INTO yaps_chat_messages (user_id, username, user_role, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $role, $message]);

        // Update last message time for rate limiting
        $_SESSION['last_message_time'] = time();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Message sent',
                'data' => [
                    'id' => $db->lastInsertId(),
                    'username' => $username,
                    'user_role' => $role,
                    'message' => $message,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        }

        header('Location: yaps.php');
        exit;
    } catch (Exception $e) {
        error_log("Error posting message: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
            exit;
        }
        header('Location: yaps.php?error=1');
        exit;
    }
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    header('Location: yaps.php');
    exit;
}
?>
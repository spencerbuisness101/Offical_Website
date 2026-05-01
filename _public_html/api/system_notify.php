<?php
/**
 * SYSTEM Notification API - Spencer's Website
 * Send notifications as SYSTEM account
 * SYSTEM notifications are INFINITE and NOT RATE LIMITED
 * 
 * Security: Only admin users can send SYSTEM notifications
 */

header('Content-Type: application/json');

// Prevent direct access
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/NotificationManager.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

// Strict authentication check - only admins can use this
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Verify admin role
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$type = $input['type'] ?? '';
$title = $input['title'] ?? '';
$message = $input['message'] ?? '';
$target = $input['target'] ?? 'single'; // single, multiple, all
$userId = intval($input['user_id'] ?? 0);
$userIds = $input['user_ids'] ?? [];
$excludeUsers = $input['exclude_users'] ?? [];
$data = $input['data'] ?? [];

if (empty($type) || empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type and title are required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $notificationManager = new NotificationManager($db);
    
    $results = [];
    $systemSenderId = NotificationManager::SYSTEM_USER_ID;
    
    switch ($target) {
        case 'single':
            // Send to single user
            if (!$userId) {
                echo json_encode(['success' => false, 'error' => 'user_id required for single target']);
                exit;
            }
            
            $result = $notificationManager->sendNotification(
                $userId, 
                $type, 
                $title, 
                $message, 
                $data, 
                $systemSenderId
            );
            
            $results = $result;
            break;
            
        case 'multiple':
            // Send to multiple users
            if (empty($userIds) || !is_array($userIds)) {
                echo json_encode(['success' => false, 'error' => 'user_ids array required for multiple target']);
                exit;
            }
            
            // Convert to integers
            $userIds = array_map('intval', $userIds);
            
            $result = $notificationManager->sendBulkNotifications(
                $userIds,
                $type,
                $title,
                $message,
                $data,
                $systemSenderId
            );
            
            $results = $result;
            break;
            
        case 'all':
            // Broadcast to ALL users (excluding specified)
            $excludeUserIds = array_map('intval', $excludeUsers);
            
            $result = $notificationManager->broadcastNotification(
                $type,
                $title,
                $message,
                $data,
                $excludeUserIds
            );
            
            $results = $result;
            break;
            
        case 'role':
            // Send to users with specific role
            $role = $input['role'] ?? '';
            if (empty($role)) {
                echo json_encode(['success' => false, 'error' => 'role required for role target']);
                exit;
            }
            
            // Get users by role
            $stmt = $db->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$role]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($userIds)) {
                echo json_encode(['success' => true, 'sent' => 0, 'message' => 'No users with this role']);
                exit;
            }
            
            $result = $notificationManager->sendBulkNotifications(
                $userIds,
                $type,
                $title,
                $message,
                $data,
                $systemSenderId
            );
            
            $results = $result;
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid target type']);
            exit;
    }
    
    // Log SYSTEM notification for audit
    $adminId = $_SESSION['user_id'];
    $adminUsername = $_SESSION['username'];
    $targetInfo = is_array($results['sent'] ?? null) ? count($results['sent']) : ($results['sent'] ?? 1);
    
    error_log("[SYSTEM NOTIFY] Admin: {$adminUsername} ({$adminId}) | Target: {$target} | Type: {$type} | Sent: {$targetInfo}");
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'is_system' => true,
        'message' => "SYSTEM notification sent successfully",
        'admin' => $adminUsername,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("SYSTEM Notify Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

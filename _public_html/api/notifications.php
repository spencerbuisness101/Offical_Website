<?php
/**
 * Notifications API - Spencer's Website
 * Real-time notification polling endpoint
 * SYSTEM notifications are infinite and not rate-limited
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Prevent direct access
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/NotificationManager.php';
require_once __DIR__ . '/../includes/csrf.php'; // v7.1: validateCsrfToken used by mark_read & mark_all_read
require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'get';

try {
    $database = new Database();
    $db = $database->getConnection();
    $notificationManager = new NotificationManager($db);
    
    switch ($action) {
        case 'get':
            // Get notifications with pagination
            $limit = min(intval($_GET['limit'] ?? 20), 50); // Max 50
            $offset = intval($_GET['offset'] ?? 0);
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $result = $notificationManager->getNotifications($userId, $limit, $offset, $unreadOnly);
            echo json_encode($result);
            break;
            
        case 'unread_count':
            // Get unread count only (for polling)
            $count = $notificationManager->getUnreadCount($userId);
            echo json_encode([
                'success' => true,
                'unread_count' => $count,
                'timestamp' => time()
            ]);
            break;
            
        case 'mark_read':
            // Mark single notification as read
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $markReadCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
            if (!validateCsrfToken($markReadCsrf)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $notificationId = intval($input['notification_id'] ?? 0);
            
            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'Notification ID required']);
                exit;
            }
            
            $success = $notificationManager->markAsRead($notificationId, $userId);
            echo json_encode(['success' => $success]);
            break;
            
        case 'mark_all_read':
            // Mark all as read
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }

            // SEC (v7.1): require CSRF token. Previously this endpoint silently
            // accepted any same-origin POST, allowing a CSRF to mark all of a
            // victim's notifications as read. Token may arrive via header
            // (X-CSRF-Token) or POST body (csrf_token) for AJAX flexibility.
            $markAllCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
            if (!validateCsrfToken($markAllCsrf)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }

            $success = $notificationManager->markAllAsRead($userId);
            echo json_encode(['success' => $success]);
            break;
            
        case 'poll':
            // Long-polling endpoint for real-time notifications
            $lastCheck = intval($_GET['last_check'] ?? 0);
            $timeout = min(intval($_GET['timeout'] ?? 30), 60); // Max 60 seconds
            
            $startTime = time();
            $notifications = [];
            
            // Poll until we get new notifications or timeout
            while (time() - $startTime < $timeout) {
                $result = $notificationManager->getNotifications($userId, 10, 0, true);
                
                if ($result['success'] && !empty($result['notifications'])) {
                    // Filter only notifications newer than last check
                    foreach ($result['notifications'] as $notification) {
                        $notificationTime = strtotime($notification['created_at']);
                        if ($notificationTime > $lastCheck) {
                            $notifications[] = $notification;
                        }
                    }
                    
                    if (!empty($notifications)) {
                        break;
                    }
                }
                
                // Sleep for 1 second before checking again
                sleep(1);
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'has_new' => !empty($notifications),
                'timestamp' => time()
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

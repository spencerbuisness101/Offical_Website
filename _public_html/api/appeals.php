<?php
/**
 * Appeals API - Phase 5 Implementation
 * 
 * Endpoints:
 * - submit: Submit new appeal from lockdown
 * - status: Check appeal status
 * - list: List user's appeals (for their history)
 * 
 * Only accessible to users in lockdown mode or reviewing their past appeals.
 */

session_start();
header('Content-Type: application/json');

define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'submit':
            // Submit new appeal
            handleSubmitAppeal($userId, $db);
            break;
            
        case 'status':
            // Get current appeal status
            handleGetStatus($userId, $db);
            break;
            
        case 'list':
            // List all appeals for this user
            handleListAppeals($userId, $db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Appeals API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle appeal submission
 */
function handleSubmitAppeal($userId, $db) {
    // Validate CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    // Rate limiting: 2 appeals per day
    // v7.1: pass true so each check counts as an attempt (preserves prior behavior).
    require_once __DIR__ . '/../includes/RateLimit.php';
    $rateLimit = new RateLimit();
    if (!$rateLimit->check('appeal', $userId, 2, 86400, true)) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'error' => 'You can only submit 2 appeals per day. Please wait before submitting another.',
            'retry_after' => $rateLimit->getRetryAfter('appeal', $userId)
        ]);
        exit;
    }
    
    // Check if user is in lockdown
    $stmt = $db->prepare("SELECT account_status, lockdown_rule FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['account_status'] !== 'restricted') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only submit appeals while in lockdown mode']);
        exit;
    }
    
    // Check for existing pending appeal
    $stmt = $db->prepare("
        SELECT id FROM lockdown_appeals 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You already have a pending appeal']);
        exit;
    }
    
    // Get and validate appeal text
    $appealText = trim($_POST['appeal_text'] ?? '');
    
    if (strlen($appealText) < 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Appeal must be at least 50 characters']);
        exit;
    }
    
    if (strlen($appealText) > 5000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Appeal cannot exceed 5000 characters']);
        exit;
    }
    
    // Insert appeal
    $stmt = $db->prepare("
        INSERT INTO lockdown_appeals 
        (user_id, appeal_text, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $appealText]);
    $appealId = $db->lastInsertId();
    
    // Send notifications
    sendAppealNotifications($userId, $appealId, $db);
    
    echo json_encode([
        'success' => true,
        'appeal_id' => $appealId,
        'message' => 'Appeal submitted successfully',
        'status' => 'pending'
    ]);
}

/**
 * Get current appeal status
 */
function handleGetStatus($userId, $db) {
    $stmt = $db->prepare("
        SELECT id, appeal_text, status, created_at, reviewed_at, admin_notes
        FROM lockdown_appeals 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appeal) {
        echo json_encode([
            'success' => true,
            'has_appeal' => false
        ]);
        exit;
    }
    
    // Format dates
    $appeal['created_at_formatted'] = date('F j, Y g:i A', strtotime($appeal['created_at']));
    if ($appeal['reviewed_at']) {
        $appeal['reviewed_at_formatted'] = date('F j, Y g:i A', strtotime($appeal['reviewed_at']));
    }
    
    echo json_encode([
        'success' => true,
        'has_appeal' => true,
        'appeal' => $appeal
    ]);
}

/**
 * List all appeals for user
 */
function handleListAppeals($userId, $db) {
    $stmt = $db->prepare("
        SELECT id, status, created_at, reviewed_at, admin_notes
        FROM lockdown_appeals 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates
    foreach ($appeals as &$appeal) {
        $appeal['created_at_formatted'] = date('F j, Y', strtotime($appeal['created_at']));
        if ($appeal['reviewed_at']) {
            $appeal['reviewed_at_formatted'] = date('F j, Y', strtotime($appeal['reviewed_at']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($appeals),
        'appeals' => $appeals
    ]);
}

/**
 * Send notifications on appeal submission
 */
function sendAppealNotifications($userId, $appealId, $db) {
    // Get user info
    $stmt = $db->prepare("SELECT username, account_tier FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return;
    
    // Get admin users
    $stmt = $db->query("SELECT id, account_tier FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Send to each admin
    foreach ($admins as $admin) {
        if ($admin['account_tier'] === 'community') {
            // Community Account: SYSTEM Tray notification (WebSocket)
            // This would integrate with your WebSocket system
            sendSystemTrayNotification($admin['id'], [
                'type' => 'NEW_APPEAL',
                'title' => 'New Lockdown Appeal',
                'message' => "User {$user['username']} has submitted an appeal",
                'link' => '/admin/review_appeals.php'
            ]);
        } else {
            // Paid Account: Store in notifications table (persistent)
            $stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, is_system, created_at)
                VALUES (?, 'system', ?, ?, TRUE, NOW())
            ");
            $stmt->execute([
                $admin['id'],
                'New Lockdown Appeal',
                "User {$user['username']} (ID: {$userId}) has submitted an appeal from lockdown. Review at: /admin/review_appeals.php"
            ]);
        }
    }
    
    // Send confirmation to user
    if ($user['account_tier'] === 'community') {
        sendSystemTrayNotification($userId, [
            'type' => 'APPEAL_CONFIRMATION',
            'title' => 'Appeal Submitted',
            'message' => 'Your appeal has been submitted and will be reviewed within 72 hours.'
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, is_system, created_at)
            VALUES (?, 'system', ?, ?, TRUE, NOW())
        ");
        $stmt->execute([
            $userId,
            'Appeal Submitted',
            'Your appeal has been submitted and will be reviewed by administrators within 72 hours. You will be notified of the decision.'
        ]);
    }
}

/**
 * Send SYSTEM Tray notification (for Community Accounts)
 * This integrates with your WebSocket-based notification system
 */
function sendSystemTrayNotification($userId, $notification) {
    // This would call your WebSocket server or SYSTEM Tray API
    // For now, we log it for the WebSocket system to pick up
    error_log("SYSTEM_NOTIFICATION: user_id={$userId}, type={$notification['type']}, message={$notification['message']}");
    
    // Store in ephemeral notifications table for WebSocket pickup
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO ephemeral_notifications 
            (user_id, type, title, message, link, created_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        $stmt->execute([
            $userId,
            $notification['type'],
            $notification['title'],
            $notification['message'],
            $notification['link'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to store ephemeral notification: " . $e->getMessage());
    }
}

<?php
/**
 * Admin Appeals API - Phase 5 Implementation
 * 
 * Endpoints for admin appeal management:
 * - list: List pending and processed appeals
 * - review: Review a specific appeal
 * - approve: Approve appeal and release lockdown
 * - deny: Deny appeal and keep lockdown
 * - stats: Get appeal statistics
 */

session_start();
header('Content-Type: application/json');

define('APP_RUNNING', true);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/PunishmentManager.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF validation for all state-changing (POST) actions
$stateChangingActions = ['approve', 'deny', 'bulk_action'];
if (in_array($action, $stateChangingActions, true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'list':
            handleListAppeals($db);
            break;
            
        case 'get':
            handleGetAppeal($db);
            break;
            
        case 'approve':
            handleApproveAppeal($db);
            break;
            
        case 'deny':
            handleDenyAppeal($db);
            break;
            
        case 'stats':
            handleGetStats($db);
            break;
            
        case 'bulk_action':
            handleBulkAction($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Admin Appeals API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * List appeals with filtering
 */
function handleListAppeals($db) {
    $status = $_GET['status'] ?? 'pending'; // pending, approved, denied, all
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $whereClause = '';
    $params = [];
    
    if ($status !== 'all') {
        $whereClause = "WHERE a.status = ?";
        $params[] = $status;
    }
    
    // Get appeals
    $stmt = $db->prepare("
        SELECT a.*, u.username, u.email_hash, u.lockdown_rule, u.lockdown_at,
               DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i') as created_formatted,
               reviewer.username as reviewed_by_username
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN users reviewer ON a.reviewed_by = reviewer.id
        {$whereClause}
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM lockdown_appeals a {$whereClause}
    ");
    if ($status !== 'all') {
        $countStmt->execute([$status]);
    } else {
        $countStmt->execute();
    }
    $totalCount = $countStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'appeals' => $appeals,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Get single appeal details
 */
function handleGetAppeal($db) {
    $appealId = intval($_GET['id'] ?? 0);
    
    if ($appealId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid appeal ID']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT a.*, u.username, u.email_hash, u.lockdown_rule, u.lockdown_at, u.lockdown_reason,
               s.rule_id, s.violation_type, s.evidence, s.created_at as strike_date,
               reviewer.username as reviewed_by_username
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN user_strikes s ON u.lockdown_strike_id = s.id
        LEFT JOIN users reviewer ON a.reviewed_by = reviewer.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appealId]);
    $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appeal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appeal not found']);
        exit;
    }
    
    // Get user's appeal history
    $stmt = $db->prepare("
        SELECT id, status, created_at, reviewed_at
        FROM lockdown_appeals
        WHERE user_id = ? AND id != ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$appeal['user_id'], $appealId]);
    $appeal['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'appeal' => $appeal
    ]);
}

/**
 * Approve appeal and release lockdown
 */
function handleApproveAppeal($db) {
    $appealId = intval($_POST['appeal_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    $sendNotification = isset($_POST['send_notification']) ? true : false;
    
    if ($appealId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid appeal ID']);
        exit;
    }
    
    // Get appeal
    $stmt = $db->prepare("
        SELECT a.*, u.id as user_id, u.username, u.account_tier
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$appealId]);
    $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appeal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appeal not found or already processed']);
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        // Update appeal status
        $stmt = $db->prepare("
            UPDATE lockdown_appeals 
            SET status = 'approved',
                reviewed_at = NOW(),
                reviewed_by = ?,
                admin_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
        
        // Release lockdown
        $punishmentManager = new PunishmentManager();
        $result = $punishmentManager->removeLockdown(
            $appeal['user_id'],
            $_SESSION['user_id'],
            $adminNotes
        );
        
        if (!$result['success']) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $result['message']]);
            exit;
        }
        
        // Send notification to user if requested
        if ($sendNotification) {
            sendAppealResultNotification(
                $appeal['user_id'],
                $appeal['username'],
                $appeal['account_tier'],
                'approved',
                $adminNotes,
                $db
            );
        }
        
        $db->commit();
        
        // Log admin action
        logAdminAction('appeal_approve', $appealId, [
            'user_id' => $appeal['user_id'],
            'admin_notes' => $adminNotes
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Appeal approved and user released from lockdown',
            'user_id' => $appeal['user_id']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Deny appeal and keep lockdown
 */
function handleDenyAppeal($db) {
    $appealId = intval($_POST['appeal_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    $sendNotification = isset($_POST['send_notification']) ? true : false;
    
    if (empty($adminNotes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Admin notes are required when denying an appeal']);
        exit;
    }
    
    if ($appealId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid appeal ID']);
        exit;
    }
    
    // Get appeal
    $stmt = $db->prepare("
        SELECT a.*, u.id as user_id, u.username, u.account_tier
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$appealId]);
    $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appeal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appeal not found or already processed']);
        exit;
    }
    
    // Update appeal status
    $stmt = $db->prepare("
        UPDATE lockdown_appeals 
        SET status = 'denied',
            reviewed_at = NOW(),
            reviewed_by = ?,
            admin_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
    
    // Send notification to user if requested
    if ($sendNotification) {
        sendAppealResultNotification(
            $appeal['user_id'],
            $appeal['username'],
            $appeal['account_tier'],
            'denied',
            $adminNotes,
            $db
        );
    }
    
    // Log admin action
    logAdminAction('appeal_deny', $appealId, [
        'user_id' => $appeal['user_id'],
        'admin_notes' => $adminNotes
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Appeal denied. User remains in lockdown.',
        'user_id' => $appeal['user_id']
    ]);
}

/**
 * Get appeal statistics
 */
function handleGetStats($db) {
    $days = intval($_GET['days'] ?? 30);
    
    $stats = [
        'pending' => 0,
        'approved' => 0,
        'denied' => 0,
        'total' => 0,
        'avg_review_time_hours' => 0
    ];
    
    // Count by status
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM lockdown_appeals 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY status
    ");
    $stmt->execute([$days]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['pending'] = $counts['pending'] ?? 0;
    $stats['approved'] = $counts['approved'] ?? 0;
    $stats['denied'] = $counts['denied'] ?? 0;
    $stats['total'] = array_sum($counts);
    
    // Average review time for processed appeals
    $stmt = $db->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_hours
        FROM lockdown_appeals 
        WHERE status != 'pending'
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$days]);
    $avgTime = $stmt->fetchColumn();
    $stats['avg_review_time_hours'] = round($avgTime ?? 0, 1);
    
    // Pending over 72 hours (SLA breach)
    $stmt = $db->query("
        SELECT COUNT(*) FROM lockdown_appeals 
        WHERE status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 72 HOUR)
    ");
    $stats['pending_over_72h'] = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'period_days' => $days
    ]);
}

/**
 * Bulk action on multiple appeals
 */
function handleBulkAction($db) {
    $appealIds = $_POST['appeal_ids'] ?? [];
    $action = $_POST['bulk_action'] ?? ''; // approve, deny
    $adminNotes = trim($_POST['bulk_notes'] ?? '');
    
    if (empty($appealIds) || !in_array($action, ['approve', 'deny'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid bulk action']);
        exit;
    }
    
    if ($action === 'deny' && empty($adminNotes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Admin notes required for bulk deny']);
        exit;
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    $punishmentManager = new PunishmentManager();
    
    foreach ($appealIds as $appealId) {
        $appealId = intval($appealId);
        
        // Get appeal
        $stmt = $db->prepare("
            SELECT a.*, u.id as user_id, u.username, u.account_tier
            FROM lockdown_appeals a
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND a.status = 'pending'
        ");
        $stmt->execute([$appealId]);
        $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appeal) {
            $errorCount++;
            continue;
        }
        
        try {
            if ($action === 'approve') {
                // Update appeal
                $stmt = $db->prepare("
                    UPDATE lockdown_appeals 
                    SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?, admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
                
                // Release lockdown
                $punishmentManager->removeLockdown($appeal['user_id'], $_SESSION['user_id'], $adminNotes);
                
            } else {
                // Deny
                $stmt = $db->prepare("
                    UPDATE lockdown_appeals 
                    SET status = 'denied', reviewed_at = NOW(), reviewed_by = ?, admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
            }
            
            // Send notification
            sendAppealResultNotification(
                $appeal['user_id'],
                $appeal['username'],
                $appeal['account_tier'],
                $action,
                $adminNotes,
                $db
            );
            
            $successCount++;
            
        } catch (Exception $e) {
            $errorCount++;
            error_log("Bulk appeal action error for ID {$appealId}: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => count($appealIds),
        'succeeded' => $successCount,
        'failed' => $errorCount
    ]);
}

/**
 * Send appeal result notification to user
 */
function sendAppealResultNotification($userId, $username, $accountTier, $result, $adminNotes, $db) {
    $title = $result === 'approved' ? 'Appeal Approved' : 'Appeal Denied';
    
    if ($result === 'approved') {
        $message = "Your appeal has been approved and your account lockdown has been removed. You can now access all features of the platform.";
        if ($adminNotes) {
            $message .= "\n\nAdmin notes: {$adminNotes}";
        }
    } else {
        $message = "Your appeal has been denied. Your account remains in lockdown mode. You may submit another appeal after 7 days.";
        if ($adminNotes) {
            $message .= "\n\nReason: {$adminNotes}";
        }
    }
    
    if ($accountTier === 'community') {
        // Community Account: SYSTEM Tray notification
        $stmt = $db->prepare("
            INSERT INTO ephemeral_notifications 
            (user_id, type, title, message, created_at, expires_at)
            VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        $stmt->execute([$userId, 'APPEAL_RESULT', $title, $message]);
    } else {
        // Paid Account: Persistent notification
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, is_system, created_at)
            VALUES (?, 'system', ?, ?, TRUE, NOW())
        ");
        $stmt->execute([$userId, $title, $message]);
    }
}

/**
 * Log admin action for audit trail
 */
function logAdminAction($action, $targetId, $details) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO admin_audit_log 
            (admin_id, action, target_type, target_id, details, created_at)
            VALUES (?, ?, 'appeal', ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $targetId,
            json_encode($details)
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}

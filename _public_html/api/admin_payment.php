<?php
/**
 * Admin Payment Actions API - Spencer's Website v7.0
 * POST endpoint for admin payment management actions:
 *   - manual_renewal: Extend subscription for offline/manual payments
 *   - deny_refund: Deny a pending refund request
 *   - admin_suspend_user: Manually suspend a user
 *   - admin_unsuspend_user: Manually unsuspend a user
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/subscription.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Must be admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$adminUserId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$targetUserId = (int)($_POST['target_user_id'] ?? 0);

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensurePaymentTables($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Verify target user exists
$stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}

switch ($action) {
    case 'manual_renewal':
        $daysToAdd = (int)($_POST['days'] ?? 30);
        if ($daysToAdd < 1 || $daysToAdd > 365) {
            echo json_encode(['success' => false, 'error' => 'Days must be between 1 and 365.']);
            exit;
        }

        $result = renewSubscription($db, $targetUserId, $daysToAdd);
        if ($result) {
            // Clear suspension if active
            unsuspendUser($db, $targetUserId);

            // Log action
            $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'manual_renewal', ?, NOW())")
                ->execute([$adminUserId, $targetUserId, json_encode(['days_added' => $daysToAdd])]);

            echo json_encode(['success' => true, 'message' => "Extended subscription by $daysToAdd days for {$targetUser['username']}."]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to renew subscription. User may not have an active subscription record.']);
        }
        break;

    case 'deny_refund':
        $refundRequestId = (int)($_POST['refund_request_id'] ?? 0);
        $denyReason = trim($_POST['deny_reason'] ?? '');

        if ($refundRequestId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid refund request ID.']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, user_id, status, amount_cents, reason FROM refund_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$refundRequestId]);
        $refundReq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refundReq) {
            echo json_encode(['success' => false, 'error' => 'Pending refund request not found.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE refund_requests SET status = 'denied', processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$adminUserId, $refundRequestId]);

        $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'deny_refund', ?, NOW())")
            ->execute([$adminUserId, $refundReq['user_id'], json_encode(['refund_request_id' => $refundRequestId, 'reason' => $denyReason])]);

        echo json_encode(['success' => true, 'message' => 'Refund request denied.']);
        break;

    case 'admin_suspend_user':
        $reason = trim($_POST['reason'] ?? 'Admin suspended');

        // Don't suspend admins
        if ($targetUser['role'] === 'admin') {
            echo json_encode(['success' => false, 'error' => 'Cannot suspend admin accounts.']);
            exit;
        }

        suspendUser($db, $targetUserId, $reason);

        $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'admin_suspend', ?, NOW())")
            ->execute([$adminUserId, $targetUserId, json_encode(['reason' => $reason])]);

        echo json_encode(['success' => true, 'message' => "User {$targetUser['username']} has been suspended."]);
        break;

    case 'admin_unsuspend_user':
        unsuspendUser($db, $targetUserId);

        $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'admin_unsuspend', ?, NOW())")
            ->execute([$adminUserId, $targetUserId, json_encode([])]);

        echo json_encode(['success' => true, 'message' => "User {$targetUser['username']} has been unsuspended."]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}

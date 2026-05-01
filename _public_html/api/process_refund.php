<?php
/**
 * Process Refund API - Spencer's Website v7.0
 * POST endpoint for admins to approve/process a refund request.
 * Calls Stripe or PayPal refund API, downgrades user, logs action.
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
$refundRequestId = (int)($_POST['refund_request_id'] ?? 0);

if ($refundRequestId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid refund request ID.']);
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

// Load refund request
$stmt = $db->prepare("SELECT id, user_id, status, provider, provider_payment_id, amount_cents FROM refund_requests WHERE id = ?");
$stmt->execute([$refundRequestId]);
$refundReq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refundReq) {
    echo json_encode(['success' => false, 'error' => 'Refund request not found.']);
    exit;
}

if ($refundReq['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'This refund request has already been processed (status: ' . $refundReq['status'] . ').']);
    exit;
}

$targetUserId = (int)$refundReq['user_id'];
$provider = $refundReq['provider'];
$providerPaymentId = $refundReq['provider_payment_id'];
$amountCents = (int)($refundReq['amount_cents'] ?? 0);

// Process refund via provider API
$refundResult = ['success' => false, 'error' => 'No provider payment ID available'];

if ($providerPaymentId) {
    if ($provider === 'stripe') {
        $refundResult = processStripeRefund($providerPaymentId, $amountCents > 0 ? $amountCents : null);
    } else {
        $refundResult = ['success' => false, 'error' => 'Unknown provider: ' . $provider];
    }
}

if ($refundResult['success']) {
    try {
        $db->beginTransaction();

        // Update refund request
        $stmt = $db->prepare("UPDATE refund_requests SET status = 'processed', provider_refund_id = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$refundResult['refund_id'], $adminUserId, $refundRequestId]);

        // Mark payment session as refunded
        if ($refundReq['payment_session_id']) {
            $db->prepare("UPDATE payment_sessions SET status = 'refunded' WHERE id = ?")->execute([$refundReq['payment_session_id']]);
        }

        // Cancel subscription and downgrade user
        downgradeUserToCommunity($db, $targetUserId);

        // Log admin action
        $stmt = $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'process_refund', ?, NOW())");
        $stmt->execute([
            $adminUserId,
            $targetUserId,
            json_encode([
                'refund_request_id' => $refundRequestId,
                'provider' => $provider,
                'provider_refund_id' => $refundResult['refund_id'],
                'amount_cents' => $amountCents
            ])
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Refund processed successfully.',
            'refund_id' => $refundResult['refund_id']
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Process refund DB error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Refund was issued by provider but database update failed. Check logs.']);
    }
} else {
    // Update refund request status to failed
    $stmt = $db->prepare("UPDATE refund_requests SET status = 'failed', processed_by = ?, processed_at = NOW() WHERE id = ?");
    $stmt->execute([$adminUserId, $refundRequestId]);

    // Log the failed attempt
    $stmt = $db->prepare("INSERT INTO admin_payment_actions (admin_user_id, target_user_id, action_type, details, created_at) VALUES (?, ?, 'refund_failed', ?, NOW())");
    $stmt->execute([
        $adminUserId,
        $targetUserId,
        json_encode([
            'refund_request_id' => $refundRequestId,
            'provider' => $provider,
            'error' => $refundResult['error']
        ])
    ]);

    echo json_encode(['success' => false, 'error' => 'Provider refund failed: ' . ($refundResult['error'] ?? 'Unknown error')]);
}

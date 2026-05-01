<?php
/**
 * Refund Request API - Spencer's Website v7.0
 * POST endpoint for users to request a refund.
 * v7.0: 48-hour refund window enforced. Feedback visible to admin.
 * Rate limited: 3/hour per IP. Requires nonce + CSRF.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/subscription.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    ensurePaymentTables($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// IP rate limit: 3 per hour
enforceIpRateLimit($db, 'refund_request', 3, 3600);

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Nonce validation
if (!validatePaymentNonce($db, 'refund_request', $_POST['nonce'] ?? '', $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired request token. Please refresh the page.']);
    exit;
}

// Validate inputs
$reason = trim($_POST['reason'] ?? '');
$feedback = trim($_POST['feedback'] ?? '');

$validReasons = ['not_useful', 'too_expensive', 'found_alternative', 'technical_issues', 'other'];
if (!in_array($reason, $validReasons)) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid reason.']);
    exit;
}

if (strlen($feedback) < 20) {
    echo json_encode(['success' => false, 'error' => 'Feedback must be at least 20 characters.']);
    exit;
}

// Race-condition-safe refund request: transaction + row lock
try {
    $db->beginTransaction();

    // Check for existing pending refund with row lock to prevent race condition (CREV-01)
    $stmt = $db->prepare("SELECT id FROM refund_requests WHERE user_id = ? AND status IN ('pending','approved') LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'You already have a pending refund request.']);
        exit;
    }

    // Get subscription and payment info
    $premium = getUserPremium($db, $userId);
    $activeSub = getLatestSubscription($db, $userId);

    // Find the most recent paid payment session for this user
    $stmt = $db->prepare("SELECT id, provider, provider_session_id, amount_cents, completed_at FROM payment_sessions WHERE user_id = ? AND status = 'paid' ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    // v7.0: Enforce 48-hour refund window
    if ($lastPayment && $lastPayment['completed_at']) {
        $purchaseTime = strtotime($lastPayment['completed_at']);
        $hoursSincePurchase = (time() - $purchaseTime) / 3600;
        if ($hoursSincePurchase > 48) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'error' => 'Refund requests must be submitted within 48 hours of purchase. Your purchase was ' . round($hoursSincePurchase) . ' hours ago.'
            ]);
            exit;
        }
    } elseif (!$lastPayment) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'No paid purchase found for your account.']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO refund_requests (user_id, subscription_id, payment_session_id, provider, provider_payment_id, amount_cents, reason, feedback, status, requested_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'user')
    ");
    $stmt->execute([
        $userId,
        $activeSub['id'] ?? null,
        $lastPayment['id'] ?? null,
        $lastPayment['provider'] ?? $premium['provider'] ?? null,
        $lastPayment['provider_session_id'] ?? null,
        $lastPayment['amount_cents'] ?? (($premium['plan_type'] ?? '') === 'monthly' ? 200 : 5000),
        $reason,
        $feedback
    ]);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Refund request submitted successfully.']);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Refund request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to submit request. Please try again.']);
}

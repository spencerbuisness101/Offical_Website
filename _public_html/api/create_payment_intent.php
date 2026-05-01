<?php
/**
 * Create Payment Intent API - Spencer's Website v7.0
 * POST endpoint that creates a Stripe Payment Intent using dynamic amounts.
 * Supports: 3 products (monthly/yearly/lifetime) + freeform donations ($1–$100).
 * Returns: JSON with client_secret for Stripe Elements embedded form.
 *
 * Required POST params:
 *   - csrf_token
 *   - product_id (int) OR is_donation=1 + amount (float, $1–$100)
 * Optional:
 *   - donation_feedback (string, max 500 chars) — only for donations
 *   - user_id (int) — for existing-user upgrades
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!arePaymentsEnabled($db)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Payments are currently disabled.']);
        exit;
    }
    
    ensurePaymentTables($db);
} catch (Exception $e) {
    error_log("PaymentIntent DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit;
}

// IP-based rate limit: 5 requests/minute
enforceIpRateLimit($db, 'payment_intent', 5, 60);

// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse input
$isDonation = filter_var($_POST['is_donation'] ?? false, FILTER_VALIDATE_BOOLEAN);
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : null;
$existingUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : null;
$donationFeedback = trim($_POST['donation_feedback'] ?? '');

// Authorization check: existingUserId must match current user or user must be admin
if ($existingUserId !== null && $existingUserId !== $_SESSION['user_id']) {
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized user ID']);
        exit;
    }
}

// Validate: must provide either product_id or is_donation
if (!$isDonation && !$productId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing product_id or is_donation flag.']);
    exit;
}

// --- Donation flow ---
if ($isDonation) {
    $amountRaw = $_POST['amount'] ?? null;
    if ($amountRaw === null || !is_numeric($amountRaw)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid donation amount.']);
        exit;
    }

    $amountCents = (int) round((float) $amountRaw * 100);
    $validation = validateDonationAmount($amountCents);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $validation['error']]);
        exit;
    }

    // Validate feedback length
    if (strlen($donationFeedback) > DONATION_FEEDBACK_MAX_LENGTH) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Feedback must be ' . DONATION_FEEDBACK_MAX_LENGTH . ' characters or less.']);
        exit;
    }

    // Sanitize feedback
    $donationFeedback = htmlspecialchars($donationFeedback, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $productName = "Donation to Spencer's Website";
    $planType = 'donation';

} else {
    // --- Product flow ---
    $product = getProductById($db, $productId);
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found or inactive.']);
        exit;
    }

    $amountCents = (int) $product['price_cents'];
    $planType = $product['plan_type'] ?? 'lifetime';
    $productName = "Spencer's Website - " . $product['name'];

    // For existing-user upgrades, verify session
    if ($existingUserId !== null) {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'You must be logged in to upgrade.']);
            exit;
        }
        if ((int) $_SESSION['user_id'] !== $existingUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'User ID mismatch.']);
            exit;
        }
        $userRole = $_SESSION['role'] ?? '';
        $isSuspended = $_SESSION['is_suspended'] ?? false;
        if ($userRole !== 'community' && !($userRole === 'user' && $isSuspended)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Your account already has this role or higher.']);
            exit;
        }
    }
}

// Fraud detection (for logged-in users)
$fraudUserId = $existingUserId ?? ($_SESSION['user_id'] ?? 0);
$fraudIp = $_SERVER['REMOTE_ADDR'] ?? '';
if ($fraudUserId && $fraudIp) {
    $fraudCheck = checkPaymentFraud($db, (int) $fraudUserId, $fraudIp);
    if (!$fraudCheck['allowed']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Payment blocked due to suspicious activity. Please contact support.']);
        exit;
    }
}

// Generate idempotency key and payment token
$idempotencyKey = generateIdempotencyKey();
$paymentToken = generatePaymentToken();

// Create payment session record
createPaymentSession($db, $paymentToken, 'stripe', $existingUserId, $planType, $idempotencyKey);

// Update payment session with v7.0 fields
$updateStmt = $db->prepare("UPDATE payment_sessions SET product_id = ?, is_donation = ?, donation_feedback = ? WHERE token = ?");
$updateStmt->execute([$productId, $isDonation ? 1 : 0, $donationFeedback ?: null, $paymentToken]);

// Build metadata for Stripe
$metadata = [
    'payment_token' => $paymentToken,
    'plan_type' => $planType,
    'product_id' => (string) ($productId ?? ''),
    'is_donation' => $isDonation ? '1' : '0',
    'user_id' => (string) ($existingUserId ?? ''),
];

// Create Stripe Payment Intent
$intentResult = createStripePaymentIntent($amountCents, 'usd', $metadata);

if (!$intentResult['success']) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Failed to create payment: ' . ($intentResult['error'] ?? 'Unknown error')]);
    exit;
}

// Store Payment Intent ID in payment session
$stmt = $db->prepare("UPDATE payment_sessions SET payment_intent_id = ?, provider_session_id = ? WHERE token = ?");
$stmt->execute([$intentResult['payment_intent_id'], $intentResult['payment_intent_id'], $paymentToken]);

// If donation, also create a donations record
if ($isDonation) {
    $donStmt = $db->prepare("INSERT INTO donations (user_id, amount_cents, currency, feedback, payment_intent_id, ip_address, status) VALUES (?, ?, 'usd', ?, ?, ?, 'pending')");
    $donStmt->execute([
        $existingUserId ?? ($_SESSION['user_id'] ?? null),
        $amountCents,
        $donationFeedback ?: null,
        $intentResult['payment_intent_id'],
        $fraudIp,
    ]);
}

// Return client_secret for frontend Stripe Elements mounting
$siteUrl = rtrim(getenv('SITE_URL') ?: 'https://thespencerwebsite.com', '/');
$hmac = generateCallbackHmac($paymentToken);

echo json_encode([
    'success' => true,
    'client_secret' => $intentResult['client_secret'],
    'payment_intent_id' => $intentResult['payment_intent_id'],
    'hmac' => $hmac,
    'amount_cents' => $amountCents,
    'is_donation' => $isDonation,
]);
exit;

<?php
/**
 * Create Checkout Session API - Spencer's Website v7.0
 * POST endpoint that creates a Stripe checkout session.
 * Stripe Checkout natively shows Card + PayPal (when enabled in dashboard).
 * Supports monthly ($2/mo), yearly ($30/yr), and lifetime ($100) plans.
 *
 * Accepts: provider (stripe), plan_type (monthly|yearly|lifetime), optional user_id
 * Returns: JSON with redirect URL
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

// Database connection (needed for IP rate limiting)
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
    error_log("Checkout DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit;
}

// IP-based rate limit: 5 requests/minute
enforceIpRateLimit($db, 'checkout', 5, 60);

// CSRF validation
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse input
$provider = $_POST['provider'] ?? '';
$planType = $_POST['plan_type'] ?? 'lifetime';
$existingUserId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

if ($provider !== 'stripe') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment provider. Use "stripe".']);
    exit;
}

if (!in_array($planType, ['monthly', 'yearly', 'lifetime'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid plan type. Use "monthly", "yearly", or "lifetime".']);
    exit;
}

// For existing-user upgrades, verify they are logged in and it's their own user_id
if ($existingUserId !== null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'You must be logged in to upgrade.']);
        exit;
    }
    if ((int)$_SESSION['user_id'] !== $existingUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'User ID mismatch.']);
        exit;
    }
    // Check they are community role OR suspended user looking to resubscribe
    $userRole = $_SESSION['role'] ?? '';
    $isSuspended = $_SESSION['is_suspended'] ?? false;
    if ($userRole !== 'community' && !($userRole === 'user' && $isSuspended)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Your account already has this role or higher.']);
        exit;
    }
}

// v7.0: Payment fraud detection
$fraudUserId = $existingUserId ?? ($_SESSION['user_id'] ?? 0);
$fraudIp = $_SERVER['REMOTE_ADDR'] ?? '';
if ($fraudUserId && $fraudIp) {
    $fraudCheck = checkPaymentFraud($db, (int)$fraudUserId, $fraudIp);
    if (!$fraudCheck['allowed']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Payment blocked due to suspicious activity. Please contact support.']);
        exit;
    }
}

// Generate idempotency key and check for duplicates
$idempotencyKey = generateIdempotencyKey();

// Generate payment token and create session row
$paymentToken = generatePaymentToken();
createPaymentSession($db, $paymentToken, $provider, $existingUserId, $planType, $idempotencyKey);

$siteUrl = rtrim(getenv('SITE_URL') ?: 'https://thespencerwebsite.com', '/');
$hmac = generateCallbackHmac($paymentToken);

// Determine success URL (with HMAC signature)
if ($existingUserId) {
    $successUrl = $siteUrl . '/main.php?upgrade=success&token=' . urlencode($paymentToken) . '&hmac=' . urlencode($hmac);
    $cancelUrl = $siteUrl . '/main.php?upgrade=cancelled';
} else {
    $successUrl = $siteUrl . '/register.php?token=' . urlencode($paymentToken) . '&hmac=' . urlencode($hmac);
    $cancelUrl = $siteUrl . '/index.php';
}

$amountCents = getPlanAmount($planType);
$amountDollars = number_format($amountCents / 100, 2, '.', '');
$planNames = ['monthly' => 'Monthly Plan', 'yearly' => 'Yearly Plan', 'lifetime' => 'Lifetime Access'];
$productName = "Spencer's Website - " . ($planNames[$planType] ?? 'Plan');

// --- Stripe Path ---
if ($provider === 'stripe') {
    $stripeSecret = getenv('STRIPE_SECRET_KEY');
    if (!$stripeSecret) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Stripe is not configured.']);
        exit;
    }

    if ($planType === 'monthly') {
        // Monthly subscription — use pre-created price ID
        $stripePriceId = getenv('STRIPE_MONTHLY_PRICE_ID');
        if (!$stripePriceId) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Monthly plan is not configured in Stripe.']);
            exit;
        }

        $postFields = http_build_query([
            'mode' => 'subscription',
            'line_items[0][price]' => $stripePriceId,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[payment_token]' => $paymentToken,
            'metadata[plan_type]' => 'monthly',
        ]);
    } elseif ($planType === 'yearly') {
        // Yearly subscription — use pre-created price ID
        $stripePriceId = getenv('STRIPE_YEARLY_PRICE_ID');
        if (!$stripePriceId) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Yearly plan is not configured in Stripe.']);
            exit;
        }

        $postFields = http_build_query([
            'mode' => 'subscription',
            'line_items[0][price]' => $stripePriceId,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[payment_token]' => $paymentToken,
            'metadata[plan_type]' => 'yearly',
        ]);
    } else {
        // Lifetime — one-time payment
        $postFields = http_build_query([
            'mode' => 'payment',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][product_data][name]' => $productName,
            'line_items[0][price_data][unit_amount]' => $amountCents,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[payment_token]' => $paymentToken,
            'metadata[plan_type]' => 'lifetime',
        ]);
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $stripeSecret,
            'Content-Type: application/x-www-form-urlencoded',
            'Idempotency-Key: ' . $idempotencyKey,
            'Stripe-Version: 2024-12-18.acacia',
        ],
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("Stripe checkout creation failed: HTTP $httpCode - " . substr($response ?? '', 0, 200));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Failed to create Stripe checkout session.']);
        exit;
    }

    $stripeData = json_decode($response, true);
    $redirectUrl = $stripeData['url'] ?? null;

    if (!$redirectUrl) {
        error_log("Stripe response missing URL (truncated): " . substr($response ?? '', 0, 200));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Invalid Stripe response.']);
        exit;
    }

    // Store Stripe session ID
    $stmt = $db->prepare("UPDATE payment_sessions SET provider_session_id = ? WHERE token = ?");
    $stmt->execute([$stripeData['id'] ?? '', $paymentToken]);

    echo json_encode(['success' => true, 'redirect_url' => $redirectUrl]);
    exit;
}

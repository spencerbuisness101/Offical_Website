<?php
/**
 * Payment Helper Functions - Spencer's Website v7.0
 * Shared utilities for Stripe Payment Intents, Turnstile, payment token management,
 * subscriptions, refunds, HMAC, idempotency, nonces, webhook audit logging, and donations.
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Plan pricing constants (in cents)
define('PLAN_MONTHLY_CENTS', 200);    // $2.00
define('PLAN_YEARLY_CENTS', 2000);    // $20.00
define('PLAN_LIFETIME_CENTS', 10000); // $100.00

// Donation limits (in cents)
define('DONATION_MIN_CENTS', 100);    // $1.00
define('DONATION_MAX_CENTS', 10000);  // $100.00
define('DONATION_FEEDBACK_MAX_LENGTH', 500);

/**
 * Get expected amount in cents for a plan type.
 */
function getPlanAmount(string $planType): int {
    return match ($planType) {
        'monthly'  => PLAN_MONTHLY_CENTS,
        'yearly'   => PLAN_YEARLY_CENTS,
        'lifetime' => PLAN_LIFETIME_CENTS,
        default    => PLAN_LIFETIME_CENTS,
    };
}

/**
 * Validate a donation amount in cents.
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateDonationAmount(int $amountCents): array {
    if ($amountCents < DONATION_MIN_CENTS) {
        return ['valid' => false, 'error' => 'Minimum donation is $' . number_format(DONATION_MIN_CENTS / 100, 2)];
    }
    if ($amountCents > DONATION_MAX_CENTS) {
        return ['valid' => false, 'error' => 'Maximum donation is $' . number_format(DONATION_MAX_CENTS / 100, 2)];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Get a product by ID from the products table.
 * @return array|false
 */
function getProductById(PDO $db, int $productId): array|false {
    $stmt = $db->prepare("SELECT id, name, description, price_cents, is_active, plan_type FROM products WHERE id = ? AND is_active = TRUE LIMIT 1");
    $stmt->execute([$productId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all active products.
 * @return array
 */
function getActiveProducts(PDO $db): array {
    $stmt = $db->query("SELECT id, name, description, price_cents, is_active, plan_type FROM products WHERE is_active = TRUE ORDER BY price_cents ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a Stripe Payment Intent via cURL.
 * @param int $amountCents Amount in cents
 * @param string $currency Currency code (default 'usd')
 * @param array $metadata Key-value metadata for the intent
 * @return array ['success' => bool, 'client_secret' => string|null, 'payment_intent_id' => string|null, 'error' => string|null]
 */
function createStripePaymentIntent(int $amountCents, string $currency = 'usd', array $metadata = []): array {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey) {
        return ['success' => false, 'client_secret' => null, 'payment_intent_id' => null, 'error' => 'Stripe not configured'];
    }

    $fields = [
        'amount' => $amountCents,
        'currency' => $currency,
        'automatic_payment_methods[enabled]' => 'true',
    ];

    foreach ($metadata as $key => $value) {
        $fields["metadata[$key]"] = (string) $value;
    }

    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2024-12-18.acacia',
        ],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'client_secret' => $data['client_secret'] ?? null,
            'payment_intent_id' => $data['id'] ?? null,
            'error' => null,
        ];
    }

    $errData = json_decode($response, true);
    $errMsg = $errData['error']['message'] ?? "HTTP $httpCode";
    error_log("Stripe PaymentIntent creation failed: $errMsg");
    return ['success' => false, 'client_secret' => null, 'payment_intent_id' => null, 'error' => $errMsg];
}

/**
 * Retrieve a Stripe Payment Intent via cURL.
 * @param string $paymentIntentId
 * @return array|false
 */
function verifyStripePaymentIntent(string $paymentIntentId): array|false {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey || $paymentIntentId === '') {
        return false;
    }

    $ch = curl_init('https://api.stripe.com/v1/payment_intents/' . urlencode($paymentIntentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Stripe-Version: 2024-12-18.acacia',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return false;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : false;
}

/**
 * Generate a cryptographically secure payment token.
 * @return string 64-character hex token
 */
function generatePaymentToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Look up a payment session by token, checking expiry.
 * @param PDO $db
 * @param string $token
 * @return array|false  The row if valid, false otherwise
 */
function getPaymentSession($db, $token) {
    $stmt = $db->prepare("
        SELECT id, token, user_id, data, expires_at, used, created_at FROM payment_sessions
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Mark a payment token as consumed (used = true).
 * @param PDO $db
 * @param string $token
 * @return bool
 */
function markPaymentUsed($db, $token) {
    $stmt = $db->prepare("UPDATE payment_sessions SET used = TRUE WHERE token = ?");
    return $stmt->execute([$token]);
}

/**
 * Create a new payment session row.
 * @param PDO $db
 * @param string $token
 * @param string $provider  'stripe' or 'paypal'
 * @param int|null $userId  NULL for new registrations
 * @param string $planType  'monthly' or 'lifetime'
 * @param string|null $idempotencyKey
 * @return bool
 */
function createPaymentSession($db, $token, $provider, $userId = null, $planType = 'lifetime', $idempotencyKey = null) {
    $amountCents = getPlanAmount($planType);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $hmac = generateCallbackHmac($token);

    $stmt = $db->prepare("
        INSERT INTO payment_sessions (token, provider, user_id, plan_type, amount_cents, idempotency_key, ip_address, callback_hmac, status, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
    ");
    return $stmt->execute([$token, $provider, $userId, $planType, $amountCents, $idempotencyKey, $ip, $hmac]);
}

/**
 * Update payment session status after provider confirmation.
 * @param PDO $db
 * @param string $token
 * @param string $status  'paid', 'failed', 'expired', 'refunded'
 * @param string|null $providerSessionId  Provider's transaction/session ID
 * @return bool
 */
function updatePaymentStatus($db, $token, $status, $providerSessionId = null) {
    $stmt = $db->prepare("
        UPDATE payment_sessions
        SET status = ?, provider_session_id = ?, completed_at = NOW()
        WHERE token = ?
    ");
    return $stmt->execute([$status, $providerSessionId, $token]);
}

// =====================================================================
// Provider Verification (existing, preserved)
// =====================================================================

/**
 * Verify a Stripe Checkout Session via cURL.
 */
function verifyStripeSession($sessionId) {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey) return false;

    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($sessionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return false;
    return json_decode($response, true);
}

/**
 * Verify Google reCAPTCHA v3 token.
 * Returns true if valid and score >= threshold, false otherwise.
 * Uses cURL with file_get_contents fallback for Hostinger compatibility.
 * Includes rate limiting (30 requests per 60s per IP) to prevent quota exhaustion.
 * @param string $token The g-recaptcha-response token from the client
 * @param float $scoreThreshold Minimum acceptable score (0.0-1.0, default 0.5)
 * @return bool
 */
function verifyRecaptcha($token, $scoreThreshold = 0.5) {
    // --- Rate limiting: 30 captcha verifications per 60s per IP ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $window = 60;
    $maxAttempts = 30;
    $key = 'recaptcha_limit_' . $ip;

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_at' => $now + $window];
    }
    if ($now > $_SESSION[$key]['reset_at']) {
        $_SESSION[$key] = ['count' => 0, 'reset_at' => $now + $window];
    }
    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        error_log("RECAPTCHA: Rate limit exceeded for IP {$ip}");
        return false;
    }
    $_SESSION[$key]['count']++;
    // --- End rate limiting ---

    $secret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : (getenv('RECAPTCHA_SECRET_KEY') ?: '');
    if (!$secret) {
        error_log("RECAPTCHA: Secret key not configured");
        return false;
    }
    if (empty($token)) {
        error_log("RECAPTCHA: Empty token received");
        return false;
    }

    $postData = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || !$response) {
            error_log("RECAPTCHA: cURL failed - Error: {$curlError}, HTTP: {$httpCode}");
            $response = null;
        }
    }

    if ($response === null && ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData,
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log("RECAPTCHA: file_get_contents fallback also failed");
            return false;
        }
    }

    if (!$response) {
        error_log("RECAPTCHA: No response from Google (both methods failed)");
        return false;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("RECAPTCHA: Invalid JSON response: " . substr($response, 0, 200));
        return false;
    }

    if (empty($data['success'])) {
        error_log("RECAPTCHA: Verification rejected - error codes: " . implode(', ', $data['error-codes'] ?? ['none']));
        return false;
    }

    $score = (float)($data['score'] ?? 0);
    if ($score < $scoreThreshold) {
        error_log("RECAPTCHA: Score too low ({$score} < {$scoreThreshold})");
        return false;
    }

    return true;
}

// NOTE: Cloudflare Turnstile support removed in v7.0 — replaced by Google reCAPTCHA v3.
// See verifyRecaptcha() earlier in this file for the active captcha verifier.

// =====================================================================
// User Role Management (updated)
// =====================================================================

/**
 * Upgrade an existing user's role to 'user' and record premium status.
 * @param PDO $db
 * @param int $userId
 * @param int|null $paymentSessionId
 * @param string $planType 'monthly' or 'lifetime'
 * @param string|null $provider 'stripe', 'paypal', or 'admin_manual'
 * @param string|null $providerSubscriptionId
 * @return bool
 */
function upgradeUserRole($db, $userId, $paymentSessionId = null, $planType = 'lifetime', $provider = null, $providerSubscriptionId = null) {
    try {
        $db->beginTransaction();

        // Only upgrade if current role is community (don't downgrade higher roles)
        // Also clear suspension if re-subscribing
        $stmt = $db->prepare("UPDATE users SET role = 'user', is_suspended = FALSE, suspended_at = NULL, suspension_reason = NULL WHERE id = ? AND role = 'community'");
        $stmt->execute([$userId]);

        // Also unsuspend if they were a suspended 'user'
        $stmt2 = $db->prepare("UPDATE users SET is_suspended = FALSE, suspended_at = NULL, suspension_reason = NULL WHERE id = ? AND role = 'user' AND is_suspended = TRUE");
        $stmt2->execute([$userId]);

        // Calculate period end as a PHP date string (null for lifetime) — never interpolated into SQL
        $periodEnd = null;
        if ($planType === 'monthly') {
            $periodEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
        } elseif ($planType === 'yearly') {
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 year'));
        }

        // Insert or update premium record — $periodEnd is a bound parameter, never interpolated
        $stmt = $db->prepare("
            INSERT INTO user_premium (user_id, is_premium, premium_since, payment_session_id, plan_type, provider, last_payment_at,
                stripe_subscription_id, paypal_subscription_id, subscription_status, current_period_end)
            VALUES (?, TRUE, NOW(), ?, ?, ?, NOW(),
                ?, ?, 'active', ?)
            ON DUPLICATE KEY UPDATE
                is_premium = TRUE,
                payment_session_id = VALUES(payment_session_id),
                plan_type = VALUES(plan_type),
                provider = VALUES(provider),
                last_payment_at = NOW(),
                stripe_subscription_id = COALESCE(VALUES(stripe_subscription_id), stripe_subscription_id),
                paypal_subscription_id = COALESCE(VALUES(paypal_subscription_id), paypal_subscription_id),
                subscription_status = 'active',
                current_period_end = VALUES(current_period_end)
        ");
        $stripeSubId = ($provider === 'stripe') ? $providerSubscriptionId : null;
        $paypalSubId = ($provider === 'paypal') ? $providerSubscriptionId : null;
        $stmt->execute([$userId, $paymentSessionId, $planType, $provider, $stripeSubId, $paypalSubId, $periodEnd]);

        // Create subscription ledger entry
        $stmt = $db->prepare("
            INSERT INTO subscriptions (user_id, plan_type, provider, provider_subscription_id, status, amount_cents,
                current_period_start, current_period_end)
            VALUES (?, ?, ?, ?, 'active', ?, NOW(), ?)
        ");
        $stmt->execute([$userId, $planType, $provider ?? 'stripe', $providerSubscriptionId, getPlanAmount($planType), $periodEnd]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Role upgrade failed for user $userId: " . $e->getMessage());
        return false;
    }
}

// =====================================================================
// Amount Verification
// =====================================================================

/**
 * Verify that the reported payment amount matches what we expect for the plan.
 * @param int $reportedCents Amount reported by webhook (in cents)
 * @param string $planType 'monthly' or 'lifetime'
 * @return bool
 */
function verifyPaymentAmount($reportedCents, $planType) {
    $expected = getPlanAmount($planType);
    return (int)$reportedCents === $expected;
}

// =====================================================================
// Idempotency
// =====================================================================

/**
 * Generate a unique idempotency key.
 * @return string
 */
function generateIdempotencyKey() {
    return bin2hex(random_bytes(32));
}

/**
 * Check if an idempotency key was already used recently (within 30 min).
 * @param PDO $db
 * @param string $key
 * @return bool True if key is already used (duplicate)
 */
function isIdempotencyKeyUsed($db, $key) {
    $stmt = $db->prepare("
        SELECT id FROM payment_sessions
        WHERE idempotency_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$key]);
    return (bool)$stmt->fetch();
}

// =====================================================================
// HMAC-signed Callback URLs
// =====================================================================

/**
 * Generate HMAC signature for a callback token.
 * @param string $token
 * @return string
 */
function generateCallbackHmac($token) {
    $secret = getenv('PAYMENT_HMAC_SECRET');
    if (!$secret || $secret === 'change_this_to_a_random_64_char_hex_string') {
        error_log('CRITICAL: PAYMENT_HMAC_SECRET is not configured');
        throw new RuntimeException('Payment HMAC secret not configured');
    }
    return hash_hmac('sha256', $token, $secret);
}

/**
 * Verify HMAC on callback URL.
 * @param string $token
 * @param string $hmac
 * @return bool
 */
function verifyCallbackHmac($token, $hmac) {
    $expected = generateCallbackHmac($token);
    return hash_equals($expected, $hmac);
}

// =====================================================================
// Webhook Audit Logging & Deduplication
// =====================================================================

/**
 * Log a webhook event to the audit table.
 * @param PDO $db
 * @param string $provider 'stripe' or 'paypal'
 * @param string $eventType
 * @param string $eventId
 * @param string|null $paymentToken
 * @param int|null $userId
 * @param string $payloadHash
 * @return bool
 */
function logWebhookEvent($db, $provider, $eventType, $eventId, $paymentToken = null, $userId = null, $payloadHash = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO webhook_events (provider, event_type, event_id, payload_hash, payment_token, user_id, processing_status, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, 'received', ?)
        ");
        $stmt->execute([$provider, $eventType, $eventId, $payloadHash, $paymentToken, $userId, $ip]);
        return true;
    } catch (\PDOException $e) {
        // SQLSTATE 23000 = integrity constraint violation (duplicate key)
        if ($e->getCode() === '23000') {
            return false;
        }
        error_log("Webhook log error: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Webhook log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a webhook event was already processed.
 * @param PDO $db
 * @param string $provider
 * @param string $eventId
 * @return bool True if duplicate
 */
function isDuplicateWebhookEvent($db, $provider, $eventId) {
    $stmt = $db->prepare("
        SELECT id FROM webhook_events
        WHERE provider = ? AND event_id = ? AND processing_status = 'processed'
        LIMIT 1
    ");
    $stmt->execute([$provider, $eventId]);
    return (bool)$stmt->fetch();
}

/**
 * Mark a webhook event as processed.
 * @param PDO $db
 * @param string $provider
 * @param string $eventId
 * @param string $status 'processed', 'failed', 'skipped'
 * @param string|null $errorMessage
 */
function updateWebhookEventStatus($db, $provider, $eventId, $status = 'processed', $errorMessage = null) {
    try {
        $stmt = $db->prepare("
            UPDATE webhook_events SET processing_status = ?, error_message = ?
            WHERE provider = ? AND event_id = ?
        ");
        $stmt->execute([$status, $errorMessage, $provider, $eventId]);
    } catch (Exception $e) {
        error_log("Webhook status update error: " . $e->getMessage());
    }
}

// =====================================================================
// User Suspension
// =====================================================================

/**
 * Suspend a user. Only suspends 'user' role — never touches higher roles.
 * @param PDO $db
 * @param int $userId
 * @param string $reason
 * @return bool
 */
function suspendUser($db, $userId, $reason = 'Payment failed') {
    try {
        $stmt = $db->prepare("
            UPDATE users
            SET is_suspended = TRUE, suspended_at = NOW(), suspension_reason = ?
            WHERE id = ? AND role = 'user'
        ");
        $stmt->execute([$reason, $userId]);

        // Also update subscription status
        $db->prepare("
            UPDATE subscriptions SET status = 'suspended', updated_at = NOW()
            WHERE user_id = ? AND status IN ('active','past_due')
        ")->execute([$userId]);

        $db->prepare("
            UPDATE user_premium SET subscription_status = 'expired'
            WHERE user_id = ?
        ")->execute([$userId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Suspend user failed for $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Unsuspend a user. Clears all suspension flags.
 * @param PDO $db
 * @param int $userId
 * @return bool
 */
function unsuspendUser($db, $userId) {
    try {
        $stmt = $db->prepare("
            UPDATE users
            SET is_suspended = FALSE, suspended_at = NULL, suspension_reason = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Unsuspend user failed for $userId: " . $e->getMessage());
        return false;
    }
}

function arePaymentsEnabled($db) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    
    if (!$db) {
        return true; // Fail open if no database connection
    }

    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'payments_enabled' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['setting_value'])) {
            $cache = ($row['setting_value'] === '1' || $row['setting_value'] === 'true');
            return $cache;
        }
    } catch (Exception $e) {
        // Log error but default to true (payments enabled) to avoid breaking site
        error_log("Payments enabled check failed: " . $e->getMessage());
    }
    
    $cache = true; // Default to true if not set or error
    return $cache;
}

/**
 * Check subscription status for a user (lazy check).
 * Returns: 'active', 'grace', 'suspended', 'lifetime', 'none'
 * @param PDO $db
 * @param int $userId
 * @return string
 */
function checkSubscriptionStatus($db, $userId) {
    try {
        $stmt = $db->prepare("SELECT plan_type, subscription_status, current_period_end FROM user_premium WHERE user_id = ? AND is_premium = TRUE");
        $stmt->execute([$userId]);
        $premium = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$premium) return 'none';

        if ($premium['plan_type'] === 'lifetime') return 'lifetime';
        
        // Temporary permanent access if payments are disabled
        if (!arePaymentsEnabled($db)) {
            return 'lifetime';
        }

        // Monthly/yearly plan checks
        $periodEnd = $premium['current_period_end'] ? strtotime($premium['current_period_end']) : null;
        $now = time();

        if (!$periodEnd) return 'active'; // No period end set = treat as active

        if ($periodEnd > $now) return 'active';

        $daysPast = ($now - $periodEnd) / 86400;
        if ($daysPast <= 3) return 'grace';

        return 'suspended';
    } catch (Exception $e) {
        error_log("Subscription check error for user $userId: " . $e->getMessage());
        return 'suspended'; // Fail closed — deny access on DB errors
    }
}

// =====================================================================
// Refund Processing
// =====================================================================

/**
 * Process a Stripe refund via API.
 * @param string $paymentIntentId Stripe payment intent or charge ID
 * @param int|null $amountCents Amount to refund (null = full)
 * @return array ['success' => bool, 'refund_id' => string|null, 'error' => string|null]
 */
function processStripeRefund($paymentIntentId, $amountCents = null) {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey) return ['success' => false, 'error' => 'Stripe not configured'];

    $fields = ['payment_intent' => $paymentIntentId];
    if ($amountCents !== null) {
        $fields['amount'] = $amountCents;
    }

    $ch = curl_init('https://api.stripe.com/v1/refunds');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2024-12-18.acacia',
        ],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return ['success' => true, 'refund_id' => $data['id'] ?? null, 'error' => null];
    }

    $errData = json_decode($response, true);
    $errMsg = $errData['error']['message'] ?? "HTTP $httpCode";
    error_log("Stripe refund failed: $errMsg");
    return ['success' => false, 'refund_id' => null, 'error' => $errMsg];
}

// =====================================================================
// Subscription Cancellation via Provider APIs
// =====================================================================

/**
 * Cancel a Stripe subscription.
 * @param string $subscriptionId
 * @return bool
 */
function cancelStripeSubscription($subscriptionId) {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey) return false;

    $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . urlencode($subscriptionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Stripe-Version: 2024-12-18.acacia',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

// =====================================================================
// Payment Nonces (single-use tokens for sensitive actions)
// =====================================================================

/**
 * Generate a single-use nonce for a payment action.
 * @param PDO $db
 * @param string $action e.g. 'refund_request', 'cancel_subscription'
 * @param int $userId
 * @return string The nonce token
 */
function generatePaymentNonce($db, $action, $userId) {
    $nonce = bin2hex(random_bytes(32));
    $stmt = $db->prepare("
        INSERT INTO payment_nonces (nonce, action, user_id, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ");
    $stmt->execute([$nonce, $action, $userId]);
    return $nonce;
}

/**
 * Validate and consume a payment nonce.
 * @param PDO $db
 * @param string $action
 * @param string $nonce
 * @param int $userId
 * @return bool
 */
function validatePaymentNonce($db, $action, $nonce, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM payment_nonces
            WHERE nonce = ? AND action = ? AND user_id = ? AND used = FALSE AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$nonce, $action, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        // Consume the nonce
        $db->prepare("UPDATE payment_nonces SET used = TRUE WHERE id = ?")->execute([$row['id']]);
        return true;
    } catch (Exception $e) {
        error_log("Nonce validation error: " . $e->getMessage());
        return false;
    }
}

// =====================================================================
// Downgrade user to community after refund
// =====================================================================

/**
 * Downgrade a user back to community role after refund.
 * @param PDO $db
 * @param int $userId
 * @return bool
 */
function downgradeUserToCommunity($db, $userId) {
    try {
        $db->beginTransaction();

        // Only downgrade 'user' role (not higher roles)
        $db->prepare("UPDATE users SET role = 'community' WHERE id = ? AND role = 'user'")->execute([$userId]);

        // Update premium record
        $db->prepare("UPDATE user_premium SET is_premium = FALSE, subscription_status = 'cancelled' WHERE user_id = ?")->execute([$userId]);

        // Cancel any active subscriptions
        $db->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(), ended_at = NOW() WHERE user_id = ? AND status IN ('active','past_due','suspended')")->execute([$userId]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Downgrade failed for user $userId: " . $e->getMessage());
        return false;
    }
}

// =====================================================================
// Ensure Payment Tables (updated with all new tables)
// =====================================================================

/**
 * Ensure all payment tables exist (auto-migration on first use).
 * @param PDO $db
 */
function ensurePaymentTables($db) {
    $statements = [
        // Original tables
        "CREATE TABLE IF NOT EXISTS payment_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) UNIQUE NOT NULL,
            provider ENUM('stripe', 'paypal') NOT NULL,
            provider_session_id VARCHAR(255) NULL,
            amount_cents INT NOT NULL DEFAULT 200,
            status ENUM('pending', 'paid', 'failed', 'expired', 'refunded') DEFAULT 'pending',
            user_id INT NULL,
            used BOOLEAN DEFAULT FALSE,
            plan_type ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'lifetime',
            idempotency_key VARCHAR(64) NULL,
            ip_address VARCHAR(45) NULL,
            callback_hmac VARCHAR(128) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_token (token),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS user_premium (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            is_premium BOOLEAN DEFAULT TRUE,
            premium_since TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_session_id INT NULL,
            plan_type ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'lifetime',
            stripe_subscription_id VARCHAR(255) NULL,
            paypal_subscription_id VARCHAR(255) NULL,
            subscription_status ENUM('active','past_due','cancelled','expired') NULL,
            current_period_end TIMESTAMP NULL,
            provider ENUM('stripe','paypal','admin_manual') NULL,
            last_payment_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // New tables
        "CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_type ENUM('monthly','yearly','lifetime') NOT NULL,
            provider ENUM('stripe','paypal','admin_manual') NOT NULL,
            provider_subscription_id VARCHAR(255) NULL,
            provider_customer_id VARCHAR(255) NULL,
            status ENUM('active','past_due','cancelled','expired','suspended') NOT NULL DEFAULT 'active',
            amount_cents INT NOT NULL DEFAULT 200,
            current_period_start TIMESTAMP NULL,
            current_period_end TIMESTAMP NULL,
            cancel_at_period_end BOOLEAN DEFAULT FALSE,
            cancelled_at TIMESTAMP NULL,
            ended_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subscriptions_user_id (user_id),
            INDEX idx_subscriptions_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS webhook_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider ENUM('stripe','paypal') NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            event_id VARCHAR(255) NOT NULL,
            payload_hash VARCHAR(64) NULL,
            payment_token VARCHAR(64) NULL,
            user_id INT NULL,
            processing_status ENUM('received','processed','failed','skipped') DEFAULT 'received',
            error_message TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_webhook_provider_event (provider, event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS refund_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subscription_id INT NULL,
            payment_session_id INT NULL,
            provider ENUM('stripe','paypal') NULL,
            provider_payment_id VARCHAR(255) NULL,
            provider_refund_id VARCHAR(255) NULL,
            amount_cents INT NULL,
            reason VARCHAR(100) NOT NULL,
            feedback TEXT NULL,
            status ENUM('pending','approved','processed','failed','denied') DEFAULT 'pending',
            requested_by ENUM('user','admin') DEFAULT 'user',
            processed_by INT NULL,
            processed_at TIMESTAMP NULL,
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS rate_limit_ip (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            request_count INT NOT NULL DEFAULT 1,
            window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rate_limit_ip_endpoint (ip_address, endpoint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS admin_payment_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT NOT NULL,
            target_user_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payment_nonces (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nonce VARCHAR(64) NOT NULL UNIQUE,
            action VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // v7.0: Products catalog
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(50) NOT NULL UNIQUE,
            description TEXT NULL,
            price_cents INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'usd',
            type ENUM('subscription','one-time','donation') NOT NULL DEFAULT 'subscription',
            plan_type ENUM('monthly','yearly','lifetime','donation') NULL,
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_type (type),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // v7.0: Donations tracking
        "CREATE TABLE IF NOT EXISTS donations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_name VARCHAR(100) NULL,
            amount_cents INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'usd',
            feedback TEXT NULL,
            payment_intent_id VARCHAR(255) NULL,
            payment_session_id INT NULL,
            status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    // ALTERs for existing tables (wrapped individually)
    $alters = [
        "ALTER TABLE users ADD COLUMN is_suspended BOOLEAN DEFAULT FALSE",
        "ALTER TABLE users ADD COLUMN suspended_at TIMESTAMP NULL",
        "ALTER TABLE users ADD COLUMN suspension_reason VARCHAR(255) NULL",
        "ALTER TABLE payment_sessions ADD COLUMN plan_type ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'lifetime'",
        "ALTER TABLE payment_sessions ADD COLUMN idempotency_key VARCHAR(64) NULL",
        "ALTER TABLE payment_sessions ADD COLUMN ip_address VARCHAR(45) NULL",
        "ALTER TABLE payment_sessions ADD COLUMN callback_hmac VARCHAR(128) NULL",
        "ALTER TABLE payment_sessions ADD COLUMN product_id INT NULL",
        "ALTER TABLE payment_sessions ADD COLUMN is_donation BOOLEAN DEFAULT FALSE",
        "ALTER TABLE payment_sessions ADD COLUMN donation_feedback TEXT NULL",
        "ALTER TABLE payment_sessions ADD COLUMN payment_intent_id VARCHAR(255) NULL",
        "ALTER TABLE user_premium ADD COLUMN plan_type ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'lifetime'",
        "ALTER TABLE user_premium ADD COLUMN stripe_subscription_id VARCHAR(255) NULL",
        "ALTER TABLE user_premium ADD COLUMN paypal_subscription_id VARCHAR(255) NULL",
        "ALTER TABLE user_premium ADD COLUMN subscription_status ENUM('active','past_due','cancelled','expired') NULL",
        "ALTER TABLE user_premium ADD COLUMN current_period_end TIMESTAMP NULL",
        "ALTER TABLE user_premium ADD COLUMN provider ENUM('stripe','paypal','admin_manual') NULL",
        "ALTER TABLE user_premium ADD COLUMN last_payment_at TIMESTAMP NULL",
    ];

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            error_log("Payment table creation: " . $e->getMessage());
        }
    }

    foreach ($alters as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Column likely already exists — safe to ignore
        }
    }
}

// =====================================================================
// v7.0: Payment Fraud Detection
// =====================================================================

/**
 * Check for payment fraud indicators.
 * Returns array with 'allowed' bool and 'reasons' array.
 */
function checkPaymentFraud(PDO $db, int $userId, string $ip): array {
    $reasons = [];
    $riskScore = 0.0;

    // 1. Velocity check: >3 payment attempts in 10 minutes
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM payment_sessions WHERE (user_id = ? OR ip_address = ?) AND created_at > NOW() - INTERVAL 10 MINUTE");
        $stmt->execute([$userId, $ip]);
        $recentAttempts = (int)$stmt->fetchColumn();
        if ($recentAttempts > 3) {
            $reasons[] = "High velocity: {$recentAttempts} attempts in 10 minutes";
            $riskScore += 0.4;
        }
    } catch (Exception $e) {
        error_log("Fraud check velocity error for user $userId: " . $e->getMessage());
    }

    // 2. IP check: same IP used by >2 different users for payments
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM payment_sessions WHERE ip_address = ? AND user_id IS NOT NULL");
        $stmt->execute([$ip]);
        $ipUsers = (int)$stmt->fetchColumn();
        if ($ipUsers > 2) {
            $reasons[] = "Shared IP: {$ipUsers} different users from same IP";
            $riskScore += 0.3;
        }
    } catch (Exception $e) {
        error_log("Fraud check IP error for user $userId: " . $e->getMessage());
    }

    // 3. Fingerprint check: suspicious device linking
    try {
        $stmt = $db->prepare("SELECT dl.confidence_score, dl.linked_user_ids FROM device_links dl
            INNER JOIN device_fingerprints df ON df.fingerprint_hash = dl.fingerprint_hash
            WHERE df.user_id = ? AND dl.confidence_score > 0.5
            ORDER BY dl.confidence_score DESC LIMIT 1");
        $stmt->execute([$userId]);
        $deviceLink = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($deviceLink && floatval($deviceLink['confidence_score']) > 0.7) {
            $linkedCount = count(json_decode($deviceLink['linked_user_ids'], true) ?: []);
            $reasons[] = "Device linked to {$linkedCount} accounts (score: {$deviceLink['confidence_score']})";
            $riskScore += floatval($deviceLink['confidence_score']) * 0.3;
        }
    } catch (Exception $e) {
        error_log("Fraud check fingerprint error for user $userId: " . $e->getMessage());
    }

    // Log to payment_fraud_log if any risks detected
    if (!empty($reasons)) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS payment_fraud_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                ip_address VARCHAR(45),
                action VARCHAR(50),
                details TEXT,
                risk_score DECIMAL(5,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_ip (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $db->prepare("INSERT INTO payment_fraud_log (user_id, ip_address, action, details, risk_score) VALUES (?, ?, 'checkout_attempt', ?, ?)");
            $stmt->execute([$userId, $ip, implode('; ', $reasons), min($riskScore, 1.0)]);
        } catch (Exception $e) {
            error_log("Fraud log error: " . $e->getMessage());
        }
    }

    return [
        'allowed' => $riskScore < 0.8,
        'risk_score' => min($riskScore, 1.0),
        'reasons' => $reasons
    ];
}

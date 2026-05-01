<?php
/**
 * Stripe Webhook Endpoint - Spencer's Website v7.0
 * Handles 7 event types: checkout.session.completed, payment_intent.succeeded,
 * invoice.payment_succeeded, invoice.payment_failed, customer.subscription.updated,
 * customer.subscription.deleted, charge.refunded.
 *
 * v7.0: Added payment_intent.succeeded for Stripe Elements / Payment Intents flow.
 *       Handles product purchases and donations via dynamic amounts.
 *
 * Features: HMAC signature verification, replay protection, audit logging,
 * duplicate detection, amount verification, prepared statements throughout.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/subscription.php';
require_once __DIR__ . '/../config/database.php';

// Security headers for webhooks
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Always respond 200 with JSON to prevent Stripe retries
function respondAndExit($extra = []) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['received' => true], $extra));
    exit;
}

// =====================================================================
// 1. Connect to database first for rate limiting
// =====================================================================

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Stripe webhook DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// =====================================================================
// 2. Security: Rate limiting and IP whitelist
// =====================================================================

// Rate limit webhook endpoints (100 requests per minute per IP)
require_once __DIR__ . '/../includes/rate_limit_ip.php';
if (!checkIpRateLimit($db, 'webhook', 100, 60)) {
    http_response_code(429);
    error_log("Stripe webhook: rate limit exceeded from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// Optional: IP whitelist for Stripe webhooks
$allowedIps = getenv('STRIPE_WEBHOOK_IPS') ? explode(',', getenv('STRIPE_WEBHOOK_IPS')) : [];
if (!empty($allowedIps)) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($clientIp, $allowedIps)) {
        http_response_code(403);
        error_log("Stripe webhook: unauthorized IP attempt from: $clientIp");
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// =====================================================================
// 3. Read raw payload and verify Stripe HMAC signature
// =====================================================================

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');

if (!$payload || !$sigHeader || !$webhookSecret) {
    http_response_code(400);
    $missing = [];
    if (!$payload) $missing[] = 'payload';
    if (!$sigHeader) $missing[] = 'signature header';
    if (!$webhookSecret) $missing[] = 'STRIPE_WEBHOOK_SECRET environment variable';
    error_log("Stripe webhook: missing " . implode(', ', $missing));
    echo json_encode([
        'error' => 'Webhook verification failed'
    ]);
    exit;
}

// Parse the Stripe-Signature header (t=...,v1=...)
$sigParts = [];
foreach (explode(',', $sigHeader) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) === 2) {
        $sigParts[$kv[0]] = $kv[1];
    }
}

$timestamp = $sigParts['t'] ?? '';
$signature = $sigParts['v1'] ?? '';

if (!$timestamp || !$signature) {
    http_response_code(400);
    error_log("Stripe webhook: invalid signature format");
    exit;
}

// Reject if timestamp is more than 5 minutes old (replay protection)
if (abs(time() - intval($timestamp)) > 300) {
    http_response_code(400);
    error_log("Stripe webhook: timestamp too old");
    exit;
}

// Compute expected signature
$signedPayload = $timestamp . '.' . $payload;
$expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);

if (!hash_equals($expectedSig, $signature)) {
    http_response_code(400);
    error_log("Stripe webhook: signature mismatch");
    exit;
}

// =====================================================================
// 2. Parse event JSON
// =====================================================================

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    error_log("Stripe webhook: invalid JSON");
    exit;
}

$eventType = $event['type'] ?? '';
$eventId = $event['id'] ?? '';

if (!$eventType || !$eventId) {
    http_response_code(400);
    error_log("Stripe webhook: missing event type or id");
    exit;
}

error_log("Stripe webhook received: $eventType ($eventId)");

// =====================================================================
// 4. Idempotency Guard — prevent duplicate event processing
// =====================================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS processed_webhook_events (
        event_id VARCHAR(255) PRIMARY KEY,
        event_type VARCHAR(100) NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_processed (processed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $idempCheck = $db->prepare("SELECT event_id FROM processed_webhook_events WHERE event_id = ?");
    $idempCheck->execute([$eventId]);
    if ($idempCheck->fetch()) {
        error_log("Stripe webhook: duplicate event {$eventId} — already processed, skipping");
        respondAndExit(['duplicate' => true]);
    }

    // Mark as processing immediately (before handling)
    $idempInsert = $db->prepare("INSERT INTO processed_webhook_events (event_id, event_type) VALUES (?, ?)");
    $idempInsert->execute([$eventId, $eventType]);

    // Cleanup: remove events older than 7 days to prevent table bloat
    $db->exec("DELETE FROM processed_webhook_events WHERE processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
} catch (Exception $e) {
    error_log("Stripe webhook idempotency check error: " . $e->getMessage());
    // Continue processing — better to risk a duplicate than reject a valid payment
}

// =====================================================================
// 5. Handle event types
// =====================================================================

$payloadHash = hash('sha256', $payload);
logWebhookEvent($db, 'stripe', $eventType, $eventId, null, null, $payloadHash);

if (isDuplicateWebhookEvent($db, 'stripe', $eventId)) {
    error_log("Stripe webhook: duplicate event $eventId, skipping");
    respondAndExit(['info' => 'duplicate_event']);
}

// =====================================================================
// 5. Handle event types
// =====================================================================

try {
    switch ($eventType) {

        // -----------------------------------------------------------------
        // checkout.session.completed
        // -----------------------------------------------------------------
        case 'checkout.session.completed':
            $session = $event['data']['object'] ?? [];
            $paymentToken = $session['metadata']['payment_token'] ?? '';
            $planType = $session['metadata']['plan_type'] ?? 'lifetime';
            $stripeSessionId = $session['id'] ?? '';
            $subscriptionId = $session['subscription'] ?? null;

            if (!$paymentToken) {
                error_log("Stripe webhook: missing payment_token in metadata");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Missing payment_token');
                respondAndExit(['warning' => 'missing_payment_token']);
            }

            // Look up payment session by token
            $paymentSession = getPaymentSession($db, $paymentToken);
            if (!$paymentSession) {
                error_log("Stripe webhook: payment token not found: $paymentToken");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Token not found');
                respondAndExit(['warning' => 'token_not_found']);
            }

            // Amount verification
            $amountTotal = $session['amount_total'] ?? null;
            if ($amountTotal !== null && !verifyPaymentAmount($amountTotal, $planType)) {
                error_log("Stripe webhook: amount mismatch. Expected " . getPlanAmount($planType) . " got $amountTotal for plan $planType");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Amount mismatch');
                respondAndExit(['warning' => 'amount_mismatch']);
            }

            // If subscription mode, store subscription ID in payment session
            if ($subscriptionId) {
                $stmt = $db->prepare("UPDATE payment_sessions SET provider_session_id = ? WHERE token = ?");
                $stmt->execute([$stripeSessionId, $paymentToken]);
            }

            // Update payment status to 'paid'
            updatePaymentStatus($db, $paymentToken, 'paid', $stripeSessionId);

            // If user_id exists, upgrade immediately
            if ($paymentSession['user_id']) {
                $userId = (int)$paymentSession['user_id'];
                $upgraded = upgradeUserRole($db, $userId, $paymentSession['id'], $planType, 'stripe', $subscriptionId);
                if ($upgraded) {
                    markPaymentUsed($db, $paymentToken);
                    error_log("Stripe webhook: auto-upgraded user ID $userId (plan: $planType)");
                } else {
                    error_log("Stripe webhook: upgrade failed for user ID $userId");
                }
            }

            // Mark token as used
            markPaymentUsed($db, $paymentToken);
            break;

        // -----------------------------------------------------------------
        // payment_intent.succeeded (v7.0 — Stripe Elements / Payment Intents)
        // -----------------------------------------------------------------
        case 'payment_intent.succeeded':
            $intent = $event['data']['object'] ?? [];
            $intentId = $intent['id'] ?? '';
            $intentMetadata = $intent['metadata'] ?? [];
            $paymentToken = $intentMetadata['payment_token'] ?? '';
            $planType = $intentMetadata['plan_type'] ?? '';
            $isDonation = ($intentMetadata['is_donation'] ?? '0') === '1';
            $productId = $intentMetadata['product_id'] ?? '';
            $metaUserId = $intentMetadata['user_id'] ?? '';

            if (!$paymentToken) {
                error_log("Stripe webhook: payment_intent.succeeded missing payment_token in metadata");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Missing payment_token');
                respondAndExit(['warning' => 'missing_payment_token']);
            }

            // Look up payment session by token
            $paymentSession = getPaymentSession($db, $paymentToken);
            if (!$paymentSession) {
                error_log("Stripe webhook: payment_intent token not found: $paymentToken");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Token not found');
                respondAndExit(['warning' => 'token_not_found']);
            }

            // Update payment status to 'paid'
            updatePaymentStatus($db, $paymentToken, 'paid', $intentId);

            if ($isDonation) {
                // --- Donation flow ---
                $stmt = $db->prepare("UPDATE donations SET status = 'completed' WHERE payment_intent_id = ?");
                $stmt->execute([$intentId]);
                markPaymentUsed($db, $paymentToken);
                error_log("Stripe webhook: donation completed via payment_intent $intentId");
            } else {
                // --- Product purchase flow ---
                $amountReceived = $intent['amount_received'] ?? $intent['amount'] ?? 0;

                // Amount verification against plan type
                if ($planType && $planType !== 'donation') {
                    $expectedAmount = getPlanAmount($planType);
                    if ((int)$amountReceived !== $expectedAmount) {
                        error_log("Stripe webhook: PI amount mismatch. Expected $expectedAmount got $amountReceived for plan $planType");
                        updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Amount mismatch');
                        respondAndExit(['warning' => 'amount_mismatch']);
                    }
                }

                // If user_id exists in payment session, upgrade immediately
                $userId = $paymentSession['user_id'] ? (int)$paymentSession['user_id'] : null;
                if ($userId && $planType && $planType !== 'donation') {
                    $upgraded = upgradeUserRole($db, $userId, $paymentSession['id'], $planType, 'stripe', null);
                    if ($upgraded) {
                        error_log("Stripe webhook: auto-upgraded user ID $userId via PI (plan: $planType)");
                    } else {
                        error_log("Stripe webhook: PI upgrade failed for user ID $userId");
                    }
                }

                markPaymentUsed($db, $paymentToken);
                error_log("Stripe webhook: payment_intent.succeeded processed for $intentId (plan: $planType)");
            }
            break;

        // -----------------------------------------------------------------
        // invoice.payment_succeeded
        // -----------------------------------------------------------------
        case 'invoice.payment_succeeded':
            $invoice = $event['data']['object'] ?? [];
            $providerSubId = $invoice['subscription'] ?? '';

            if (!$providerSubId) {
                error_log("Stripe webhook: invoice.payment_succeeded missing subscription ID");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', 'No subscription ID');
                respondAndExit();
            }

            $subscription = getSubscriptionByProviderId($db, $providerSubId);
            if (!$subscription) {
                error_log("Stripe webhook: subscription not found for provider ID $providerSubId");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Subscription not found');
                respondAndExit();
            }

            // Extract period end from invoice line items
            $periodEndUnix = $invoice['lines']['data'][0]['period']['end'] ?? null;
            if ($periodEndUnix) {
                $periodEnd = date('Y-m-d H:i:s', $periodEndUnix);

                // Update subscription period end
                updateSubscriptionPeriod($db, $subscription['id'], $periodEnd);

                // Update user_premium current_period_end
                $stmt = $db->prepare("UPDATE user_premium SET current_period_end = ?, subscription_status = 'active' WHERE user_id = ?");
                $stmt->execute([$periodEnd, $subscription['user_id']]);
            }

            // Clear any suspension on the user
            unsuspendUser($db, $subscription['user_id']);

            error_log("Stripe webhook: invoice payment succeeded for subscription $providerSubId, user {$subscription['user_id']}");
            break;

        // -----------------------------------------------------------------
        // invoice.payment_failed
        // -----------------------------------------------------------------
        case 'invoice.payment_failed':
            $invoice = $event['data']['object'] ?? [];
            $providerSubId = $invoice['subscription'] ?? '';

            if (!$providerSubId) {
                error_log("Stripe webhook: invoice.payment_failed missing subscription ID");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', 'No subscription ID');
                respondAndExit();
            }

            $subscription = getSubscriptionByProviderId($db, $providerSubId);
            if (!$subscription) {
                error_log("Stripe webhook: subscription not found for provider ID $providerSubId");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Subscription not found');
                respondAndExit();
            }

            // Update subscription status to past_due
            updateSubscriptionByProviderId($db, $providerSubId, 'past_due');

            // Update user_premium subscription_status to past_due
            $stmt = $db->prepare("UPDATE user_premium SET subscription_status = 'past_due' WHERE user_id = ?");
            $stmt->execute([$subscription['user_id']]);

            error_log("Stripe webhook: invoice payment failed for subscription $providerSubId, user {$subscription['user_id']}");
            break;

        // -----------------------------------------------------------------
        // customer.subscription.updated
        // -----------------------------------------------------------------
        case 'customer.subscription.updated':
            $subObject = $event['data']['object'] ?? [];
            $providerSubId = $subObject['id'] ?? '';
            $stripeStatus = $subObject['status'] ?? '';

            if (!$providerSubId) {
                error_log("Stripe webhook: subscription.updated missing subscription ID");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', 'No subscription ID');
                respondAndExit();
            }

            $subscription = getSubscriptionByProviderId($db, $providerSubId);
            if (!$subscription) {
                error_log("Stripe webhook: subscription not found for provider ID $providerSubId");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Subscription not found');
                respondAndExit();
            }

            // Map Stripe status to internal status
            $statusMap = [
                'active'   => 'active',
                'past_due' => 'past_due',
                'canceled' => 'cancelled',
                'unpaid'   => 'expired',
            ];
            $mappedStatus = $statusMap[$stripeStatus] ?? $stripeStatus;

            // Extract period end
            $periodEndUnix = $subObject['current_period_end'] ?? null;
            $periodEnd = $periodEndUnix ? date('Y-m-d H:i:s', $periodEndUnix) : null;

            // Extract cancel_at_period_end flag
            $cancelAtPeriodEnd = !empty($subObject['cancel_at_period_end']) ? 1 : 0;

            // Update subscription record
            updateSubscriptionByProviderId($db, $providerSubId, $mappedStatus, $periodEnd);

            // Update cancel_at_period_end flag separately
            $stmt = $db->prepare("UPDATE subscriptions SET cancel_at_period_end = ? WHERE provider_subscription_id = ?");
            $stmt->execute([$cancelAtPeriodEnd, $providerSubId]);

            // Sync user_premium status
            $stmt = $db->prepare("UPDATE user_premium SET subscription_status = ?, current_period_end = COALESCE(?, current_period_end) WHERE user_id = ?");
            $stmt->execute([$mappedStatus, $periodEnd, $subscription['user_id']]);

            error_log("Stripe webhook: subscription updated $providerSubId -> status=$mappedStatus, cancel_at_period_end=$cancelAtPeriodEnd");
            break;

        // -----------------------------------------------------------------
        // customer.subscription.deleted
        // -----------------------------------------------------------------
        case 'customer.subscription.deleted':
            $subObject = $event['data']['object'] ?? [];
            $providerSubId = $subObject['id'] ?? '';

            if (!$providerSubId) {
                error_log("Stripe webhook: subscription.deleted missing subscription ID");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', 'No subscription ID');
                respondAndExit();
            }

            $subscription = getSubscriptionByProviderId($db, $providerSubId);
            if (!$subscription) {
                error_log("Stripe webhook: subscription not found for provider ID $providerSubId");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Subscription not found');
                respondAndExit();
            }

            // Mark subscription as cancelled with ended_at
            $stmt = $db->prepare("
                UPDATE subscriptions
                SET status = 'cancelled', cancelled_at = NOW(), ended_at = NOW(), updated_at = NOW()
                WHERE provider_subscription_id = ?
            ");
            $stmt->execute([$providerSubId]);

            // Update user_premium
            $stmt = $db->prepare("UPDATE user_premium SET subscription_status = 'cancelled' WHERE user_id = ?");
            $stmt->execute([$subscription['user_id']]);

            // Suspend the user
            suspendUser($db, $subscription['user_id'], 'Subscription cancelled');

            error_log("Stripe webhook: subscription deleted $providerSubId, user {$subscription['user_id']} suspended");
            break;

        // -----------------------------------------------------------------
        // charge.refunded
        // -----------------------------------------------------------------
        case 'charge.refunded':
            $charge = $event['data']['object'] ?? [];
            $paymentIntent = $charge['payment_intent'] ?? '';

            if (!$paymentIntent) {
                error_log("Stripe webhook: charge.refunded missing payment_intent");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', 'No payment_intent');
                respondAndExit();
            }

            // Look up the payment session by provider_session_id
            // The checkout session is linked via its payment_intent; we search
            // payment_sessions where provider_session_id matches a related checkout session.
            // Stripe checkout sessions store the session ID as provider_session_id,
            // but we can also search by payment intent if stored.
            $stmt = $db->prepare("
                SELECT id, user_id, provider_session_id, token, status FROM payment_sessions
                WHERE provider_session_id = ? AND status = 'paid'
                LIMIT 1
            ");
            $stmt->execute([$paymentIntent]);
            $paymentSession = $stmt->fetch(PDO::FETCH_ASSOC);

            // If not found by payment_intent, log and skip — never guess/fallback
            if (!$paymentSession) {
                error_log("Stripe webhook: charge.refunded - no payment session found for payment_intent $paymentIntent. Skipping to avoid matching wrong user.");
                updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', 'Payment session not found for payment_intent');
                respondAndExit();
            }

            $userId = $paymentSession['user_id'] ? (int)$paymentSession['user_id'] : null;

            // Update refund_requests status to 'processed' if one exists for this payment session
            if ($paymentSession['id']) {
                $stmt = $db->prepare("
                    UPDATE refund_requests
                    SET status = 'processed', processed_at = NOW()
                    WHERE payment_session_id = ? AND status IN ('pending', 'approved')
                ");
                $stmt->execute([$paymentSession['id']]);
            }

            // Downgrade the user to community role
            if ($userId) {
                downgradeUserToCommunity($db, $userId);
                error_log("Stripe webhook: charge.refunded - user $userId downgraded to community");
            }

            // Mark payment session as 'refunded'
            $stmt = $db->prepare("UPDATE payment_sessions SET status = 'refunded' WHERE id = ?");
            $stmt->execute([$paymentSession['id']]);

            error_log("Stripe webhook: charge.refunded processed for payment_intent $paymentIntent");
            break;

        // -----------------------------------------------------------------
        // Unhandled event types
        // -----------------------------------------------------------------
        default:
            error_log("Stripe webhook: unhandled event type $eventType");
            updateWebhookEventStatus($db, 'stripe', $eventId, 'skipped', "Unhandled event type: $eventType");
            respondAndExit();
    }

    // Mark the webhook event as successfully processed
    updateWebhookEventStatus($db, 'stripe', $eventId, 'processed');

} catch (Exception $e) {
    error_log("Stripe webhook processing error for $eventType ($eventId): " . $e->getMessage());
    updateWebhookEventStatus($db, 'stripe', $eventId, 'failed', $e->getMessage());
}

// Always return 200 to prevent Stripe retries
respondAndExit();

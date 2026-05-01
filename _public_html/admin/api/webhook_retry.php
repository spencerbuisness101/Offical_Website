<?php
/**
 * Webhook Retry API — Spencer's Website v7.0
 * Re-processes a failed Stripe webhook event directly (no HTTP call)
 */
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF mismatch']); exit;
}

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'No ID']); exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, event_type, type, stripe_event_id, event_id, status, payload FROM stripe_webhook_events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) { echo json_encode(['success' => false, 'message' => 'Event not found']); exit; }

    // Mark as retrying
    $stmt = $db->prepare("UPDATE stripe_webhook_events SET status = 'retrying' WHERE id = ?");
    $stmt->execute([$id]);

    // Try to re-process the event directly via the Stripe webhook processor
    $eventType = $event['event_type'] ?? $event['type'] ?? '';
    $eventId = $event['stripe_event_id'] ?? $event['event_id'] ?? '';
    $processed = false;
    $processMessage = '';

    // If we have a Stripe event ID, try to re-process via the webhook handler
    if ($eventId && file_exists(__DIR__ . '/../../api/webhook_stripe.php')) {
        // Use Stripe API to fetch the event fresh and re-process
        try {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $envVars = [];
                foreach ($envLines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) $envVars[trim($parts[0])] = trim($parts[1]);
                }
                $stripeSecret = $envVars['STRIPE_SECRET_KEY'] ?? '';
                if ($stripeSecret) {
                    \Stripe\Stripe::setApiKey($stripeSecret);
                    $stripeEvent = \Stripe\Event::retrieve($eventId);

                    // Re-include the webhook processor
                    ob_start();
                    // Set up the event for the webhook handler
                    $_POST['_stripe_event'] = $stripeEvent;
                    include __DIR__ . '/../../api/webhook_stripe.php';
                    $output = ob_get_clean();

                    $processed = true;
                    $processMessage = 'Re-processed via Stripe API';
                }
            }
        } catch (Exception $e) {
            $processMessage = 'Stripe API error: ' . $e->getMessage();
        }
    }

    if (!$processed) {
        // Fallback: mark as needing manual review
        $processMessage = $processMessage ?: 'Could not auto-reprocess. Event marked for manual review.';
    }

    $newStatus = $processed ? 'processed' : 'failed';
    $stmt = $db->prepare("UPDATE stripe_webhook_events SET status = ?, processed_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    // Audit
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, 'webhook_retry', ?, ?)");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '', "Retried webhook $id: $newStatus - $processMessage", $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}

    echo json_encode(['success' => $processed, 'message' => $processMessage]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

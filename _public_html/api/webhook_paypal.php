<?php
/**
 * PayPal Webhook Endpoint - Spencer's Website v7.0
 * PayPal is no longer used as a direct payment provider.
 * All payments now flow through Stripe (which handles PayPal as a payment method).
 * This stub returns 200 OK to prevent errors from any stale PayPal webhook events.
 */
http_response_code(200);
echo json_encode(['received' => true, 'status' => 'deprecated']);
exit;

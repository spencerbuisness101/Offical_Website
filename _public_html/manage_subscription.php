<?php
/**
 * Subscription Management Page - Spencer's Website v7.0
 * Shows plan details, payment history, cancel/refund options.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/subscription.php';
require_once __DIR__ . '/config/database.php';

// Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Must be user role or higher (not community)
$userRole = $_SESSION['role'] ?? 'community';
if ($userRole === 'community') {
    header('Location: main.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

try {
    $database = new Database();
    $db = $database->getConnection();
    ensurePaymentTables($db);
} catch (Exception $e) {
    die('Database error.');
}

// Fetch subscription data
$premium = getUserPremium($db, $userId);
$activeSub = getActiveSubscription($db, $userId);
$latestSub = getLatestSubscription($db, $userId);
$paymentHistory = getSubscriptionHistory($db, $userId);

$planType = $premium['plan_type'] ?? 'lifetime';
$provider = $premium['provider'] ?? 'unknown';
$subStatus = $premium['subscription_status'] ?? 'active';
$memberSince = $premium['premium_since'] ?? null;
$nextBilling = $premium['current_period_end'] ?? null;

// Generate nonces for sensitive actions
$cancelNonce = generatePaymentNonce($db, 'cancel_subscription', $userId);
$refundNonce = generatePaymentNonce($db, 'refund_request', $userId);

// Handle cancel subscription POST
$cancelMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_subscription') {
    if (validateCsrfToken($_POST['csrf_token'] ?? '') && validatePaymentNonce($db, 'cancel_subscription', $_POST['nonce'] ?? '', $userId)) {
        $cancelled = false;
        if ($activeSub) {
            $provSubId = $activeSub['provider_subscription_id'] ?? '';
            if ($activeSub['provider'] === 'stripe' && $provSubId) {
                $cancelled = cancelStripeSubscription($provSubId);
            }
        }
        cancelSubscription($db, $userId, 'User cancelled via manage page');
        $cancelMessage = 'Your subscription has been cancelled. You will retain access until the end of your current billing period.';
        // Refresh data
        $premium = getUserPremium($db, $userId);
        $activeSub = getActiveSubscription($db, $userId);
        $subStatus = $premium['subscription_status'] ?? 'cancelled';
        $cancelNonce = generatePaymentNonce($db, 'cancel_subscription', $userId);
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <style>
        .manage-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .card {
            background: rgba(0,0,0,0.7);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .card h2 {
            color: #4ECDC4;
            margin: 0 0 15px;
            font-size: 1.3em;
            font-weight: 700;
        }
        .plan-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: white;
        }
        .plan-badge.lifetime { background: #10b981; }
        .plan-badge.monthly { background: #3b82f6; }
        .plan-badge.cancelled { background: #ef4444; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #e2e8f0;
            font-size: 14px;
        }
        .info-row .label { color: #94a3b8; }
        .info-row .value { font-weight: 600; }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .history-table th {
            color: #94a3b8;
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-weight: 600;
        }
        .history-table td {
            color: #e2e8f0;
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .btn-danger {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239,68,68,0.3); }
        .btn-warning {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
        .btn-back {
            color: #4ECDC4;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-back:hover { color: white; }
        .success-msg {
            background: rgba(16,185,129,0.2);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        /* Refund Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: rgba(30,41,59,0.98);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
        }
        .modal-box h3 { color: #f59e0b; margin: 0 0 15px; font-size: 1.2em; }
        .modal-box select, .modal-box textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(0,0,0,0.3);
            color: #e2e8f0;
            font-size: 14px;
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        .modal-box textarea { min-height: 80px; resize: vertical; }
        .modal-box label { display: block; color: #94a3b8; font-size: 13px; margin-bottom: 5px; font-weight: 600; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; }
        .btn-modal-cancel {
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            padding: 8px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="manage-container">
        <div style="margin-bottom: 20px;">
            <a href="main.php" class="btn-back">&larr; Back to Dashboard</a>
        </div>

        <?php if ($cancelMessage): ?>
        <div class="success-msg"><?php echo htmlspecialchars($cancelMessage); ?></div>
        <?php endif; ?>

        <!-- Plan Details Card -->
        <div class="card">
            <h2>Your Subscription</h2>
            <div class="info-row">
                <span class="label">Plan</span>
                <span class="value">
                    <span class="plan-badge <?php echo $planType; ?>"><?php echo $planType === 'lifetime' ? 'Lifetime' : 'Monthly'; ?></span>
                    <?php if ($subStatus === 'cancelled'): ?>
                        <span class="plan-badge cancelled" style="margin-left:5px;">Cancelled</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value" style="color: <?php echo $subStatus === 'active' ? '#10b981' : ($subStatus === 'cancelled' ? '#ef4444' : '#f59e0b'); ?>;"><?php echo ucfirst($subStatus); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Provider</span>
                <span class="value"><?php echo ucfirst($provider); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Member Since</span>
                <span class="value"><?php echo $memberSince ? date('F j, Y', strtotime($memberSince)) : 'N/A'; ?></span>
            </div>
            <?php if ($planType === 'monthly' && $nextBilling): ?>
            <div class="info-row">
                <span class="label">Next Billing</span>
                <span class="value"><?php echo date('F j, Y', strtotime($nextBilling)); ?></span>
            </div>
            <?php elseif ($planType === 'lifetime'): ?>
            <div class="info-row">
                <span class="label">Expires</span>
                <span class="value" style="color: #10b981;">Never</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="card">
            <h2>Payment History</h2>
            <?php if (empty($paymentHistory)): ?>
                <p style="color: #64748b; font-size: 14px;">No payment records found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Provider</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $ph): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($ph['created_at'])); ?></td>
                                <td><?php echo ucfirst($ph['provider']); ?></td>
                                <td><?php echo ucfirst($ph['plan_type'] ?? 'lifetime'); ?></td>
                                <td>$<?php echo number_format(($ph['amount_cents'] ?? 200) / 100, 2); ?></td>
                                <td style="color: <?php echo $ph['status'] === 'paid' ? '#10b981' : ($ph['status'] === 'refunded' ? '#f59e0b' : '#94a3b8'); ?>;"><?php echo ucfirst($ph['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="card">
            <h2>Actions</h2>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?php if ($planType === 'monthly' && $subStatus === 'active'): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel? You will retain access until the end of your current period.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="cancel_subscription">
                    <input type="hidden" name="nonce" value="<?php echo htmlspecialchars($cancelNonce); ?>">
                    <button type="submit" class="btn-danger">Cancel Subscription</button>
                </form>
                <?php endif; ?>

                <button type="button" class="btn-warning" onclick="document.getElementById('refundModal').classList.add('active')">Request Refund</button>
            </div>
        </div>
    </div>

    <!-- Refund Request Modal -->
    <div class="modal-overlay" id="refundModal">
        <div class="modal-box">
            <h3>Request a Refund</h3>
            <form id="refundForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="nonce" value="<?php echo htmlspecialchars($refundNonce); ?>">

                <label for="refundReason">Reason *</label>
                <select name="reason" id="refundReason" required>
                    <option value="">Select a reason...</option>
                    <option value="not_useful">Not useful for me</option>
                    <option value="too_expensive">Too expensive</option>
                    <option value="found_alternative">Found an alternative</option>
                    <option value="technical_issues">Technical issues</option>
                    <option value="other">Other</option>
                </select>

                <label for="refundFeedback">Feedback * <span style="font-weight:400;">(min 20 characters)</span></label>
                <textarea name="feedback" id="refundFeedback" placeholder="Tell us more about why you'd like a refund..." required minlength="20"></textarea>

                <div id="refundError" style="display:none; color:#ef4444; font-size:13px; margin-bottom:10px; font-weight:600;"></div>
                <div id="refundSuccess" style="display:none; color:#10b981; font-size:13px; margin-bottom:10px; font-weight:600;"></div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="document.getElementById('refundModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn-warning" id="refundSubmitBtn">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('refundForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('refundSubmitBtn');
            const errorDiv = document.getElementById('refundError');
            const successDiv = document.getElementById('refundSuccess');

            const feedback = form.feedback.value.trim();
            if (feedback.length < 20) {
                errorDiv.textContent = 'Feedback must be at least 20 characters.';
                errorDiv.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Submitting...';
            errorDiv.style.display = 'none';

            const data = new URLSearchParams(new FormData(form));

            fetch('api/refund_request.php', {
                method: 'POST',
                body: data
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = 'Refund request submitted. An admin will review it shortly.';
                    successDiv.style.display = 'block';
                    btn.style.display = 'none';
                } else {
                    errorDiv.textContent = data.error || 'Failed to submit. Please try again.';
                    errorDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Submit Request';
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Connection error. Please try again.';
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Submit Request';
            });
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>

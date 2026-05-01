<?php
/**
 * Parent Consent Portal (Step 3)
 * 
 * Parent-facing page where they:
 * 1. Review what they're consenting to
 * 2. Enter payment information (for $1.00 verification charge)
 * 3. Grant or deny consent
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/VPCManager.php';
require_once __DIR__ . '/../includes/payment.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';

$consentToken = $_GET['token'] ?? '';
$error = '';
$showForm = false;
$success = false;
$successMessage = '';
$denied = false;
$csrfToken = generateCsrfToken();
$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
$stripeClientSecret = '';
$submittedPaymentToken = '';

if (empty($consentToken) || !preg_match('/^[a-f0-9]{64}$/i', $consentToken)) {
    $error = 'Invalid consent link. Please request a new verification code.';
} else {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $vpcManager = new VPCManager();
        $consentRecord = $vpcManager->getPendingConsentByToken($consentToken);
        
        if (!$consentRecord) {
            $error = 'This consent link is invalid or has already been used.';
        } elseif (strtotime($consentRecord['token_expires_at']) < time()) {
            $error = 'This consent link has expired. Please request a new verification code.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$consentRecord['child_user_id']]);
            
            if (!$stmt->fetchColumn()) {
                $error = 'This consent request is no longer available.';
            } else {
                $showForm = true;
                $childUserId = (int)$consentRecord['child_user_id'];
                $parentEmail = $consentRecord['parent_email'];
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                        $error = 'Invalid request. Please refresh the page and try again.';
                    } else {
                        $action = $_POST['action'] ?? '';
                        $submittedPaymentToken = trim((string)($_POST['payment_token'] ?? ''));
                        
                        if ($action === 'grant') {
                            if (!checkIpRateLimit($db, 'vpc_portal_grant_' . $consentRecord['id'], 5, 900)) {
                                $error = 'Too many consent attempts. Please wait a few minutes and try again.';
                            } elseif ($submittedPaymentToken === '') {
                                $error = 'Payment verification is required before consent can be granted.';
                            } else {
                                $result = $vpcManager->processConsentAndPayment($consentToken, $submittedPaymentToken);
                                
                                if ($result['success']) {
                                    $success = true;
                                    $successMessage = $result['message'];
                                    $showForm = false;
                                } else {
                                    $error = $result['message'];
                                }
                            }
                        } elseif ($action === 'deny') {
                            if (!checkIpRateLimit($db, 'vpc_portal_deny_' . $consentRecord['id'], 10, 900)) {
                                $error = 'Too many requests. Please wait a few minutes and try again.';
                            } else {
                                $denied = true;
                                $showForm = false;
                            }
                        }
                    }
                }
                
                if ($showForm && !$success && !$denied && $submittedPaymentToken === '') {
                    if (!arePaymentsEnabled($db)) {
                        $error = 'Payment verification is temporarily disabled by the site administrator. Please try again later.';
                        $showForm = false;
                    } elseif ($stripePublishableKey === '') {
                        error_log('VPC Stripe publishable key missing');
                        $error = 'Payment verification is temporarily unavailable. Please try again later.';
                        $showForm = false;
                    } else {
                        $intentResult = createStripePaymentIntent(100, 'usd', [
                            'purpose' => 'parental_consent',
                            'transaction_id' => (string)$consentRecord['transaction_id'],
                            'child_user_id' => (string)$childUserId,
                        ]);
                        
                        if (!$intentResult['success'] || empty($intentResult['client_secret'])) {
                            error_log('VPC Stripe intent creation failed: ' . ($intentResult['error'] ?? 'unknown'));
                            $error = 'Payment verification is temporarily unavailable. Please try again later.';
                            $showForm = false;
                        } else {
                            $stripeClientSecret = $intentResult['client_secret'];
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Parent consent portal error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
        $showForm = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parental Consent - Spencer's Website</title>
    <?php if ($showForm && $stripePublishableKey !== '' && $stripeClientSecret !== ''): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            border-radius: 15px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        h1 {
            color: #f1f5f9;
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 16px;
            margin-bottom: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            text-align: center;
        }
        
        .alert-success h2 {
            color: #22c55e;
            margin-bottom: 10px;
        }
        
        .info-section {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: #f1f5f9;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .info-section ul {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .info-section li {
            margin-bottom: 8px;
        }
        
        .verification-charge {
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.1), rgba(99, 102, 241, 0.1));
            border: 2px solid rgba(78, 205, 196, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .verification-charge .amount {
            font-size: 36px;
            font-weight: 700;
            color: #4ECDC4;
        }
        
        .verification-charge .label {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .verification-charge .note {
            color: #64748b;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #f1f5f9;
        }
        
        .legal-notice {
            color: #64748b;
            font-size: 12px;
            text-align: center;
            margin-top: 30px;
            line-height: 1.6;
        }
        
        .legal-notice a {
            color: #4ECDC4;
        }
        
        .denied-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .denied-message h2 {
            color: #f87171;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .denied-message p {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .privacy-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 20px;
            padding: 8px 16px;
            color: #22c55e;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">👨‍👩‍👧</div>
        <h1>Parental Consent Required</h1>
        <p class="subtitle">Your child has requested permission to upgrade their account</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success">
                <h2>✓ Consent Granted</h2>
                <p><?php echo htmlspecialchars($successMessage); ?></p>
                <p style="margin-top:15px; font-size:12px;">
                    You will receive a confirmation email with details about how to revoke this consent in the future.
                </p>
            </div>
        <?php elseif (isset($denied) && $denied): ?>
            <div class="denied-message">
                <h2>Consent Not Granted</h2>
                <p>Your child will continue using a Community Account, which allows them to play games without any messaging or social features.</p>
                <p style="margin-top:20px;">No action is required. If you change your mind, your child can request consent again at any time.</p>
            </div>
        <?php elseif ($showForm): ?>
            <div class="privacy-badge">
                <span>🔒</span> COPPA Compliant - Your Privacy is Protected
            </div>
            
            <div class="info-section">
                <h3>What You're Consenting To:</h3>
                <ul>
                    <li>Your child accessing social features (messaging, posting, commenting)</li>
                    <li>Creation of a public profile with username</li>
                    <li>Collection of email address for account management</li>
                    <li>Storage of IP address and device information for security</li>
                    <li>Ability for your child to interact with other users on the platform</li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3>What Stays Protected:</h3>
                <ul>
                    <li>No advertisements are ever shown to your child</li>
                    <li>No personal data is sold to third parties</li>
                    <li>No behavioral tracking for advertising purposes</li>
                    <li>You can revoke consent at any time</li>
                    <li>All data is anonymized upon revocation</li>
                </ul>
            </div>
            
            <div class="verification-charge">
                <div class="amount">$1.00</div>
                <div class="label">Verification Charge</div>
                <div class="note">Non-refundable charge to verify your identity and confirm consent<br>This is a one-time charge, not a subscription</div>
            </div>
            
            <form method="POST" action="" id="consentForm">
                <input type="hidden" name="action" value="grant">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="payment_token" id="payment_token" value="<?php echo htmlspecialchars($submittedPaymentToken); ?>">
                <div id="card-element"></div>
                <div id="payment-error" class="payment-error" aria-live="polite"></div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary" id="grantButton">
                        Grant Consent
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="denyConsent()">
                        Deny Request
                    </button>
                </div>
            </form>
            
            <p class="legal-notice">
                By granting consent, you agree to our <a href="/legal/terms.php" target="_blank">Terms of Service</a> and 
                <a href="/legal/privacy.php" target="_blank">Privacy Policy</a> on behalf of your child. 
                You can revoke this consent at any time through the link that will be provided in the confirmation email.
            </p>
             
             <form id="denyForm" method="POST" action="" style="display:none;">
                 <input type="hidden" name="action" value="deny">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
             </form>
             
            <?php if ($stripePublishableKey !== '' && $stripeClientSecret !== ''): ?>
             <script>
                const consentForm = document.getElementById('consentForm');
                const paymentTokenField = document.getElementById('payment_token');
                const paymentError = document.getElementById('payment-error');
                const grantButton = document.getElementById('grantButton');
                const stripe = Stripe('<?php echo htmlspecialchars($stripePublishableKey, ENT_QUOTES); ?>');
                const elements = stripe.elements();
                const card = elements.create('card', {
                    style: {
                        base: {
                            color: '#f1f5f9',
                            fontFamily: 'Segoe UI, sans-serif',
                            fontSize: '16px',
                            '::placeholder': {
                                color: '#64748b'
                            }
                        },
                        invalid: {
                            color: '#f87171'
                        }
                    }
                });
                card.mount('#card-element');
                card.on('change', function(event) {
                    paymentError.textContent = event.error ? event.error.message : '';
                });

                consentForm.addEventListener('submit', async function(event) {
                    if (paymentTokenField.value) {
                        return;
                    }

                    event.preventDefault();
                    paymentError.textContent = '';
                    grantButton.disabled = true;

                    const result = await stripe.confirmCardPayment('<?php echo htmlspecialchars($stripeClientSecret, ENT_QUOTES); ?>', {
                        payment_method: {
                            card: card,
                            billing_details: {
                                email: '<?php echo htmlspecialchars($parentEmail, ENT_QUOTES); ?>'
                            }
                        }
                    });

                    if (result.error) {
                        paymentError.textContent = result.error.message || 'Payment verification failed. Please check your card details and try again.';
                        grantButton.disabled = false;
                        return;
                    }

                    if (!result.paymentIntent || result.paymentIntent.status !== 'succeeded') {
                        paymentError.textContent = 'Payment verification did not complete. Please try again.';
                        grantButton.disabled = false;
                        return;
                    }

                    paymentTokenField.value = result.paymentIntent.id;
                    consentForm.submit();
                });

                 function denyConsent() {
                     if (confirm('Are you sure you want to deny this request? Your child will continue using the free Community Account without social features.')) {
                         document.getElementById('denyForm').submit();
                     }
                 }
             </script>
            <?php else: ?>
            <script>
                function denyConsent() {
                    if (confirm('Are you sure you want to deny this request? Your child will continue using the free Community Account without social features.')) {
                        document.getElementById('denyForm').submit();
                    }
                }
            </script>
            <?php endif; ?>
         <?php endif; ?>
     </div>
 </body>
 </html>

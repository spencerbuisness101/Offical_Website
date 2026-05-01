<?php
/**
 * Post-Payment Registration Page - Spencer's Website v7.0
 * Creates a new user account after successful payment.
 * Only accessible with a valid, verified payment token.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/subscription.php';
require_once __DIR__ . '/includes/db.php';

// Database connection
try {
    $db = db();
    ensurePaymentTables($db);
} catch (Exception $e) {
    error_log("Register DB error: " . $e->getMessage());
    die('A database error occurred. Please try again later.');
}

// --- Step 1: Validate payment token + HMAC ---
$token = sanitizeInput($_GET['token'] ?? $_POST['payment_token'] ?? '');
$hmac = sanitizeInput($_GET['hmac'] ?? $_POST['hmac'] ?? '');
$tokenError = '';
$paymentSession = null;

if (empty($token)) {
    $tokenError = 'No payment token provided. You must complete payment before creating an account.';
} else {
    // Validate token format
    if (!preg_match('/^[a-zA-Z0-9_-]{32,64}$/', $token)) {
        $tokenError = 'Invalid token format. Please use the payment link provided.';
    } else {
        // Verify HMAC signature on callback URL
        if (!empty($hmac) && !verifyCallbackHmac($token, $hmac)) {
            $tokenError = 'Invalid callback signature. This URL may have been tampered with.';
        } else {
            $paymentSession = getPaymentSession($db, $token);
            if (!$paymentSession) {
                $tokenError = 'Invalid or expired payment token. Tokens expire after 30 minutes.';
            } elseif ($paymentSession['status'] !== 'paid') {
                $tokenError = 'Payment has not been completed yet. Please complete your payment first.';
            } elseif ($paymentSession['used']) {
                $tokenError = 'This payment token has already been used to create an account.';
            }
        }
    }
}

// Extract plan type from payment session
$planType = ($paymentSession['plan_type'] ?? 'lifetime');
$provider = ($paymentSession['provider'] ?? 'stripe');

// --- Step 3: Handle form submission ---
$formErrors = [];
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tokenError) {
    // Rate limit registration: 3/minute per IP (DB-backed, not session-based)
    require_once __DIR__ . '/includes/rate_limit_ip.php';
    try {
        enforceIpRateLimit($db, 'register', 3, 60);
    } catch (Exception $e) {
        error_log("Register rate limit DB error: " . $e->getMessage());
    }

    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $formErrors[] = 'Invalid form token. Please refresh and try again.';
    }

    // reCAPTCHA v3 verification
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    $recaptchaSecret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if ($recaptchaSecret) {
        if (empty($recaptchaResponse)) {
            $formErrors[] = 'Please complete the CAPTCHA verification.';
        } elseif (!verifyRecaptcha($recaptchaResponse)) {
            $formErrors[] = 'CAPTCHA verification failed. Please try again.';
        }
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $formErrors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores.';
    }

    // Check username uniqueness
    if (empty($formErrors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $formErrors[] = 'This username is already taken. Please choose another.';
        }
    }

    // Password validation
    if (strlen($password) < 8) {
        $formErrors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $formErrors[] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirmPassword) {
        $formErrors[] = 'Passwords do not match.';
    }

    // Terms acceptance validation
    if (empty($_POST['agree_terms'])) {
        $formErrors[] = 'You must agree to the Terms of Service, Privacy Policy, and Refund Policy.';
    }

    // Re-validate token (prevent race condition)
    if (empty($formErrors)) {
        $paymentSession = getPaymentSession($db, $token);
        if (!$paymentSession || $paymentSession['status'] !== 'paid' || $paymentSession['used']) {
            $formErrors[] = 'Payment token is no longer valid.';
        }
    }

    // --- Step 4: Create account ---
    if (empty($formErrors)) {
        try {
            $db->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

            // Insert user (with terms acceptance timestamp)
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, terms_accepted_at) VALUES (?, ?, 'user', NOW())");
            $stmt->execute([$username, $passwordHash]);
            $newUserId = $db->lastInsertId();

            // Determine subscription IDs from payment session
            $providerSubId = $paymentSession['provider_session_id'] ?? null;
            $stripeSubId = ($planType === 'monthly') ? $providerSubId : null;
            $periodEnd = ($planType === 'monthly') ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;

            // Insert premium record with plan type
            $stmt = $db->prepare("
                INSERT INTO user_premium (user_id, is_premium, premium_since, payment_session_id, plan_type, provider,
                    stripe_subscription_id, paypal_subscription_id, subscription_status, current_period_end, last_payment_at)
                VALUES (?, TRUE, NOW(), ?, ?, ?, ?, NULL, 'active', ?, NOW())
            ");
            $stmt->execute([$newUserId, $paymentSession['id'], $planType, $provider, $stripeSubId, $periodEnd]);

            // Create subscription ledger entry
            $stmt = $db->prepare("
                INSERT INTO subscriptions (user_id, plan_type, provider, provider_subscription_id, status, amount_cents,
                    current_period_start, current_period_end)
                VALUES (?, ?, ?, ?, 'active', ?, NOW(), ?)
            ");
            $stmt->execute([$newUserId, $planType, $provider, $providerSubId, getPlanAmount($planType), $periodEnd]);

            // Mark token as used
            markPaymentUsed($db, $token);

            $db->commit();
            $formSuccess = true;

            // Set flash message in session for index.php
            $_SESSION['registration_success'] = 'Account created successfully! You can now log in.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $formErrors[] = 'An error occurred creating your account. Please try again.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="register-overlay">
        <?php if ($tokenError): ?>
        <!-- Token Error State -->
        <div class="token-error-container">
            <h2>Payment Required</h2>
            <p><?php echo htmlspecialchars($tokenError); ?></p>
            <a href="index.php" class="back-btn">Back to Home</a>
        </div>
        <?php else: ?>
        <!-- Registration Form -->
        <div class="register-container">
            <h2>Create Your Account</h2>
            <p class="subtitle">Payment verified! Set up your account below.
                <br><span style="display:inline-block; margin-top:6px; background:<?php echo $planType === 'lifetime' ? '#10b981' : '#3b82f6'; ?>; color:white; padding:3px 10px; border-radius:8px; font-size:13px; font-weight:700;">
                    <?php echo $planType === 'lifetime' ? 'Lifetime Access' : 'Monthly Plan'; ?>
                </span>
            </p>

            <?php if (!empty($formErrors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($formErrors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="register.php?token=<?php echo htmlspecialchars(urlencode($token)); ?>" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="payment_token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required
                           pattern="[a-zA-Z0-9_]{3,20}" maxlength="20" minlength="3"
                           placeholder="Choose a username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           autocomplete="username">
                    <div class="hint">3-20 characters. Letters, numbers, and underscores only.</div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           minlength="8" placeholder="Create a password"
                           autocomplete="new-password">
                    <div class="password-strength"><div class="bar" id="strengthBar"></div></div>
                    <div class="hint">At least 8 characters with a letter and a number.</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           minlength="8" placeholder="Confirm your password"
                           autocomplete="new-password">
                </div>

                <div class="form-group" style="margin-top: 8px;">
                    <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; font-size:0.85rem; color:#cbd5e1; font-weight:400; line-height:1.5;">
                        <input type="checkbox" name="agree_terms" value="1" required
                               style="margin-top:3px; accent-color:#4ECDC4; width:18px; height:18px; flex-shrink:0;"
                               <?php echo !empty($_POST['agree_terms']) ? 'checked' : ''; ?>>
                        <span>I have read and agree to the
                            <a href="terms.php" target="_blank" style="color:#4ECDC4;">Terms of Service</a>,
                            <a href="privacy.php" target="_blank" style="color:#4ECDC4;">Privacy Policy</a>, and
                            <a href="refund-policy.php" target="_blank" style="color:#4ECDC4;">Refund Policy</a>.
                        </span>
                    </label>
                </div>

                <input type="hidden" name="g-recaptcha-response" id="recaptchaToken">

                <button type="submit" class="register-btn" id="submitBtn">Create Account</button>
            </form>

            <p style="margin-top: 20px; font-size: 13px; color: #7f8c8d;">
                Already have an account? <a href="index.php" style="color: #3b82f6; font-weight: 600;">Log in</a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const val = this.value;
                let strength = 0;
                if (val.length >= 8) strength++;
                if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
                if (/[0-9]/.test(val)) strength++;
                if (/[^a-zA-Z0-9]/.test(val)) strength++;
                if (val.length >= 12) strength++;

                const percent = Math.min((strength / 5) * 100, 100);
                const colors = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#27ae60'];
                strengthBar.style.width = percent + '%';
                strengthBar.style.background = colors[Math.min(strength, 4)] || '#e74c3c';
            });
        }

        // Username live validation
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                // Strip invalid characters in real-time
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            });
        }

        // reCAPTCHA v3: silent token acquisition
        const RECAPTCHA_KEY = '<?php echo defined("RECAPTCHA_SITE_KEY") ? htmlspecialchars(RECAPTCHA_SITE_KEY) : ""; ?>';
        function refreshRegisterCaptcha() {
            if (RECAPTCHA_KEY && typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(RECAPTCHA_KEY, {action: 'register'}).then(function(token) {
                        var el = document.getElementById('recaptchaToken');
                        if (el) el.value = token;
                    });
                });
            }
        }
        if (RECAPTCHA_KEY) {
            document.addEventListener('DOMContentLoaded', refreshRegisterCaptcha);
        }

        // Prevent double-submit
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Creating Account...';
                }
            });
        }
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>

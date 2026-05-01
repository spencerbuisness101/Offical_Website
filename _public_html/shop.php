<?php
/**
 * Shop Page - Spencer's Website v7.0
 * Dedicated purchase page with Stripe Elements embedded form.
 * Products: Monthly ($2), Yearly ($20), Lifetime ($100), Donation ($1–$100).
 * Post-purchase: account creation with username/password validation.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/config/database.php';

// Generate CSRF token
$csrfToken = generateCsrfToken();

// Database connection
$db = null;
$products = [];
try {
    require_once __DIR__ . '/includes/db.php';
    $db = db();
    ensurePaymentTables($db);

    // Seed products if empty
    $countStmt = $db->query("SELECT COUNT(*) FROM products");
    if ((int)$countStmt->fetchColumn() === 0) {
        $db->exec("INSERT INTO products (name, slug, description, price_cents, currency, type, plan_type, is_active, sort_order) VALUES
            ('Monthly Membership', 'monthly', 'Full access to all member benefits. Billed monthly.', 300, 'usd', 'subscription', 'monthly', TRUE, 1),
            ('Yearly Membership', 'yearly', 'Full access to all member benefits. Billed yearly — SAVE 11% vs monthly!', 3200, 'usd', 'subscription', 'yearly', TRUE, 2),
            ('Lifetime Membership', 'lifetime', 'One-time payment for permanent full access. Best value.', 10000, 'usd', 'one-time', 'lifetime', TRUE, 3),
            ('Donation', 'donation', 'Support the website with a custom donation (\$1–\$100).', 0, 'usd', 'donation', 'donation', TRUE, 4)
        ");
    }

    $products = getActiveProducts($db);
    // Check if payments are enabled
    $paymentsEnabled = arePaymentsEnabled($db);
} catch (Exception $e) {
    $paymentsEnabled = true;
    error_log("Shop page DB error: " . $e->getMessage());
}

// Check if user is already logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$currentRole = $_SESSION['role'] ?? 'guest';
$currentUserId = $_SESSION['user_id'] ?? null;

// Determine what the user came here to do.
// - No plan / specific paid plan (monthly|yearly|lifetime): show ONLY that product family, hide donation UI.
// - plan=donation: show ONLY the donation section (dedicated donate.php flow redirects here).
$planFilter = strtolower(trim((string)($_GET['plan'] ?? '')));
$allowedPlans = ['monthly', 'yearly', 'lifetime', 'donation'];
if (!in_array($planFilter, $allowedPlans, true)) {
    $planFilter = ''; // empty = show all paid plans (legacy behavior)
}
$isDonationOnly = ($planFilter === 'donation');
$isPaidOnly     = in_array($planFilter, ['monthly', 'yearly', 'lifetime'], true);

// Prefilled donation values (from donate.php landing page)
$prefillAmount = '';
$prefillMsg    = '';
if ($isDonationOnly) {
    $amt = (float)($_GET['amount'] ?? 0);
    if ($amt >= 1 && $amt <= 100) {
        $prefillAmount = number_format($amt, 2, '.', '');
    }
    $prefillMsg = substr((string)($_GET['msg'] ?? ''), 0, 500);
}

// Check for post-payment account creation flow
$paymentToken = $_GET['token'] ?? '';
$paymentHmac = $_GET['hmac'] ?? '';
$showAccountCreation = false;
$paymentSuccess = false;

if ($paymentToken && $paymentHmac && $db) {
    if (verifyCallbackHmac($paymentToken, $paymentHmac)) {
        $session = getPaymentSession($db, $paymentToken);
        if ($session && $session['status'] === 'paid' && !$session['user_id']) {
            $showAccountCreation = true;
            $paymentSuccess = true;
        }
    }
}

// Handle account creation POST
$accountError = '';
$accountSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    header('Content-Type: application/json');

    $postCsrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($postCsrf)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh.']);
        exit;
    }

    // Google reCAPTCHA v3 verification (invisible)
    $recaptchaToken = $_POST['recaptcha_token'] ?? $_POST['g-recaptcha-response'] ?? '';
    $recaptchaSecret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if ($recaptchaSecret) {
        if (empty($recaptchaToken)) {
            echo json_encode(['success' => false, 'error' => 'Security verification unavailable. Please refresh and try again.']);
            exit;
        }
        if (!verifyRecaptcha($recaptchaToken, 0.3)) {
            echo json_encode(['success' => false, 'error' => 'Security verification failed. Please refresh and try again.']);
            exit;
        }
    }

    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newPasswordConfirm = $_POST['password_confirm'] ?? '';
    $accountFeedback = trim($_POST['feedback'] ?? '');
    $accPaymentToken = $_POST['payment_token'] ?? '';
    $accHmac = $_POST['payment_hmac'] ?? '';

    // Validate payment token
    if (!$accPaymentToken || !$accHmac || !verifyCallbackHmac($accPaymentToken, $accHmac)) {
        echo json_encode(['success' => false, 'error' => 'Invalid payment verification.']);
        exit;
    }

    // Race-condition-safe: lock the payment session row before reading (CREV-02)
    $db->beginTransaction();
    $lockStmt = $db->prepare("SELECT id, status, user_id FROM payment_sessions WHERE token = ? FOR UPDATE");
    $lockStmt->execute([$accPaymentToken]);
    $accSession = $lockStmt->fetch(PDO::FETCH_ASSOC);
    if (!$accSession || $accSession['status'] !== 'paid') {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Payment not found or not completed.']);
        exit;
    }
    if ($accSession['user_id']) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Account already created for this payment.']);
        exit;
    }

    // Validate username
    if (!validateUsername($newUsername)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Username must be 3–30 characters (letters, numbers, underscore, hyphen).']);
        exit;
    }

    // Username blocklist
    $blocklist = ['admin', 'administrator', 'moderator', 'system', 'root', 'support',
        'fuck', 'shit', 'ass', 'bitch', 'nigger', 'nigga', 'faggot', 'retard',
        'pussy', 'dick', 'cock', 'cunt', 'whore', 'slut', 'rape', 'nazi', 'hitler'];
    $lowerUsername = strtolower($newUsername);
    foreach ($blocklist as $blocked) {
        if (str_contains($lowerUsername, $blocked)) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'That username is not allowed.']);
            exit;
        }
    }

    // Check username uniqueness
    $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$newUsername]);
    if ($checkStmt->fetch()) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Username already taken.']);
        exit;
    }

    // Validate password
    if (!validatePassword($newPassword)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Password must be 8+ characters with at least one letter, one number, and one special character.']);
        exit;
    }
    if ($newPassword !== $newPasswordConfirm) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
        exit;
    }

    // Sanitize feedback
    if (strlen($accountFeedback) > 500) {
        $accountFeedback = substr($accountFeedback, 0, 500);
    }
    $accountFeedback = htmlspecialchars($accountFeedback, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Create account (transaction already started above for FOR UPDATE lock)
    try {
        $passwordHash = hashPassword($newPassword);
        $insertStmt = $db->prepare("INSERT INTO users (username, password_hash, role, is_active, terms_accepted_at) VALUES (?, ?, 'user', 1, NOW())");
        $insertStmt->execute([$newUsername, $passwordHash]);
        $newUserId = (int)$db->lastInsertId();

        // Link payment session to user
        $linkStmt = $db->prepare("UPDATE payment_sessions SET user_id = ? WHERE token = ?");
        $linkStmt->execute([$newUserId, $accPaymentToken]);

        // Upgrade user role (creates premium/subscription records)
        $planType = $accSession['plan_type'] ?? 'lifetime';
        if ($planType !== 'donation') {
            upgradeUserRole($db, $newUserId, $accSession['id'], $planType, 'stripe', null);
        }

        // Store feedback if provided
        if ($accountFeedback) {
            $fbStmt = $db->prepare("INSERT INTO user_feedback (user_id, content, status) VALUES (?, ?, 'pending')");
            $fbStmt->execute([$newUserId, $accountFeedback]);
        }

        $db->commit();

        // Log the user in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $newUsername;
        $_SESSION['role'] = 'user';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['is_suspended'] = false;
        session_write_close();

        echo json_encode(['success' => true, 'redirect' => 'main.php']);
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Account creation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Account creation failed. Please try again.']);
        exit;
    }
}

$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <?php $recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : ''; ?>
    <?php if ($recaptchaSiteKey): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptchaSiteKey); ?>" async defer></script>
    <?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            min-height: 100vh;
            line-height: 1.6;
        }

        .shop-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .shop-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .shop-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .shop-header p {
            color: #9ca3af;
            font-size: 1.1rem;
        }

        .shop-nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .shop-nav a {
            color: #9ca3af;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .shop-nav a:hover {
            color: #e0e0e0;
            background: rgba(255,255,255,0.05);
        }

        /* Product Cards */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .product-card {
            background: #111111;
            border: 1px solid #222;
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.15);
        }

        .product-card.featured {
            border-color: #8b5cf6;
        }

        .product-card.featured::before {
            content: 'BEST VALUE';
            position: absolute;
            top: 12px;
            right: -30px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 40px;
            transform: rotate(45deg);
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #f0f0f0;
        }

        .product-price {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .product-price .currency { font-size: 1.2rem; vertical-align: top; color: #9ca3af; }
        .product-price .period { font-size: 0.9rem; color: #6b7280; font-weight: 400; }

        .product-desc {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .product-features {
            list-style: none;
            margin-bottom: 1.5rem;
        }

        .product-features li {
            padding: 0.3rem 0;
            font-size: 0.9rem;
            color: #d1d5db;
        }

        .product-features li::before {
            content: '\2713';
            color: #10b981;
            font-weight: 700;
            margin-right: 0.5rem;
        }

        .btn-purchase {
            display: block;
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-purchase.primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-purchase.primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-purchase.featured {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
        }

        .btn-purchase.featured:hover {
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.4);
        }

        .btn-purchase:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Donation Section */
        .donation-section {
            background: #111111;
            border: 1px solid #222;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 3rem;
        }

        .donation-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #f0f0f0;
        }

        .donation-section p {
            color: #9ca3af;
            margin-bottom: 1.5rem;
        }

        .donation-form {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1rem;
            align-items: start;
        }

        @media (max-width: 640px) {
            .donation-form {
                grid-template-columns: 1fr;
            }
        }

        .donation-amount-group {
            position: relative;
        }

        .donation-amount-group .currency-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .donation-amount-input {
            width: 100%;
            padding: 0.85rem 0.85rem 0.85rem 2rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            color: #e0e0e0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .donation-amount-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .donation-feedback-textarea {
            width: 100%;
            padding: 0.85rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            color: #e0e0e0;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 80px;
            max-height: 160px;
            font-family: inherit;
        }

        .donation-feedback-textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .char-counter {
            text-align: right;
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .char-counter.warn { color: #f59e0b; }
        .char-counter.over { color: #ef4444; }

        /* Payment Form */
        .payment-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .payment-modal.active { display: flex; }

        .payment-modal-content {
            background: #111;
            border: 1px solid #333;
            border-radius: 16px;
            padding: 2rem;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .payment-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .payment-modal-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .payment-modal-close {
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .payment-summary {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-summary .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            font-size: 0.95rem;
        }

        .payment-summary .summary-total {
            border-top: 1px solid #333;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        #stripe-element {
            padding: 1rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .btn-pay {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-pay:hover { box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4); }
        .btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }

        .payment-error {
            color: #ef4444;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            min-height: 1.2em;
        }

        .payment-processing {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
        }

        .payment-processing i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Account Creation */
        .account-section {
            background: #111;
            border: 1px solid #10b981;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            margin: 2rem auto;
        }

        .account-section h2 {
            color: #10b981;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: #d1d5db;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
        }

        .form-group .hint {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .btn-create-account {
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-create-account:hover {
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-create-account:disabled { opacity: 0.5; cursor: not-allowed; }

        .account-error {
            color: #ef4444;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            min-height: 1.2em;
        }

        .secure-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            color: #6b7280;
            font-size: 0.8rem;
            margin-top: 1.5rem;
        }

        .secure-badge i { color: #10b981; }

        /* Success banner */
        .success-banner {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .shop-header h1 { font-size: 1.8rem; }
            .products-grid { grid-template-columns: 1fr; }
            .product-price { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>

<div class="shop-container">
    <nav class="shop-nav">
        <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        <?php if ($isLoggedIn): ?>
            <a href="main.php"><i class="fas fa-home"></i> Dashboard</a>
        <?php endif; ?>
    </nav>

    <?php if ($showAccountCreation): ?>
        <!-- Post-Payment Account Creation -->
        <div class="success-banner">
            <i class="fas fa-check-circle"></i> Payment successful! Create your account below.
        </div>

        <div class="account-section">
            <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
            <div class="account-error" id="accountError"></div>

            <form id="accountForm" onsubmit="return handleAccountCreation(event)">
                <input type="hidden" name="action" value="create_account">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="payment_token" value="<?php echo htmlspecialchars($paymentToken); ?>">
                <input type="hidden" name="payment_hmac" value="<?php echo htmlspecialchars($paymentHmac); ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required minlength="3" maxlength="30"
                           pattern="[a-zA-Z0-9_\-]+" placeholder="Choose a username" autocomplete="username">
                    <p class="hint">3–30 characters. Letters, numbers, underscore, hyphen.</p>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8"
                           placeholder="Create a strong password" autocomplete="new-password">
                    <p class="hint">Min 8 chars, with 1 letter, 1 number, 1 special character.</p>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           placeholder="Confirm your password" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="feedback">Feedback <span style="color:#6b7280">(optional)</span></label>
                    <textarea id="feedback" name="feedback" rows="3" maxlength="500"
                              placeholder="How did you hear about us? Any suggestions?"></textarea>
                    <p class="hint">Visible to admins. Max 500 characters.</p>
                </div>

                <input type="hidden" name="recaptcha_token" id="recaptchaToken" value="">

                <button type="submit" class="btn-create-account" id="btnCreateAccount">
                    <i class="fas fa-rocket"></i> Create Account & Enter
                </button>
            </form>

            <div class="secure-badge">
                <i class="fas fa-lock"></i>
                Your password is encrypted with Argon2id. We never store plaintext passwords.
            </div>
        </div>

    <?php else: ?>
        <!-- Shop Header -->
        <div class="shop-header">
            <h1>Spencer's Website</h1>
            <p>Choose a plan to unlock the full experience</p>
        </div>

        <?php if (!$paymentsEnabled): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 30px; text-align: center; color: #f87171; margin-top: 30px;">
                <i class="fas fa-store-slash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.8;"></i>
                <h2 style="margin-bottom: 10px; color: #fca5a5;">Purchases Temporarily Disabled</h2>
                <p style="color: #fca5a5;">We are currently not accepting new payments. Please check back later.</p>
                <?php if ($isPremium): ?>
                <p style="margin-top: 15px; font-size: 0.9em; color: #f1f5f9;"><i class="fas fa-info-circle"></i> Existing premium members retain full access during this period.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Product Cards -->
            <div class="products-grid">
                <?php
                $features = [
                    'Custom backgrounds',
                    'AI assistant access',
                    'Yaps chat with tags',
                    'Accent color customization',
                    'Server-synced settings',
                    'Full game library access',
                ];
                foreach ($products as $product):
                    if ($product['type'] === 'donation') continue;
                    // If user specified a single plan, only show that card
                    if ($isPaidOnly && $product['slug'] !== $planFilter) continue;
                    // If user came for donation only, hide all paid product cards
                    if ($isDonationOnly) continue;
                    $isFeatured = $product['slug'] === 'lifetime';
                    $priceFormatted = number_format($product['price_cents'] / 100, 2);
                    $period = match($product['slug']) {
                        'monthly' => '/mo',
                        'yearly' => '/yr',
                        default => '',
                    };
                ?>
                <div class="product-card <?php echo $isFeatured ? 'featured' : ''; ?>">
                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="product-price">
                        <span class="currency">$</span><?php echo $priceFormatted; ?><span class="period"><?php echo $period; ?></span>
                    </div>
                    <p class="product-desc"><?php echo htmlspecialchars($product['description']); ?></p>
                    <ul class="product-features">
                        <?php foreach ($features as $f): ?>
                            <li><?php echo $f; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button class="btn-purchase <?php echo $isFeatured ? 'featured' : 'primary'; ?>"
                            onclick="selectProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price_cents']; ?>)">
                        <?php echo $isFeatured ? 'Get Lifetime Access' : 'Choose ' . htmlspecialchars($product['name']); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isDonationOnly): ?>
            <!-- Donation Section (only when explicitly requested via ?plan=donation) -->
            <div class="donation-section">
                <h2><i class="fas fa-heart" style="color:#ef4444"></i> Support the Website</h2>
                <p>Love what we're building? Leave a tip and an optional message. Every dollar helps keep the servers running.</p>

                <div class="donation-form">
                    <div>
                        <div class="donation-amount-group">
                            <span class="currency-prefix">$</span>
                            <input type="number" class="donation-amount-input" id="donationAmount"
                                   min="1" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars($prefillAmount !== '' ? $prefillAmount : '5'); ?>" placeholder="5.00">
                        </div>
                    </div>
                    <div>
                        <textarea class="donation-feedback-textarea" id="donationFeedback"
                                  maxlength="500" placeholder="Say thanks, share an idea, or just leave a note (optional)"><?php echo htmlspecialchars($prefillMsg); ?></textarea>
                        <div class="char-counter" id="donationCharCounter"><?php echo strlen($prefillMsg); ?> / 500</div>
                    </div>
                </div>
                <br>
                <button class="btn-purchase primary" onclick="selectDonation()" style="max-width:300px">
                    <i class="fas fa-heart"></i> Donate
                </button>
            </div>
            <?php elseif ($isPaidOnly): ?>
            <!-- Cross-link: paid plan view can jump to dedicated donate page -->
            <div style="text-align:center; margin-top:32px; padding:20px; font-size:13px; color:#94a3b8;">
                Prefer to donate instead? <a href="donate.php" style="color:#FF6BB3;">Visit the donation page →</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div class="payment-modal" id="paymentModal">
    <div class="payment-modal-content">
        <div class="payment-modal-header">
            <h2 id="paymentTitle">Complete Payment</h2>
            <button class="payment-modal-close" onclick="closePaymentModal()">&times;</button>
        </div>

        <div class="payment-summary">
            <div class="summary-item">
                <span id="summaryProduct">Product</span>
                <span id="summaryPrice">$0.00</span>
            </div>
            <div class="summary-item summary-total">
                <span>Total</span>
                <span id="summaryTotal">$0.00</span>
            </div>
        </div>

        <div id="stripeFormContainer">
            <div id="stripe-element"></div>
            <button class="btn-pay" id="btnPay" onclick="submitPayment()">
                <i class="fas fa-lock"></i> Pay Now
            </button>
            <div class="payment-error" id="paymentError"></div>
        </div>

        <div class="payment-processing" id="paymentProcessing" style="display:none">
            <i class="fas fa-spinner"></i>
            <p>Processing your payment...</p>
        </div>

        <div class="secure-badge">
            <i class="fas fa-shield-alt"></i>
            Secured by Stripe. We never see your card details.
        </div>
    </div>
</div>

<?php if ($stripePublishableKey && !$showAccountCreation): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe('<?php echo htmlspecialchars($stripePublishableKey); ?>');
const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';
const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const currentUserId = <?php echo $currentUserId ? (int)$currentUserId : 'null'; ?>;

let elements = null;
let paymentElement = null;
let currentClientSecret = null;
let currentPaymentToken = null;
let currentHmac = null;
let currentIsDonation = false;

// Donation character counter
const donationFeedbackEl = document.getElementById('donationFeedback');
const donationCounterEl = document.getElementById('donationCharCounter');
if (donationFeedbackEl) {
    donationFeedbackEl.addEventListener('input', function() {
        const len = this.value.length;
        donationCounterEl.textContent = len + ' / 500';
        donationCounterEl.className = 'char-counter' + (len > 450 ? (len >= 500 ? ' over' : ' warn') : '');
    });
}

async function selectProduct(productId, productName, priceCents) {
    document.getElementById('paymentTitle').textContent = 'Purchase ' + productName;
    const priceStr = '$' + (priceCents / 100).toFixed(2);
    document.getElementById('summaryProduct').textContent = productName;
    document.getElementById('summaryPrice').textContent = priceStr;
    document.getElementById('summaryTotal').textContent = priceStr;
    currentIsDonation = false;
    await createIntent({ product_id: productId });
}

async function selectDonation() {
    const amountEl = document.getElementById('donationAmount');
    const amount = parseFloat(amountEl.value);
    if (isNaN(amount) || amount < 1 || amount > 100) {
        alert('Donation must be between $1.00 and $100.00');
        return;
    }
    const priceStr = '$' + amount.toFixed(2);
    document.getElementById('paymentTitle').textContent = 'Donate';
    document.getElementById('summaryProduct').textContent = 'Donation';
    document.getElementById('summaryPrice').textContent = priceStr;
    document.getElementById('summaryTotal').textContent = priceStr;
    currentIsDonation = true;
    const feedback = document.getElementById('donationFeedback').value.trim();
    await createIntent({ is_donation: 1, amount: amount, donation_feedback: feedback });
}

async function createIntent(params) {
    const modal = document.getElementById('paymentModal');
    const errorEl = document.getElementById('paymentError');
    const formContainer = document.getElementById('stripeFormContainer');
    const processing = document.getElementById('paymentProcessing');

    errorEl.textContent = '';
    formContainer.style.display = 'none';
    processing.style.display = 'block';
    modal.classList.add('active');

    const body = new URLSearchParams({ csrf_token: csrfToken, ...params });
    if (isLoggedIn && currentUserId) {
        body.set('user_id', currentUserId);
    }

    try {
        const resp = await fetch('api/create_payment_intent.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Failed to initialize payment.';
            processing.style.display = 'none';
            formContainer.style.display = 'block';
            return;
        }

        currentClientSecret = data.client_secret;
        currentPaymentToken = data.payment_token;
        currentHmac = data.hmac;

        // Mount Stripe Elements
        if (elements) {
            elements.getElement('payment')?.destroy();
        }
        elements = stripe.elements({
            clientSecret: currentClientSecret,
            appearance: {
                theme: 'night',
                variables: {
                    colorPrimary: '#3b82f6',
                    colorBackground: '#1a1a1a',
                    colorText: '#e0e0e0',
                    borderRadius: '8px',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                },
            },
        });

        paymentElement = elements.create('payment');
        document.getElementById('stripe-element').innerHTML = '';
        paymentElement.mount('#stripe-element');

        processing.style.display = 'none';
        formContainer.style.display = 'block';

    } catch (err) {
        console.error('Payment intent error:', err);
        errorEl.textContent = 'Network error. Please try again.';
        processing.style.display = 'none';
        formContainer.style.display = 'block';
    }
}

async function submitPayment() {
    const btnPay = document.getElementById('btnPay');
    const errorEl = document.getElementById('paymentError');
    btnPay.disabled = true;
    btnPay.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    errorEl.textContent = '';

    let returnUrl;
    if (isLoggedIn) {
        returnUrl = window.location.origin + '/main.php?upgrade=success&token=' +
            encodeURIComponent(currentPaymentToken) + '&hmac=' + encodeURIComponent(currentHmac);
    } else if (currentIsDonation) {
        returnUrl = window.location.origin + '/shop.php?donated=1';
    } else {
        returnUrl = window.location.origin + '/shop.php?token=' +
            encodeURIComponent(currentPaymentToken) + '&hmac=' + encodeURIComponent(currentHmac);
    }

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: returnUrl },
    });

    if (error) {
        errorEl.textContent = error.message;
        btnPay.disabled = false;
        btnPay.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
    }
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
}

// Close modal on escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePaymentModal();
});
</script>
<?php endif; ?>

<?php if ($showAccountCreation): ?>
<script>
async function handleAccountCreation(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCreateAccount');
    const errorEl = document.getElementById('accountError');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
    errorEl.textContent = '';

    // Get reCAPTCHA v3 token (invisible)
    const recaptchaSiteKey = '<?php echo htmlspecialchars($recaptchaSiteKey ?? ""); ?>';
    if (recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
        try {
            await new Promise(r => grecaptcha.ready(r));
            const token = await grecaptcha.execute(recaptchaSiteKey, { action: 'create_account' });
            document.getElementById('recaptchaToken').value = token;
        } catch (err) {
            errorEl.textContent = 'Security verification failed. Please refresh.';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-rocket"></i> Create Account & Enter';
            return false;
        }
    }

    const formData = new FormData(document.getElementById('accountForm'));
    try {
        const resp = await fetch('shop.php', { method: 'POST', credentials: 'same-origin', body: formData });
        const data = await resp.json();
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Success!';
            setTimeout(() => { window.location.href = data.redirect || 'main.php'; }, 800);
        } else {
            errorEl.textContent = data.error || 'Failed to create account.';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-rocket"></i> Create Account & Enter';
        }
    } catch (err) {
        errorEl.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-rocket"></i> Create Account & Enter';
    }
    return false;
}
</script>
<?php endif; ?>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>

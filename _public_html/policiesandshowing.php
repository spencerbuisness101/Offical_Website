<?php
/**
 * Policies & Legal Hub - Spencer's Website v7.0 Elite
 * Accordion-style layout for all legal documents.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role = $_SESSION['role'] ?? 'community';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policies & Legal - Spencer's Website</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        *{box-sizing:border-box;}
        .lh-wrap{max-width:800px;margin:0 auto;padding:24px 20px 60px;}
        .lh-back{display:inline-flex;align-items:center;gap:6px;color:#64748b;text-decoration:none;font-size:0.85rem;margin-bottom:18px;transition:color .2s;}
        .lh-back:hover{color:#4ECDC4;}
        .lh-hero{text-align:center;margin-bottom:32px;padding:36px 24px;background:rgba(15,23,42,0.6);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.06);border-radius:18px;}
        .lh-hero h1{font-size:2rem;font-weight:800;background:linear-gradient(135deg,#4ECDC4,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0 0 8px;}
        .lh-hero p{color:#94a3b8;font-size:0.92rem;margin:0;}
        .lh-accordion{display:flex;flex-direction:column;gap:10px;}
        .lh-item{background:rgba(15,23,42,0.6);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.06);border-radius:14px;overflow:hidden;transition:border-color .2s;}
        .lh-item:hover{border-color:rgba(78,205,196,0.2);}
        .lh-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;cursor:pointer;user-select:none;}
        .lh-header h2{font-size:1rem;font-weight:700;color:#e2e8f0;margin:0;display:flex;align-items:center;gap:10px;}
        .lh-header h2 i{font-size:1.1rem;}
        .lh-chevron{color:#475569;font-size:0.8rem;transition:transform .3s;}
        .lh-item.open .lh-chevron{transform:rotate(180deg);}
        .lh-body{display:none;padding:0 22px 20px;}
        .lh-item.open .lh-body{display:block;}
        .lh-body p{color:#94a3b8;font-size:0.88rem;line-height:1.7;margin:0 0 12px;}
        .lh-body a{color:#4ECDC4;text-decoration:none;font-weight:600;}
        .lh-body a:hover{text-decoration:underline;}
        .lh-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:linear-gradient(135deg,#4ECDC4,#6366f1);color:#fff;border-radius:8px;text-decoration:none;font-size:0.85rem;font-weight:600;transition:opacity .2s;}
        .lh-btn:hover{opacity:.9;}
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="lh-wrap">
    <a href="main.php" class="lh-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="lh-hero">
        <h1><i class="fas fa-scale-balanced"></i> Policies & Legal</h1>
        <p>Review our legal documents governing the use of Spencer's Website</p>
    </div>

    <div class="lh-accordion">
        <!-- Privacy Policy -->
        <div class="lh-item" onclick="this.classList.toggle('open')">
            <div class="lh-header">
                <h2><i class="fas fa-shield-halved" style="color:#8b5cf6;"></i> Privacy Policy</h2>
                <i class="fas fa-chevron-down lh-chevron"></i>
            </div>
            <div class="lh-body">
                <p>Our Privacy Policy explains how we collect, use, and protect your personal data — including login information, device fingerprints, IP addresses for threat detection, AI chat histories, support ticket data, and Smail messages.</p>
                <p>We are committed to transparency about data practices and comply with applicable privacy regulations.</p>
                <a href="privacy.php" class="lh-btn"><i class="fas fa-external-link-alt"></i> Read Full Privacy Policy</a>
            </div>
        </div>

        <!-- Terms of Service -->
        <div class="lh-item" onclick="this.classList.toggle('open')">
            <div class="lh-header">
                <h2><i class="fas fa-file-contract" style="color:#4ECDC4;"></i> Terms of Service</h2>
                <i class="fas fa-chevron-down lh-chevron"></i>
            </div>
            <div class="lh-body">
                <p>Our Terms of Service outline the rules for using Spencer's Website — including account responsibilities, acceptable use policies, automated security measures (IP blocking, rate limiting), content moderation of Smail messages, and AI assistant usage guidelines.</p>
                <p>By using the site, you agree to these terms.</p>
                <a href="terms.php" class="lh-btn"><i class="fas fa-external-link-alt"></i> Read Full Terms of Service</a>
            </div>
        </div>

        <!-- Refund Policy -->
        <div class="lh-item" onclick="this.classList.toggle('open')">
            <div class="lh-header">
                <h2><i class="fas fa-money-bill-wave" style="color:#f59e0b;"></i> Refund Policy</h2>
                <i class="fas fa-chevron-down lh-chevron"></i>
            </div>
            <div class="lh-body">
                <p>Our 48-hour refund policy covers all paid subscriptions and one-time purchases made through Stripe. Refund requests must be submitted within 48 hours of the original transaction.</p>
                <p>Refunds are processed back to the original payment method within 5-10 business days.</p>
                <a href="refund-policy.php" class="lh-btn"><i class="fas fa-external-link-alt"></i> Read Full Refund Policy</a>
            </div>
        </div>

        <!-- Data & Security -->
        <div class="lh-item" onclick="this.classList.toggle('open')">
            <div class="lh-header">
                <h2><i class="fas fa-lock" style="color:#ef4444;"></i> Data & Security</h2>
                <i class="fas fa-chevron-down lh-chevron"></i>
            </div>
            <div class="lh-body">
                <p><strong>Live Threat Detection:</strong> We monitor login attempts and automatically block IP addresses that exhibit suspicious behavior (5+ failed logins in 10 minutes). Blocks expire after 30 minutes.</p>
                <p><strong>AI Chat Data:</strong> Conversations with the AI assistant are saved to your account for continuity. Admins may review chats for moderation purposes.</p>
                <p><strong>Support Tickets:</strong> All support ticket data is stored securely and accessible only to you and site administrators.</p>
                <p><strong>Encryption:</strong> All connections use HTTPS with HSTS enforcement. Database connections use utf8mb4 charset with SSL where available.</p>
            </div>
        </div>

        <!-- DMCA & Copyright -->
        <div class="lh-item" onclick="this.classList.toggle('open')">
            <div class="lh-header">
                <h2><i class="fas fa-copyright" style="color:#f59e0b;"></i> DMCA &amp; Copyright Policy</h2>
                <i class="fas fa-chevron-down lh-chevron"></i>
            </div>
            <div class="lh-body">
                <p>Our DMCA &amp; Copyright Policy outlines the procedures for reporting copyright infringement, submitting counter-notifications, and our repeat infringer policy — in compliance with the Digital Millennium Copyright Act.</p>
                <p>We respect the intellectual property rights of others and will respond to clear notices of alleged copyright infringement.</p>
                <a href="dmca.php" class="lh-btn"><i class="fas fa-external-link-alt"></i> Read Full DMCA Policy</a>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>

<?php
/**
 * Refund Policy — Spencer's Website v7.0
 * Redesigned 2026-04-18 with dark-futuristic layout.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Refund Policy',
    'subtitle'     => 'How to request a refund and what qualifies — 48-hour window applies.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'refunds',
    'toc'          => [
        ['id' => 'eligibility', 'label' => '1. Refund Eligibility'],
        ['id' => 'how-to',      'label' => '2. How to Request a Refund'],
        ['id' => 'processing',  'label' => '3. Refund Processing'],
        ['id' => 'partial',     'label' => '4. Partial Refunds'],
        ['id' => 'chargebacks', 'label' => '5. Chargebacks'],
        ['id' => 'contact',     'label' => '6. Contact'],
    ],
]);
?>

<p>This Refund Policy applies to all purchases made on Spencer's Website (<strong>thespencerwebsite.com</strong>). By making a purchase, you agree to this policy.</p>

<div class="critical-box">
    <strong>Key Point:</strong> All refund requests must be submitted within <strong>48 hours</strong> of your purchase. Requests submitted after 48 hours will be automatically denied by our system.
</div>

<!-- 1 -->
<section id="eligibility">
<h2>1. Refund Eligibility</h2>

<h3>1.1 The 48-Hour Refund Window</h3>
<p>You may request a refund for any membership purchase within <strong>48 hours</strong> of the payment being completed. This 48-hour window is enforced automatically.</p>

<h3>1.2 Eligible Purchases</h3>
<table>
    <thead>
        <tr><th>Plan Type</th><th>Refund Eligible?</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td>Monthly ($3/mo)</td><td>Yes, within 48 hours</td><td>Subscription is cancelled upon refund</td></tr>
        <tr><td>Yearly ($32/yr)</td><td>Yes, within 48 hours</td><td>Subscription is cancelled upon refund</td></tr>
        <tr><td>Lifetime ($100)</td><td>Yes, within 48 hours</td><td>Account downgraded to Community upon refund</td></tr>
        <tr><td>Donations</td><td>No</td><td>Donations are non-refundable</td></tr>
    </tbody>
</table>

<h3>1.3 Parental Consent</h3>
<p>Users under 18 must have parental or legal guardian consent to make purchases. Refunds for purchases made by minors without parental consent will be processed upon request, provided the request is within the 48-hour window. We reserve the right to verify parental consent before processing.</p>

<h3>1.4 Ineligible for Refund</h3>
<ul>
    <li>Requests submitted <strong>after 48 hours</strong> from the time of purchase</li>
    <li>Accounts that have been <strong>suspended or terminated</strong> for violating the <a href="terms.php">Terms of Service</a> or <a href="community-standards.php">Community Standards</a></li>
    <li><strong>Donations</strong> are non-refundable under any circumstances</li>
    <li>Accounts with a <strong>pending refund request</strong> already in the system</li>
    <li>Accounts with <strong>active strikes</strong> or currently in Time Removal (Tier 2 punishment)</li>
</ul>

<div class="warning-box">
    <strong>Terminated Accounts:</strong> If your account has been terminated under our <a href="terms.php#community-standards">Three-Tier Punishment System</a> (Tier 3: Account Termination), you are permanently ineligible for refunds. This includes accounts terminated for hate speech, gore/violent extremism, illegal activity, or repeated violations.
</div>
</section>

<!-- 2 -->
<section id="how-to">
<h2>2. How to Request a Refund</h2>
<ol>
    <li><strong>Log in</strong> to your account</li>
    <li>Navigate to <strong>Subscription Management</strong> (accessible from your User Panel)</li>
    <li>Click <strong>"Request Refund"</strong></li>
    <li>Select a reason for your refund and provide feedback (minimum 20 characters)</li>
    <li>Submit the request</li>
</ol>
<p>Your request will be reviewed by an administrator. You will be notified of the outcome via Smail.</p>

<div class="warning-box">
    <strong>Important:</strong> You may only have one pending refund request at a time. If your request is denied, you may submit a new one (provided you are still within the 48-hour window).
</div>
</section>

<!-- 3 -->
<section id="processing">
<h2>3. Refund Processing</h2>

<h3>3.1 Review Process</h3>
<p>All refund requests are manually reviewed by an administrator. Typical review time is <strong>1–3 business days</strong>.</p>

<h3>3.2 Approved Refunds</h3>
<p>If your refund is approved:</p>
<ul>
    <li>The refund is processed through <strong>Stripe</strong> back to your original payment method</li>
    <li>Refunds typically appear on your statement within <strong>5–10 business days</strong>, depending on your bank</li>
    <li>Your account is <strong>downgraded to Community</strong> tier immediately</li>
    <li>Any active subscription is <strong>cancelled</strong></li>
    <li>You lose access to premium features (custom backgrounds, AI assistant, chat tags, server-synced settings)</li>
</ul>

<h3>3.3 Denied Refunds</h3>
<p>If your refund is denied, the administrator may provide a reason. Common denial reasons include:</p>
<ul>
    <li>Request submitted outside the 48-hour window</li>
    <li>Evidence of abuse or Terms of Service violations</li>
    <li>Duplicate or fraudulent refund requests</li>
</ul>
</section>

<!-- 4 -->
<section id="partial">
<h2>4. Partial Refunds</h2>
<p>We do not issue partial refunds. If you cancel a monthly or yearly subscription mid-cycle, you retain access until the end of the current billing period. No prorated refund is issued for unused time.</p>
</section>

<!-- 5 -->
<section id="chargebacks">
<h2>5. Chargebacks</h2>
<p>If you initiate a chargeback (dispute) with your bank instead of using our refund process:</p>
<ul>
    <li>Your account will be <strong>immediately and permanently suspended</strong></li>
    <li>Your device fingerprint may be <strong>banned</strong>, preventing future account creation</li>
    <li>We reserve the right to contest the chargeback with evidence of the transaction</li>
</ul>
<p>We strongly encourage you to use the in-site refund process before contacting your bank.</p>
</section>

<!-- 6 -->
<section id="contact">
<h2>6. Contact</h2>
<p>If you have questions about this Refund Policy or need assistance with a refund, contact us:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

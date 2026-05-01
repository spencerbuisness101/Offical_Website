<?php
/**
 * Children's Privacy Policy — Spencer's Website v7.0
 * COPPA-compliant policy for users under 13 and Verifiable Parental Consent (VPC) flow.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Children\'s Privacy Policy',
    'subtitle'     => 'How we protect users under 18 in compliance with COPPA and GDPR-K.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'children',
    'toc'          => [
        ['id' => 'age-requirements', 'label' => '1. Age Requirements'],
        ['id' => 'under-13',         'label' => '2. Users Under 13'],
        ['id' => 'teen-consent',     'label' => '3. Users 13–17 (VPC)'],
        ['id' => 'parent-rights',    'label' => '4. Parent/Guardian Rights'],
        ['id' => 'data-collected',   'label' => '5. Data We Collect From Minors'],
        ['id' => 'safety',           'label' => '6. Safety Features'],
        ['id' => 'contact',          'label' => '7. Contact'],
    ],
]);
?>

<p>This Children's Privacy Policy describes how Spencer's Website handles personal information from users under the age of 18, in compliance with the U.S. Children's Online Privacy Protection Act (COPPA) and the EU General Data Protection Regulation for Kids (GDPR-K).</p>

<div class="info-box">
    <strong>If you are a parent or legal guardian</strong> and wish to review, modify, or delete your child's data, see <a href="#parent-rights">Section 4</a> or contact us at <span class="contact-email">spencerbuisness101@gmail.com</span>.
</div>

<section id="age-requirements">
<h2>1. Age Requirements</h2>
<p>Our Service has the following minimum-age requirements:</p>
<table>
    <thead><tr><th>Age</th><th>Access</th><th>Requirements</th></tr></thead>
    <tbody>
        <tr><td>Under 13</td><td>Not permitted</td><td>We do not knowingly collect data from children under 13</td></tr>
        <tr><td>13–17</td><td>Permitted with parental consent</td><td>Verifiable Parental Consent (VPC) required for paid accounts &amp; full features</td></tr>
        <tr><td>18+</td><td>Permitted</td><td>Standard Terms of Service apply</td></tr>
    </tbody>
</table>
</section>

<section id="under-13">
<h2>2. Users Under 13</h2>
<p>We do <strong>not knowingly</strong> collect personal information from children under 13. If we discover that we have collected data from a child under 13 without verified parental consent, we will promptly:</p>
<ul>
    <li>Delete all personal information from our systems</li>
    <li>Terminate the account</li>
    <li>Notify the parent/guardian if contact info is available</li>
</ul>
<p>If you believe we have inadvertently collected data from a child under 13, please contact us immediately at <span class="contact-email">spencerbuisness101@gmail.com</span>.</p>
</section>

<section id="teen-consent">
<h2>3. Users 13–17 (Verifiable Parental Consent)</h2>
<p>Users between 13 and 17 may use our free Community features, but the following activities require <strong>Verifiable Parental Consent (VPC)</strong>:</p>
<ul>
    <li>Making any purchase (monthly, yearly, or lifetime membership)</li>
    <li>Accessing paid features (AI Assistant, custom backgrounds, full game library)</li>
    <li>Providing additional personal information beyond username and email</li>
</ul>

<h3>3.1 VPC Process</h3>
<p>When a minor attempts a restricted action, they are directed to the Parent Consent Portal (<code>auth/parent_consent_portal.php</code>). Our VPC process is:</p>
<ol>
    <li>Minor provides parent's email address</li>
    <li>System generates unique consent token and emails parent</li>
    <li>Parent reviews data-collection practices and our policies</li>
    <li>Parent verifies identity via Stripe PaymentIntent of <strong>$1.00</strong> (industry-standard VPC method) — this is refunded after verification</li>
    <li>Consent is recorded with verification code hash, payment intent ID, and timestamp</li>
    <li>Minor's account is unlocked for the approved activities</li>
</ol>

<h3>3.2 VPC Records</h3>
<p>We retain consent records (verification hash, payment intent ID, timestamp) for <strong>3 years</strong> after the minor reaches age 18, as required by COPPA Safe Harbor provisions.</p>
</section>

<section id="parent-rights">
<h2>4. Parent/Guardian Rights</h2>
<p>Parents and legal guardians have the right to:</p>
<ul>
    <li><strong>Review</strong> their child's personal information collected by us</li>
    <li><strong>Refuse</strong> further collection of their child's data</li>
    <li><strong>Delete</strong> their child's personal information</li>
    <li><strong>Revoke</strong> previously granted consent at any time (see <code>auth/revoke_consent.php</code>)</li>
    <li><strong>Request an accounting</strong> of data shared with third parties (Stripe, Groq, Google)</li>
</ul>

<h3>4.1 How to Exercise These Rights</h3>
<ol>
    <li>Email us at <span class="contact-email">spencerbuisness101@gmail.com</span> with subject "Parent/Guardian Data Request"</li>
    <li>Include your child's username and a copy of the consent confirmation email</li>
    <li>We will verify your identity (e.g., matching email that granted consent) before taking action</li>
    <li>We respond within 10 business days</li>
</ol>

<h3>4.2 Revoking Consent</h3>
<p>When consent is revoked, the minor's account is reverted to the free Community tier. Any active subscriptions are cancelled and prorated refunds may be issued at admin discretion. Account deletion can also be requested as part of revocation.</p>
</section>

<section id="data-collected">
<h2>5. Data We Collect From Minors (13–17)</h2>
<p>For users aged 13–17 who use free Community features, we collect:</p>
<ul>
    <li>Username (chosen by the user)</li>
    <li>Email address (required for password recovery &amp; security alerts)</li>
    <li>Hashed password (Argon2id; we cannot read it)</li>
    <li>Login timestamps, IP address, and device fingerprint (for security)</li>
    <li>Chat messages and AI conversations (if used)</li>
</ul>
<p>For users 13–17 with parental consent and a paid plan, we additionally collect:</p>
<ul>
    <li>Payment records via Stripe (card data never touches our servers)</li>
    <li>Parental consent records (verification hash, payment intent ID)</li>
    <li>Subscription tier and billing history</li>
</ul>
<div class="warning-box">
    We never collect: Social Security numbers, precise geolocation, government IDs, biometric data, or data from third-party social sign-ins beyond what Google OAuth provides.
</div>
</section>

<section id="safety">
<h2>6. Safety Features for Minors</h2>
<ul>
    <li><strong>No direct messaging from strangers:</strong> Smail requires both parties to have a paid account</li>
    <li><strong>Automatic content filters:</strong> NSFW, hate speech, and violent content trigger immediate lockdown</li>
    <li><strong>Admin oversight of AI chat:</strong> AI conversations involving minors receive enhanced monitoring</li>
    <li><strong>No location sharing:</strong> We do not collect or display precise geolocation</li>
    <li><strong>Report button:</strong> Minors (and all users) can report inappropriate content for 72-hour admin review</li>
    <li><strong>Bulk delete on request:</strong> Parents can request full deletion of their child's data</li>
</ul>
</section>

<section id="contact">
<h2>7. Contact</h2>
<p>For any questions or concerns about your child's privacy:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
<p><strong>Subject line:</strong> "Children's Privacy Inquiry"</p>
</section>

<?php render_policy_footer(); ?>

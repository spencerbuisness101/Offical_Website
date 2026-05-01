<?php
/**
 * Terms of Service — Spencer's Website v7.0
 * Redesigned 2026-04-18 with dark-futuristic layout.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Terms of Service',
    'subtitle'     => 'The agreement between you and Spencer\'s Website.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'terms',
    'toc'          => [
        ['id' => 'eligibility',          'label' => '1. Eligibility'],
        ['id' => 'accounts',             'label' => '2. Accounts & Registration'],
        ['id' => 'memberships',          'label' => '3. Memberships & Payments'],
        ['id' => 'community-standards',  'label' => '4. Community Standards'],
        ['id' => 'content',              'label' => '5. Content & IP'],
        ['id' => 'third-party',          'label' => '6. Third-Party Content'],
        ['id' => 'ai',                   'label' => '7. AI Assistant Usage'],
        ['id' => 'termination',          'label' => '8. Termination'],
        ['id' => 'disclaimers',          'label' => '9. Disclaimers'],
        ['id' => 'liability',            'label' => '10. Limitation of Liability'],
        ['id' => 'changes',              'label' => '11. Changes to Terms'],
        ['id' => 'contact',              'label' => '12. Contact'],
    ],
]);
?>

<p>Welcome to Spencer's Website. By accessing or using our Service at <strong>thespencerwebsite.com</strong> or <strong>thespencergamingwebsite.com</strong>, you agree to be bound by these Terms of Service ("Terms"). If you disagree with any part of these Terms, you may not use the Service.</p>

<div class="info-box">
    <strong>Related policies:</strong>
    <a href="privacy.php">Privacy Policy</a> · <a href="acceptable-use.php">Acceptable Use Policy</a> · <a href="refund-policy.php">Refund Policy</a> · <a href="dmca.php">DMCA Policy</a> · <a href="community-standards.php">Community Standards</a>
</div>

<!-- 1 -->
<section id="eligibility">
<h2>1. Eligibility</h2>
<p>You must be at least <strong>13 years of age</strong> to use this Service. If you are under 18, you must have the consent of a parent or legal guardian, as outlined in our <a href="childrens-privacy.php">Children's Privacy Policy</a>.</p>
<p>By using the Service, you represent that you meet these requirements and that all information you provide is accurate.</p>
</section>

<!-- 2 -->
<section id="accounts">
<h2>2. Accounts &amp; Registration</h2>
<ul>
    <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
    <li>You agree to provide accurate information during registration.</li>
    <li>You may not share your account with others. Our security system tracks device fingerprints and may flag or suspend accounts exhibiting suspicious sharing behavior.</li>
    <li>Guest accounts (auto-created temporary Community accounts) are limited to 3 per IP address per 30 days.</li>
    <li>You are responsible for all activity that occurs under your account.</li>
    <li>You must notify the administrator immediately if you suspect unauthorized access.</li>
</ul>
</section>

<!-- 3 -->
<section id="memberships">
<h2>3. Memberships &amp; Payments</h2>

<h3>3.1 Membership Tiers</h3>
<table>
    <thead><tr><th>Tier</th><th>Price</th><th>Billing</th></tr></thead>
    <tbody>
        <tr><td>Community</td><td>Free</td><td>No billing (guest or registered free accounts)</td></tr>
        <tr><td>Premium Monthly</td><td>$3.00/month</td><td>Recurring monthly</td></tr>
        <tr><td>Premium Yearly</td><td>$32.00/year</td><td>Recurring annual (saves 11% vs. monthly)</td></tr>
        <tr><td>Lifetime</td><td>$100.00</td><td>One-time, permanent access</td></tr>
    </tbody>
</table>

<h3>3.2 Payment Processing</h3>
<p>All payments are processed by <strong>Stripe, Inc.</strong> By making a purchase, you agree to <a href="https://stripe.com/legal" target="_blank" rel="noopener">Stripe's Terms of Service</a>. We do not store your credit card information on our servers.</p>

<h3>3.3 Refunds</h3>
<p>Refunds are subject to our <a href="refund-policy.php">Refund Policy</a>. In summary, refund requests must be submitted within <strong>48 hours</strong> of purchase.</p>

<h3>3.4 Subscription Cancellation</h3>
<p>Monthly and yearly subscriptions may be cancelled at any time from the Subscription Management page. Cancellation takes effect at the end of the current billing period. No partial refunds are issued for unused time within a paid period.</p>

<h3>3.5 Suspension for Non-Payment</h3>
<p>If a recurring subscription payment fails and is not resolved within 3 days, your account may be suspended. Access is restored upon successful payment or admin intervention.</p>

<h3>3.6 Parental Consent for Purchases</h3>
<p>Users under 18 must have parental or legal guardian consent before making any purchase. By completing a transaction, you confirm that you are 18 years or older, or have obtained such consent. We reserve the right to request proof of parental consent and refund any purchase made by a minor without such consent.</p>

<h3>3.7 Transaction IP Logging</h3>
<p>Your IP address is logged during all payment transactions for fraud detection via our Live Threat Detector system. Logged IP data is retained for 90 days. See <a href="privacy.php#info-collect">Privacy Policy</a>.</p>
</section>

<!-- 4 -->
<section id="community-standards">
<h2>4. Community Standards &amp; Enforcement</h2>
<p>To maintain a safe and respectful environment, all users must adhere to these standards. Violations are tracked via a strike system administered by the <strong>SYSTEM</strong> account (automated moderation). Full rules are in our <a href="community-standards.php">Community Standards</a> document.</p>

<h3>4.1 The SYSTEM Account</h3>
<div class="info-box">
    <strong>SYSTEM</strong> is an automated moderation account (User ID: 0) that handles enforcement.
    <ul>
        <li>SYSTEM cannot receive messages, emails, or replies</li>
        <li>All violation notices come from SYSTEM with reason, evidence, and strike count</li>
        <li>Strike count resets after 30 days of clean behavior</li>
    </ul>
</div>

<h3>4.2 Three-Tier Punishment System</h3>
<table>
    <thead><tr><th>Tier</th><th>Name</th><th>Action</th><th>Duration</th></tr></thead>
    <tbody>
        <tr><td>1</td><td>Verbal Warning</td><td>Strike recorded, account fully active</td><td>Permanent record (resets after 30 days)</td></tr>
        <tr><td>2</td><td>Time Removal</td><td>Posting/viewing privileges frozen</td><td>3–14 days (admin discretion)</td></tr>
        <tr><td>3</td><td>Account Termination</td><td>Permanent ban, data retained for security</td><td>Permanent</td></tr>
    </tbody>
</table>

<h3>4.3 Appeals</h3>
<p>Users may appeal strikes/terminations within 14 days via the Smail system. Appeals are reviewed by human moderators. Decisions are final after second review.</p>
</section>

<!-- 5 -->
<section id="content">
<h2>5. Content &amp; Intellectual Property</h2>

<h3>5.1 User-Generated Content</h3>
<p>By posting content on the Service (chat messages, feedback, ideas, background submissions, profile data), you grant us a non-exclusive, royalty-free license to display and use that content within the Service. You retain ownership of your content.</p>

<h3>5.2 Site Content</h3>
<p>The design, code, text, and original content of this website are the property of Spencer's Website. You may not copy, modify, distribute, or reverse-engineer any part of the Service without written permission.</p>

<h3>5.3 Third-Party Game Content</h3>
<p>Games hosted on this site are embedded from third-party sources and remain the intellectual property of their respective creators. We do not claim ownership of any embedded game content.</p>

<h3>5.4 Copyright Infringement &amp; DMCA</h3>
<p>If you believe that your copyrighted work has been copied and is accessible on this Service in a way that constitutes copyright infringement, see our <a href="dmca.php">DMCA &amp; Copyright Policy</a>.</p>
</section>

<!-- 6 -->
<section id="third-party">
<h2>6. Third-Party Content &amp; Games</h2>
<p>The Service embeds games and content from external providers via iframes. These third-party sites operate independently and have their own terms and privacy policies. We are not responsible for:</p>
<ul>
    <li>The availability, accuracy, or content of embedded games</li>
    <li>Any data collected by embedded game providers</li>
    <li>Any harm resulting from interaction with embedded third-party content</li>
</ul>
<p>Use of embedded games is at your own risk.</p>
</section>

<!-- 7 -->
<section id="ai">
<h2>7. AI Assistant Usage</h2>
<ul>
    <li>The AI Assistant is provided for entertainment and informational purposes only. It is <strong>not</strong> a substitute for professional advice (medical, legal, financial, or otherwise).</li>
    <li>AI responses are generated by third-party language models (Groq) and may contain inaccuracies.</li>
    <li>We reserve the right to monitor, log, and review AI conversations for safety and moderation.</li>
    <li>You agree not to use the AI Assistant to generate illegal, harmful, or deceptive content.</li>
    <li>AI Assistant access requires a paid membership (Premium tier or above).</li>
</ul>
</section>

<!-- 8 -->
<section id="termination">
<h2>8. Termination &amp; Suspension</h2>
<p>We reserve the right to suspend or terminate your account at any time, with or without notice, for:</p>
<ul>
    <li>Violations of these Terms or the <a href="acceptable-use.php">Acceptable Use Policy</a></li>
    <li>Fraudulent or suspicious activity</li>
    <li>Non-payment of subscription fees</li>
    <li>Any other reason at the sole discretion of the administrator</li>
</ul>
<p>Upon termination, you lose access to all premium features and content associated with your account. Refunds for terminated accounts are handled at the administrator's discretion.</p>
</section>

<!-- 9 -->
<section id="disclaimers">
<h2>9. Disclaimers</h2>
<p>The Service is provided <strong>"as is"</strong> and <strong>"as available"</strong> without warranties of any kind, either express or implied, including implied warranties of merchantability, fitness for a particular purpose, and non-infringement.</p>
<p>We do not guarantee that:</p>
<ul>
    <li>The Service will be uninterrupted, secure, or error-free</li>
    <li>Any embedded third-party content will be available or functional</li>
    <li>AI-generated responses will be accurate, complete, or appropriate</li>
</ul>
</section>

<!-- 10 -->
<section id="liability">
<h2>10. Limitation of Liability</h2>
<p>To the maximum extent permitted by applicable law, Spencer's Website and its operator(s) shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenues, whether incurred directly or indirectly, or any loss of data, use, goodwill, or other intangible losses, resulting from:</p>
<ul>
    <li>Your use or inability to use the Service</li>
    <li>Any unauthorized access to or use of our servers and/or any personal information stored therein</li>
    <li>Any interruption or cessation of transmission to or from the Service</li>
    <li>Any third-party content accessed through the Service</li>
</ul>
<p>Our total aggregate liability for any claims arising from or related to the Service shall not exceed the amount you paid us in the 12 months preceding the claim.</p>
</section>

<!-- 11 -->
<section id="changes">
<h2>11. Changes to Terms</h2>
<p>We may update these Terms from time to time. Material changes will be communicated via a site announcement. Continued use of the Service after changes constitutes acceptance of the revised Terms.</p>
</section>

<!-- 12 -->
<section id="contact">
<h2>12. Contact</h2>
<p>For questions about these Terms, contact us:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

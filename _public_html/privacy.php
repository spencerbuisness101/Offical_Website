<?php
/**
 * Privacy Policy — Spencer's Website v7.0
 * Redesigned 2026-04-18 with dark-futuristic layout.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Privacy Policy',
    'subtitle'     => 'How we collect, use, and protect your personal information.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'privacy',
    'toc'          => [
        ['id' => 'info-collect',   'label' => '1. Information We Collect'],
        ['id' => 'how-use',        'label' => '2. How We Use Your Information'],
        ['id' => 'third-party',    'label' => '3. Third-Party Services'],
        ['id' => 'data-retention', 'label' => '4. Data Retention'],
        ['id' => 'data-security',  'label' => '5. Data Security'],
        ['id' => 'your-rights',    'label' => '6. Your Rights (GDPR/CCPA)'],
        ['id' => 'children',       'label' => '7. Children\'s Privacy'],
        ['id' => 'changes',        'label' => '8. Changes to This Policy'],
        ['id' => 'contact',        'label' => '9. Contact Us'],
    ],
]);
?>

<p>Spencer's Website ("we," "us," or "our") operates <strong>thespencerwebsite.com</strong> and <strong>thespencergamingwebsite.com</strong>. This Privacy Policy explains what information we collect, how we use it, and your rights regarding your data.</p>

<div class="info-box">
    <strong>Quick summary:</strong> We collect only what we need to run the site (accounts, payments, security telemetry, site usage). We never sell your data. Your password is irreversibly hashed — even we can't read it. Payment card info never touches our servers.
</div>

<!-- 1. Information We Collect -->
<section id="info-collect">
<h2>1. Information We Collect</h2>

<h3>1.1 Account Information</h3>
<p>When you create an account, we collect:</p>
<ul>
    <li><strong>Username</strong> — chosen by you during registration</li>
    <li><strong>Email address</strong> — optional for community accounts; required for paid accounts (used for password resets &amp; security alerts)</li>
    <li><strong>Password</strong> — stored as a one-way cryptographic hash (Argon2id); we cannot read your password</li>
    <li><strong>Account role</strong> — your membership tier (Community, User, Contributor, Designer, Admin)</li>
    <li><strong>Registration date and last login timestamp</strong></li>
</ul>

<h3>1.2 Guest Accounts</h3>
<p>Guest accounts are temporary passwordless accounts created with a single click for the free Community tier. We store:</p>
<ul>
    <li>Auto-generated guest username (e.g. <code>Guest_123456</code>)</li>
    <li>Hashed IP address (for rate-limiting — max 3 guest accounts per IP per 30 days)</li>
    <li>Creation timestamp</li>
</ul>
<p>Guest accounts have no email and cannot be recovered if lost. Guest accounts are excluded from public "member count" displays.</p>

<h3>1.3 Payment Information</h3>
<p>We use <strong>Stripe</strong> as our payment processor. When you purchase a membership:</p>
<ul>
    <li>Your credit card details are handled <strong>entirely by Stripe</strong> and never touch our servers</li>
    <li>We store: payment status, plan type (monthly/yearly/lifetime), transaction timestamps, and Stripe session/subscription IDs</li>
    <li>We do <strong>not</strong> store your card number, CVV, or billing address</li>
    <li>Your <strong>IP address</strong> is logged at the time of each transaction for fraud prevention</li>
</ul>

<h3>1.4 Device &amp; Browser Information</h3>
<p>For security and fraud prevention, we collect device fingerprint data, including:</p>
<table>
    <thead><tr><th>Data Point</th><th>Purpose</th></tr></thead>
    <tbody>
        <tr><td>IP address</td><td>Rate limiting, fraud detection, session tracking</td></tr>
        <tr><td>Browser user agent</td><td>Session validation, analytics</td></tr>
        <tr><td>Screen resolution</td><td>Device fingerprinting for fraud prevention</td></tr>
        <tr><td>GPU/WebGL renderer</td><td>Device fingerprinting for fraud prevention</td></tr>
        <tr><td>Canvas fingerprint hash</td><td>Device fingerprinting for fraud prevention</td></tr>
        <tr><td>Installed fonts hash</td><td>Device fingerprinting for fraud prevention</td></tr>
        <tr><td>Timezone &amp; language</td><td>Device fingerprinting, localization</td></tr>
        <tr><td>Platform (OS)</td><td>Device fingerprinting for fraud prevention</td></tr>
    </tbody>
</table>
<p>This data is used to detect account sharing, prevent unauthorized access, and identify banned users who attempt to create new accounts.</p>

<h3>1.5 Usage Data</h3>
<p>We automatically collect:</p>
<ul>
    <li><strong>Page views</strong> — which pages you visit and when</li>
    <li><strong>Session data</strong> — session ID, current page, page view count, session duration</li>
    <li><strong>Game analytics</strong> — which games you play and for how long</li>
    <li><strong>Feature usage</strong> — which site features you interact with</li>
    <li><strong>Performance metrics</strong> — page load times</li>
</ul>

<h3>1.6 AI Chat Data</h3>
<p>If you use the AI Assistant panel, we store your messages, the AI's responses, the AI persona selected, and conversation timestamps. AI conversations are processed by <strong>Groq</strong> (third-party AI provider).</p>
<div class="info-box">
    <strong>AI Conversation Logging:</strong> All AI conversations are logged and retained for 90 days. Logs may be reviewed by administrators for safety, moderation, and Acceptable Use Policy enforcement. After 90 days, logs are automatically purged.
</div>

<h3>1.7 Chat Messages (Yaps)</h3>
<p>Messages sent in the Yaps chat system are stored with your username, role, and timestamp. Yaps messages are visible to other logged-in users.</p>

<h3>1.8 Smail (Internal Messages)</h3>
<p>If you use the Smail internal messaging system, we store message sender/recipient IDs, title and body content, color/urgency settings, read status, and timestamps. <strong>Smail messages are private between sender and recipient, but administrators may access them for moderation and safety.</strong> Community-role accounts cannot use Smail.</p>

<h3>1.9 Profile Information</h3>
<p>If you create or edit your user profile, we collect: nickname, description (max 500 chars), about section (max 2000 chars), and profile picture URL or uploaded image. <strong>Profile pictures require admin approval before being visible</strong>; submitted images are retained in administrative records, including declined images.</p>

<h3>1.10 Live Threat Detection &amp; IP Tracking</h3>
<p>To protect the security of all users, we implement automated threat detection that monitors failed login attempts, rate-limiting data, and automated IP blocking. IPs with 5+ failed login attempts within 10 minutes are temporarily blocked for 30 minutes.</p>

<h3>1.11 Google reCAPTCHA v3</h3>
<p>We use Google reCAPTCHA v3 (invisible) on forms to protect against bots. reCAPTCHA collects hardware and software information (device and application data) and sends this information to Google for analysis. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.</p>

<h3>1.12 Password Security</h3>
<div class="success-box">
    <strong>We cannot see your password.</strong> All passwords are hashed using Argon2id (memory_cost=65536, time_cost=4, threads=3). Passwords are never stored in plain text. Even administrators cannot retrieve or view your password.
</div>
</section>

<!-- 2. How We Use Your Information -->
<section id="how-use">
<h2>2. How We Use Your Information</h2>
<p>We use your information to:</p>
<ul>
    <li>Provide and maintain your account and membership</li>
    <li>Process payments and manage subscriptions</li>
    <li>Detect and prevent fraud, abuse, and unauthorized access</li>
    <li>Enforce our <a href="terms.php">Terms of Service</a> and <a href="acceptable-use.php">Acceptable Use Policy</a></li>
    <li>Improve site performance and user experience</li>
    <li>Respond to support requests</li>
    <li>Send critical account notifications (e.g., suspension, subscription expiry, new device login)</li>
</ul>
<p><strong>We do not sell, rent, or share your personal information with third parties for marketing purposes.</strong></p>

<h3>2.1 Administrator Monitoring</h3>
<p>Site administrators have access to the following data for security, moderation, and site management:</p>
<ul>
    <li><strong>Login history</strong> — username, last login, IP address, account status</li>
    <li><strong>Page visit analytics</strong> — aggregated into top-visited-pages reports</li>
    <li><strong>Device fingerprints</strong> — for fraud detection</li>
    <li><strong>AI chat conversations</strong> — for safety and moderation</li>
    <li><strong>Smail messages</strong> — for moderation</li>
    <li><strong>Support tickets &amp; game reports</strong></li>
    <li><strong>Data clearing</strong> — admins may periodically clear aggregated analytics data</li>
</ul>
<p>All administrative access to user data is logged in an audit trail (<code>admin_access_log</code>), including the administrator's identity, the action performed, and the timestamp.</p>
</section>

<!-- 3. Third-Party Services -->
<section id="third-party">
<h2>3. Third-Party Services &amp; Embedded Content</h2>

<h3>3.1 Embedded Games (Iframes)</h3>
<p>Our game pages embed third-party HTML games via <code>&lt;iframe&gt;</code> elements. These games are hosted on external servers and may independently collect data. We do not control their privacy practices.</p>

<h3>3.2 CDN Resources</h3>
<p>We load static resources (fonts, icons, JS libraries) from:</p>
<ul>
    <li><strong>cdnjs.cloudflare.com</strong> — Font Awesome icons, JS libraries</li>
    <li><strong>cdn.jsdelivr.net</strong> — JavaScript libraries</li>
    <li><strong>fonts.googleapis.com / fonts.gstatic.com</strong> — Web fonts</li>
    <li><strong>www.google.com / www.gstatic.com</strong> — Google reCAPTCHA v3</li>
</ul>
<p>These CDN providers may log your IP address and browser information when serving resources. Their respective privacy policies apply.</p>

<h3>3.3 Groq AI API</h3>
<p>AI chat messages are sent to <strong>Groq</strong> for processing. See <a href="https://groq.com/privacy-policy/" target="_blank" rel="noopener">Groq's Privacy Policy</a>.</p>

<h3>3.4 Stripe Payment Processing</h3>
<p>All payment processing is handled by <strong>Stripe, Inc.</strong> according to <a href="https://stripe.com/privacy" target="_blank" rel="noopener">Stripe's Privacy Policy</a> and PCI-DSS standards.</p>

<h3>3.5 Google OAuth</h3>
<p>If you sign in with Google, Google provides us with your email, name, Google ID, and email-verified status. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.</p>

<p>For detailed information about cookies and local storage, see our <a href="cookie-policy.php">Cookie Policy</a>.</p>
</section>

<!-- 4. Data Retention -->
<section id="data-retention">
<h2>4. Data Retention</h2>

<h3>4.1 User-Requested Account Deletion (Soft Deletion)</h3>
<p>Users may request account deletion via Settings &gt; Privacy &gt; Delete Account:</p>
<ul>
    <li><strong>Immediate effect:</strong> Account disabled, username changed to "[Deleted User]"</li>
    <li><strong>30-day grace period:</strong> Account can be reactivated by logging in</li>
    <li><strong>After 30 days:</strong> Personal data (email, IP logs, messages, Smail) purged</li>
    <li><strong>Retained data:</strong> Public posts remain visible with "[Deleted User]" attribution</li>
</ul>

<h3>4.2 Terminated Account Retention</h3>
<p>Accounts terminated for rule violations retain email hash, IP hash, device fingerprint, and strike history indefinitely for ban-evasion prevention. This falls under GDPR "Legitimate Interest" exemption.</p>

<h3>4.3 Standard Data Retention Table</h3>
<table>
    <thead><tr><th>Data Type</th><th>Retention Period</th></tr></thead>
    <tbody>
        <tr><td>Account data (username, role, settings)</td><td>Until account deletion or termination</td></tr>
        <tr><td>AI conversation logs</td><td>90 days, then auto-purged</td></tr>
        <tr><td>Login history</td><td>90 days, then auto-deleted</td></tr>
        <tr><td>Strike records</td><td>30 days after last strike (retained for terminated accounts)</td></tr>
        <tr><td>Payment sessions (completed)</td><td>Indefinitely (for records)</td></tr>
        <tr><td>Payment sessions (failed/expired)</td><td>7 days</td></tr>
        <tr><td>Payment nonces</td><td>Auto-deleted after expiry (15 minutes)</td></tr>
        <tr><td>Webhook events (processed)</td><td>90 days</td></tr>
        <tr><td>Page views &amp; analytics</td><td>Indefinitely (admin can clear)</td></tr>
        <tr><td>Device fingerprints</td><td>Until manually removed by admin</td></tr>
        <tr><td>Yaps chat messages</td><td>Indefinitely</td></tr>
        <tr><td>Smail messages</td><td>Until manually deleted by user or admin</td></tr>
        <tr><td>Rate limit records</td><td>Automatically cleaned</td></tr>
        <tr><td>Session data</td><td>Until browser session ends</td></tr>
        <tr><td>Guest account IP hashes</td><td>30 days (for rate limiting)</td></tr>
    </tbody>
</table>
</section>

<!-- 5. Data Security -->
<section id="data-security">
<h2>5. Data Security</h2>
<p>We implement the following security measures:</p>
<ul>
    <li><strong>Encryption in transit</strong> — All connections are forced over HTTPS with HSTS</li>
    <li><strong>Password hashing</strong> — Argon2id (industry standard)</li>
    <li><strong>CSRF protection</strong> — Session-based tokens on all forms and state-changing requests</li>
    <li><strong>Rate limiting</strong> — IP-based and session-based rate limiting on sensitive endpoints</li>
    <li><strong>Input sanitization</strong> — All user inputs sanitized to prevent XSS/SQL injection</li>
    <li><strong>Prepared statements</strong> — All database queries use PDO prepared statements</li>
    <li><strong>Content Security Policy</strong> — CSP headers restrict script and resource loading</li>
    <li><strong>Account lockout</strong> — Automatic lockout after 5 failed login attempts</li>
    <li><strong>Google reCAPTCHA v3</strong> — Invisible bot detection on all form submissions</li>
    <li><strong>Device fingerprint binding</strong> — Sessions bound to device fingerprint on login</li>
</ul>
</section>

<!-- 6. Your Rights -->
<section id="your-rights">
<h2>6. Your Rights (GDPR/CCPA)</h2>
<p>You have the right to:</p>
<ul>
    <li><strong>Access your data</strong> — Export your settings from the Settings page</li>
    <li><strong>Rectify inaccurate data</strong> — Update your profile and account details</li>
    <li><strong>Delete your data</strong> — Clear your browsing data or request full account deletion</li>
    <li><strong>Restrict processing</strong> — Request limits on how we use your data</li>
    <li><strong>Data portability</strong> — Receive your data in a machine-readable format</li>
    <li><strong>Object to processing</strong> — Opt out of non-essential data processing</li>
    <li><strong>Opt out of tracking</strong> — Device fingerprinting is used for security and cannot be individually disabled, but requesting account deletion removes all stored fingerprint data</li>
</ul>

<h3>6.1 Right to Appeal Automated Decisions (GDPR Article 22)</h3>
<p>Some account actions may be taken automatically based on rule violations. You have the right to:</p>
<ul>
    <li><strong>Obtain human intervention</strong> — Request that a human moderator review automated decisions</li>
    <li><strong>Express your point of view</strong> — Submit an appeal explaining your perspective</li>
    <li><strong>Contest the decision</strong> — Dispute strikes or terminations you believe were applied in error</li>
</ul>
<p>To appeal an automated decision, submit a Smail message to the administrator within <strong>14 days</strong> of the action. Appeals are reviewed by human moderators within 72 hours. Decisions after the second review are final.</p>
<p>To exercise any of these rights, contact us at <span class="contact-email">spencerbuisness101@gmail.com</span>.</p>
</section>

<!-- 7. Children's Privacy -->
<section id="children">
<h2>7. Children's Privacy</h2>
<p>Our service is not directed to children under 13. For users between 13–17, we implement Verifiable Parental Consent (VPC) per COPPA requirements. See our <a href="childrens-privacy.php">Children's Privacy Policy</a> for full details.</p>
<p>If we learn we have collected data from a child under 13 without verified parental consent, we will promptly delete it.</p>
</section>

<!-- 8. Changes -->
<section id="changes">
<h2>8. Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. Material changes will be posted on this page with an updated "Last Updated" date and, where applicable, communicated via Smail notification. Continued use of the site after changes constitutes acceptance of the updated policy.</p>
</section>

<!-- 9. Contact -->
<section id="contact">
<h2>9. Contact Us</h2>
<p>If you have questions about this Privacy Policy or wish to exercise any of your rights, contact us:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

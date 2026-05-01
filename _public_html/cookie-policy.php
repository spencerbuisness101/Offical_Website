<?php
/**
 * Cookie Policy — Spencer's Website v7.0
 * New policy page created 2026-04-18 (extracted from privacy.php for GDPR best practice).
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Cookie Policy',
    'subtitle'     => 'What cookies and local storage we use, and why.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'cookies',
    'toc'          => [
        ['id' => 'what-is',       'label' => '1. What Are Cookies?'],
        ['id' => 'our-cookies',   'label' => '2. Cookies We Use'],
        ['id' => 'local-storage', 'label' => '3. Local Storage'],
        ['id' => 'third-party',   'label' => '4. Third-Party Cookies'],
        ['id' => 'choices',       'label' => '5. Your Choices'],
        ['id' => 'contact',       'label' => '6. Contact'],
    ],
]);
?>

<p>This Cookie Policy explains what cookies and local storage Spencer's Website uses, what they do, and how you can control them. For our broader data practices, see the <a href="privacy.php">Privacy Policy</a>.</p>

<section id="what-is">
<h2>1. What Are Cookies?</h2>
<p>Cookies are small text files stored on your device by your browser when you visit a website. They are used to remember your preferences, maintain your session, and provide analytics.</p>
<p>"Local Storage" is a similar browser technology that stores data on your device without sending it to the server unless the application explicitly chooses to.</p>
</section>

<section id="our-cookies">
<h2>2. Cookies We Use</h2>

<h3>2.1 Essential (Required)</h3>
<table>
    <thead>
        <tr><th>Name</th><th>Purpose</th><th>Duration</th><th>Type</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>PHPSESSID</code></td>
            <td>Maintains your login state across pages</td>
            <td>Browser session (or 30 days if "Remember Me")</td>
            <td>Essential — First-party</td>
        </tr>
        <tr>
            <td><code>csrf_token</code> <em>(session var, not a cookie)</em></td>
            <td>Protects against Cross-Site Request Forgery attacks</td>
            <td>Session</td>
            <td>Essential — First-party</td>
        </tr>
    </tbody>
</table>
<p>These cookies are <strong>required</strong> for the site to function. Without them, you cannot log in or stay logged in.</p>
<p>Session cookies are set with <code>HttpOnly</code>, <code>Secure</code>, and <code>SameSite=Lax</code> flags for security.</p>

<h3>2.2 Functional (Optional)</h3>
<p>We do not set functional or preference cookies on the server. Preferences are stored in Local Storage (see Section 3).</p>

<h3>2.3 Analytics &amp; Marketing</h3>
<div class="success-box">
    <strong>Good news:</strong> We do not use Google Analytics, Facebook Pixel, or any third-party marketing trackers. Our analytics are <strong>server-side only</strong> (aggregated page views) and do not require cookies.
</div>
</section>

<section id="local-storage">
<h2>3. Local Storage</h2>
<p>We use browser Local Storage to save your preferences. This data stays on your device and is <strong>never sent to our servers</strong> unless you have a paid account with server-synced settings enabled.</p>

<h3>3.1 What We Store Locally</h3>
<ul>
    <li><strong>Theme preference</strong> — dark/light mode</li>
    <li><strong>Accent color</strong> — your chosen accent</li>
    <li><strong>Background choice</strong> — selected background theme</li>
    <li><strong>Game settings</strong> — volume, graphics, etc.</li>
    <li><strong>Dismissed banners/announcements</strong> — to avoid re-showing them</li>
    <li><strong>Cookie consent</strong> — if you've acknowledged this policy</li>
</ul>

<h3>3.2 Clearing Local Storage</h3>
<p>You can clear Local Storage from your browser settings at any time, or use our in-site "Clear Browsing Data" option in Settings &gt; Privacy.</p>
</section>

<section id="third-party">
<h2>4. Third-Party Cookies</h2>
<p>The following third-party services may set cookies on your device when you use our site:</p>

<h3>4.1 Google reCAPTCHA v3</h3>
<p>We use Google reCAPTCHA v3 (invisible) on login, registration, and other forms to prevent bot abuse. Google may set cookies to detect fraudulent activity. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a>.</p>

<h3>4.2 Stripe (Payment Processing)</h3>
<p>When you make a purchase, Stripe may set cookies during checkout to secure the transaction and detect fraud. See <a href="https://stripe.com/privacy" target="_blank" rel="noopener">Stripe's Privacy Policy</a> and <a href="https://stripe.com/cookies-policy/legal" target="_blank" rel="noopener">Cookie Policy</a>.</p>

<h3>4.3 Google OAuth (if you sign in with Google)</h3>
<p>If you sign in with Google, Google may set authentication-related cookies. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.</p>

<h3>4.4 CDN providers (fonts, icons)</h3>
<p>Cloudflare, jsDelivr, and Google Fonts serve static resources and may log IP addresses. They typically do not set tracking cookies.</p>
</section>

<section id="choices">
<h2>5. Your Choices</h2>

<h3>5.1 Browser Controls</h3>
<p>Most browsers let you block or delete cookies through their settings:</p>
<ul>
    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a></li>
    <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener">Firefox</a></li>
    <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
    <li><a href="https://support.microsoft.com/en-us/windows/delete-and-manage-cookies-168dab11-0753-043d-7c16-ede5947fc64d" target="_blank" rel="noopener">Edge</a></li>
</ul>

<div class="warning-box">
    <strong>Note:</strong> Blocking essential cookies (like <code>PHPSESSID</code>) will prevent you from logging in. You must allow first-party cookies from our domain to use the Service.
</div>

<h3>5.2 "Do Not Track" Signals</h3>
<p>We do not use behavioral tracking, so Do Not Track (DNT) signals have no additional effect on our site.</p>

<h3>5.3 Opt Out of reCAPTCHA / Stripe</h3>
<p>reCAPTCHA and Stripe cookies are only loaded when you interact with forms or checkout. If you do not want them, avoid submitting forms or making purchases. There is no way to use the Service without some form of bot protection on account-creation flows.</p>
</section>

<section id="contact">
<h2>6. Contact</h2>
<p>Questions about this Cookie Policy:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

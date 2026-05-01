<?php
/**
 * Acceptable Use Policy — Spencer's Website v7.0
 * New policy page created 2026-04-18.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Acceptable Use Policy',
    'subtitle'     => 'What you can and cannot do on Spencer\'s Website.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'aup',
    'toc'          => [
        ['id' => 'scope',       'label' => '1. Scope'],
        ['id' => 'permitted',   'label' => '2. Permitted Use'],
        ['id' => 'prohibited',  'label' => '3. Prohibited Activities'],
        ['id' => 'ai-usage',    'label' => '4. AI Assistant Rules'],
        ['id' => 'security',    'label' => '5. Security &amp; Integrity'],
        ['id' => 'consequences','label' => '6. Consequences'],
        ['id' => 'reporting',   'label' => '7. Reporting Violations'],
        ['id' => 'contact',     'label' => '8. Contact'],
    ],
]);
?>

<p>This Acceptable Use Policy ("AUP") governs how you may use Spencer's Website. It supplements the <a href="terms.php">Terms of Service</a> and <a href="community-standards.php">Community Standards</a>. Violations may result in strikes, time removals, or account termination.</p>

<div class="info-box">
    <strong>Related policies:</strong>
    <a href="terms.php">Terms of Service</a> · <a href="community-standards.php">Community Standards</a> · <a href="privacy.php">Privacy Policy</a>
</div>

<section id="scope">
<h2>1. Scope</h2>
<p>This AUP applies to all users of Spencer's Website (<strong>thespencerwebsite.com</strong> and <strong>thespencergamingwebsite.com</strong>), including guests, free Community accounts, paid members, contributors, and administrators.</p>
</section>

<section id="permitted">
<h2>2. Permitted Use</h2>
<p>You may use the Service for:</p>
<ul>
    <li>Playing embedded games and consuming content</li>
    <li>Participating in the community chat (Yaps) and discussion features</li>
    <li>Using the AI Assistant for conversational, educational, and entertainment purposes</li>
    <li>Sending Smail messages to other users in good faith</li>
    <li>Submitting feedback, bug reports, and feature suggestions</li>
    <li>Customizing your profile, background, and display name within platform limits</li>
    <li>Purchasing memberships for personal, non-commercial access</li>
</ul>
</section>

<section id="prohibited">
<h2>3. Prohibited Activities</h2>
<p>You must not engage in any of the following:</p>

<h3>3.1 Account Abuse</h3>
<ul>
    <li>Creating multiple accounts to evade bans, rate limits, or guest-account limits</li>
    <li>Sharing your account credentials or selling your account</li>
    <li>Using automated tools, bots, or scripts to interact with the Service without written permission</li>
    <li>Creating accounts on behalf of another person without their explicit consent</li>
</ul>

<h3>3.2 Content Violations</h3>
<ul>
    <li>Posting NSFW, sexually explicit, or adult content (see <a href="community-standards.php#rules">Rule B1</a>)</li>
    <li>Posting gore, graphic violence, animal cruelty, or terrorist/extremist content (Rule B2 — Immediate Termination)</li>
    <li>Posting hate speech, slurs, discriminatory content (Rule A2)</li>
    <li>Doxxing — posting personal information about others without consent (Rule C1)</li>
    <li>Impersonating staff, admins, or other users (Rule C2)</li>
    <li>Posting copyrighted material you do not own or have a license to (see <a href="dmca.php">DMCA Policy</a>)</li>
</ul>

<h3>3.3 Platform Abuse</h3>
<ul>
    <li>Spamming chat, Smail, feedback, or AI endpoints</li>
    <li>Unauthorized advertising of external services, Discord servers, social media, or competitors</li>
    <li>Flooding the site with repetitive or meaningless content</li>
    <li>Abusing the refund system (chargeback fraud, false dispute claims)</li>
</ul>

<h3>3.4 Illegal Activities</h3>
<ul>
    <li>Buying or selling drugs, stolen goods, hacking services, or any illegal goods</li>
    <li>Facilitating or planning crimes</li>
    <li>Sharing pirated software, media, or credentials</li>
    <li>Attempting to launder money, commit fraud, or evade taxes via the platform</li>
</ul>
</section>

<section id="ai-usage">
<h2>4. AI Assistant Rules</h2>
<p>When using the AI Assistant, you must not:</p>
<ul>
    <li>Attempt to jailbreak, prompt-inject, or bypass safety filters</li>
    <li>Generate content that violates Sections 3.2 (NSFW, hate speech, gore, etc.)</li>
    <li>Use the AI to produce malware, phishing content, or exploit code</li>
    <li>Harvest or scrape AI responses for commercial resale</li>
    <li>Use the AI to impersonate real individuals in harmful ways</li>
    <li>Generate deceptive content (fake news, deepfakes, misinformation)</li>
</ul>
<div class="warning-box">
    <strong>AI conversation logs are retained for 90 days</strong> and reviewed by administrators for safety. Intentional abuse of the AI may result in immediate Time Removal or Termination.
</div>
</section>

<section id="security">
<h2>5. Security &amp; Integrity</h2>
<p>You must not:</p>
<ul>
    <li>Attempt to probe, scan, or test the vulnerability of any system or network</li>
    <li>Attempt to breach security or authentication measures</li>
    <li>Interfere with service to any user, host, or network (DDoS, flooding, etc.)</li>
    <li>Use the Service to transmit any malware, virus, worm, or harmful code</li>
    <li>Reverse-engineer, decompile, or disassemble the Service (except where permitted by law)</li>
    <li>Scrape, crawl, or data-mine user data, chat histories, or site content without written permission</li>
    <li>Use VPNs, proxies, or other tools specifically to evade rate limits or bans</li>
</ul>
<p>Ethical security researchers may responsibly disclose vulnerabilities at <span class="contact-email">spencerbuisness101@gmail.com</span>.</p>
</section>

<section id="consequences">
<h2>6. Consequences</h2>
<p>Violations of this AUP are handled under the <a href="community-standards.php#punishment">Three-Tier Punishment System</a>:</p>
<ol>
    <li><strong>Tier 1 — Verbal Warning:</strong> Strike recorded, account active</li>
    <li><strong>Tier 2 — Time Removal:</strong> 3–14 days of restricted access</li>
    <li><strong>Tier 3 — Account Termination:</strong> Permanent ban; data retained per <a href="privacy.php#data-retention">Privacy Policy</a></li>
</ol>
<p>Certain violations (gore, illegal activity, child sexual content) result in <strong>immediate termination without appeal</strong>.</p>
</section>

<section id="reporting">
<h2>7. Reporting Violations</h2>
<p>If you see a user or content violating this policy:</p>
<ul>
    <li><strong>In-app:</strong> Use the "Report" button on posts, profiles, or chat messages</li>
    <li><strong>Feedback form:</strong> Submit via <code>feedback.php</code></li>
    <li><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></li>
</ul>
<p>Admins review reports within 72 hours. We do not tolerate retaliation against users who report violations in good faith.</p>
</section>

<section id="contact">
<h2>8. Contact</h2>
<p>Questions about this Acceptable Use Policy:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

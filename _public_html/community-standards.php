<?php
/**
 * Community Standards — Spencer's Website v7.0
 * Redesigned 2026-04-18 with dark-futuristic layout.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Community Standards',
    'subtitle'     => 'The rules for using Spencer\'s Website and how we enforce them.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'community',
    'toc'          => [
        ['id' => 'system',      'label' => 'The SYSTEM Account'],
        ['id' => 'punishment',  'label' => 'Three-Tier Punishment System'],
        ['id' => 'rules',       'label' => 'Complete Rules List'],
        ['id' => 'lockdown',    'label' => 'Lockdown Mode'],
        ['id' => 'appeals',     'label' => 'Appeals Process'],
        ['id' => 'enforcement', 'label' => 'Enforcement Details'],
        ['id' => 'contact',     'label' => 'Contact'],
    ],
]);
?>

<style>
/* Page-specific rule category cards */
.rule-category {
    background: rgba(255,255,255,0.03);
    border: 0.5px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 20px 24px;
    margin: 20px 0;
}
.rule-category h4 {
    margin-top: 0 !important;
    color: var(--accent) !important;
    font-size: 14px !important;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.strike-indicator {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    margin-left: 8px;
    text-transform: uppercase;
}
.strike-1 { background: rgba(234, 179, 8, 0.15); color: #eab308; border: 0.5px solid rgba(234,179,8,0.35); }
.strike-2 { background: rgba(249, 115, 22, 0.15); color: #f97316; border: 0.5px solid rgba(249,115,22,0.35); }
.strike-3 { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 0.5px solid rgba(239,68,68,0.35); }
.immediate { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 0.5px solid rgba(239,68,68,0.5); }
.zero-tolerance { background: rgba(220, 38, 38, 0.3); color: #fca5a5; border: 0.5px solid rgba(220,38,38,0.7); font-weight: 800; }
</style>

<p>These Community Standards define the rules for using Spencer's Website and explain how we enforce them. All users must follow these standards. Violations may result in warnings, temporary suspensions, or permanent account termination.</p>

<div class="info-box">
    <strong>Quick link:</strong> For a summary of the punishment system, see the <a href="terms.php#community-standards">Terms of Service §4</a>. For the full Acceptable Use Policy, see <a href="acceptable-use.php">Acceptable Use</a>.
</div>

<!-- SYSTEM -->
<section id="system">
<h2>The SYSTEM Account</h2>
<div class="info-box">
    <strong>SYSTEM</strong> (User ID: 0) is our automated moderation account. It handles all enforcement notifications and cannot receive messages.
    <ul>
        <li><strong>No Interaction:</strong> SYSTEM cannot receive emails, direct messages, mentions, or replies</li>
        <li><strong>Automated Notifications:</strong> SYSTEM sends official notifications for all rule violations</li>
        <li><strong>Message Contents:</strong> All SYSTEM messages include: Violation Reason, Evidence Snippet, Strike Count, and Punishment Applied</li>
        <li><strong>Strike Reset:</strong> Strike count automatically resets after 30 days of clean behavior</li>
    </ul>
</div>
<p>If you attempt to message SYSTEM, you will receive an auto-response: "SYSTEM is an automated account and does not accept messages. Please visit the Help Center."</p>
</section>

<!-- Punishment -->
<section id="punishment">
<h2>Three-Tier Punishment System</h2>
<table>
    <thead>
        <tr><th>Tier</th><th>Name</th><th>Description</th><th>Duration</th></tr>
    </thead>
    <tbody>
        <tr><td>1</td><td><strong>Verbal Warning</strong></td><td>Strike recorded on your account. All features remain active.</td><td>Visible for 30 days, then expires</td></tr>
        <tr><td>2</td><td><strong>Time Removal</strong></td><td>Posting, chatting, and interactive features disabled. View-only access.</td><td>3–14 days (admin discretion)</td></tr>
        <tr><td>3</td><td><strong>Account Termination</strong></td><td>Permanent ban from the platform. Data retained for security purposes.</td><td>Permanent</td></tr>
    </tbody>
</table>

<h3>Time Removal Duration Scale</h3>
<ul>
    <li><strong>3 days:</strong> Minor disruption (spam, unauthorized advertising)</li>
    <li><strong>7 days:</strong> Moderate offense (harassment, hate speech violations)</li>
    <li><strong>14 days:</strong> Final warning before termination (extreme toxicity, repeated offenses)</li>
</ul>
</section>

<!-- Rules -->
<section id="rules">
<h2>Complete Rules List</h2>

<div class="rule-category">
    <h4>Category A: Respect &amp; Conduct</h4>

    <p><strong>A1 — Harassment</strong> <span class="strike-indicator strike-1">Strike 1–3</span></p>
    <p>Targeting users with insults, slurs, intimidation, or persistent unwanted contact.</p>
    <ul>
        <li>Strike 1: Verbal Warning</li>
        <li>Strike 2: 3–7 day Time Removal</li>
        <li>Strike 3: Account Termination</li>
    </ul>

    <p><strong>A2 — Hate Speech</strong> <span class="strike-indicator zero-tolerance">Zero Tolerance</span></p>
    <p>Racism, sexism, homophobia, transphobia, religious discrimination, or extremist ideology.</p>
    <ul>
        <li>Strike 1: Immediate 5-day Time Removal (no warning)</li>
        <li>Repeat offenses escalate to termination</li>
    </ul>
</div>

<div class="rule-category">
    <h4>Category B: Content &amp; Safety</h4>

    <p><strong>B1 — NSFW/Adult Content</strong> <span class="strike-indicator immediate">Lockdown</span></p>
    <p>Explicit nudity, pornography, or sexually suggestive content involving minors (drawn or real).</p>
    <ul>
        <li>Immediate: Account enters Lockdown Mode</li>
        <li>User must submit appeal email to admin for review</li>
        <li>Content is removed and logged</li>
    </ul>

    <p><strong>B2 — Gore/Violent Extremism</strong> <span class="strike-indicator immediate">Immediate Termination</span></p>
    <p>Real-life gore, graphic violence, animal cruelty, or terrorist/extremist content.</p>
    <ul>
        <li>Immediate: Permanent Account Termination (no warning, no appeal)</li>
        <li>Incident logged for potential legal reporting</li>
    </ul>
</div>

<div class="rule-category">
    <h4>Category C: Security &amp; Privacy</h4>

    <p><strong>C1 — Doxxing</strong> <span class="strike-indicator immediate">Lockdown</span></p>
    <p>Posting personal information (name, address, phone, workplace, family details) without consent.</p>
    <ul>
        <li>Immediate: Account enters Lockdown Mode</li>
        <li>Posted information removed immediately</li>
        <li>User must submit appeal explaining why they should not receive Time Removal</li>
    </ul>

    <p><strong>C2 — Impersonation</strong> <span class="strike-indicator strike-1">Strike 1–2</span></p>
    <p>Pretending to be staff, moderators, administrators, or other users.</p>
    <ul>
        <li>Strike 1: Verbal Warning + forced name/nickname change</li>
        <li>Strike 2: Account Termination</li>
    </ul>
</div>

<div class="rule-category">
    <h4>Category D: Platform Integrity</h4>

    <p><strong>D1 — Spamming</strong> <span class="strike-indicator strike-1">Strike 1–3</span></p>
    <p>Repetitive messages, excessive CAPS, chain mail, or meaningless content flooding.</p>
    <ul>
        <li>Strike 1: Verbal Warning + content removal</li>
        <li>Strike 2: 3-day Time Removal</li>
        <li>Strike 3: 7-day Time Removal</li>
    </ul>

    <p><strong>D2 — Unauthorized Advertising</strong> <span class="strike-indicator strike-1">Strike 1–3</span></p>
    <p>Unsolicited promotion of external services, Discord servers, social media, or competitors.</p>
    <ul>
        <li>Strike 1: Verbal Warning</li>
        <li>Strike 2: 3-day Time Removal</li>
        <li>Strike 3: Account Termination</li>
    </ul>

    <p><strong>D3 — Ban Evasion</strong> <span class="strike-indicator immediate">Termination</span></p>
    <p>Creating new accounts during active Time Removal or after Termination.</p>
    <ul>
        <li>New account: Immediate Termination</li>
        <li>Original account: Punishment doubled or escalated to Termination</li>
        <li>IP and device fingerprints retained for 90 days post-punishment to detect evasion</li>
    </ul>
</div>

<div class="rule-category">
    <h4>Category E: Legal</h4>

    <p><strong>E1 — Illegal Activity</strong> <span class="strike-indicator immediate">Immediate Termination</span></p>
    <p>Buying/selling illegal goods, hacking services, stolen data, or facilitating crimes.</p>
    <ul>
        <li>Immediate: Permanent Account Termination</li>
        <li>Incident documented and may be reported to relevant authorities</li>
        <li>No appeal permitted</li>
    </ul>
</div>
</section>

<!-- Lockdown -->
<section id="lockdown">
<h2>Lockdown Mode</h2>
<div class="critical-box">
    <strong>Severe Violation Protocol</strong>
    <p>For severe violations (NSFW content, Doxxing), accounts enter Lockdown Mode immediately.</p>
</div>

<h3>Lockdown Restrictions</h3>
<ul>
    <li><strong>All permissions revoked:</strong> Cannot post, chat, send Smail, or interact with content</li>
    <li><strong>View-only access:</strong> Can view site but cannot participate</li>
    <li><strong>Forced appeal page:</strong> Redirected to /compliance/appeal on every login</li>
    <li><strong>Navigation blocked:</strong> Cannot navigate away from appeal page until submission</li>
    <li><strong>Account deletion disabled:</strong> Cannot delete account until appeal is processed</li>
</ul>

<h3>Lockdown Appeal Process</h3>
<ol>
    <li>User is redirected to the appeal page upon login</li>
    <li>User must submit written explanation of the violation</li>
    <li>User must acknowledge rules and commit to compliance</li>
    <li>Admin reviews appeal within 72 hours</li>
    <li>Decision: Release from lockdown OR escalate to Time Removal/Termination</li>
</ol>
<p><strong>Important:</strong> Attempting to circumvent lockdown (VPN, new accounts, browser tricks) will result in immediate Termination without appeal.</p>
</section>

<!-- Appeals -->
<section id="appeals">
<h2>Appeals Process</h2>
<div class="success-box">
    <strong>Your Right to Appeal</strong>
    <p>You have the right to appeal enforcement decisions within 14 days of the action (GDPR Article 22).</p>
</div>

<h3>How to Appeal</h3>
<ol>
    <li><strong>Via Smail:</strong> Send a message to any administrator within 14 days</li>
    <li><strong>Include:</strong> Your perspective on the violation, why you believe it was an error, and your commitment to following rules</li>
    <li><strong>One appeal per action:</strong> Multiple appeals for the same strike will be ignored</li>
    <li><strong>Be respectful:</strong> Hostile appeals will be automatically denied</li>
</ol>

<h3>Appeal Review Process</h3>
<ul>
    <li><strong>Timeline:</strong> Human moderator review within 72 hours</li>
    <li><strong>Outcome:</strong> Strike removed, reduced, or upheld</li>
    <li><strong>Final review:</strong> You may request one additional review if new evidence exists</li>
    <li><strong>Decisions after second review are final</strong></li>
</ul>

<h3>What Can Be Appealed</h3>
<table>
    <thead>
        <tr><th>Action</th><th>Appealable?</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td>Verbal Warnings (Tier 1)</td><td>Yes</td><td>Strikes can be appealed if applied in error</td></tr>
        <tr><td>Time Removal (Tier 2)</td><td>Yes</td><td>Duration can be appealed if excessive</td></tr>
        <tr><td>Lockdown Mode</td><td>Yes</td><td>Must submit via appeal page</td></tr>
        <tr><td>Terminations (Tier 3)</td><td>Yes (14 days)</td><td>Serious violations (B2, E1) typically not overturned</td></tr>
        <tr><td>Gore/Extremism (B2)</td><td>No</td><td>Zero tolerance — no appeal permitted</td></tr>
        <tr><td>Illegal Activity (E1)</td><td>No</td><td>Zero tolerance — no appeal permitted</td></tr>
    </tbody>
</table>
</section>

<!-- Enforcement -->
<section id="enforcement">
<h2>Enforcement Details</h2>

<h3>Moderator Discretion</h3>
<p>Administrators may escalate punishments at their discretion for:</p>
<ul>
    <li>Intentional rule-skirting (evading filters, using coded language)</li>
    <li>Aggravating circumstances (targeting vulnerable users, organized harassment)</li>
    <li>Repeat offenses within the 30-day strike window</li>
    <li>Bad-faith behavior during appeals</li>
</ul>

<h3>Ban Evasion Detection</h3>
<p>We employ multiple methods to detect ban evasion:</p>
<ul>
    <li><strong>IP address hashing:</strong> Stored and checked during registration</li>
    <li><strong>Device fingerprinting:</strong> Browser, screen resolution, fonts, GPU info</li>
    <li><strong>Behavioral patterns:</strong> Writing style, interaction patterns, timing</li>
    <li><strong>Retention period:</strong> Fingerprints kept for duration of punishment + 90 days</li>
</ul>

<h3>Transparency</h3>
<p>You can view your current strike count and history in your account settings. Each strike includes rule violated, date applied, expiration date, and associated punishment.</p>

<h3>Data Retention for Terminated Accounts</h3>
<p>Per our <a href="privacy.php#data-retention">Privacy Policy</a>, terminated accounts retain hashed email, device fingerprints, and strike/violation history indefinitely under GDPR "Legitimate Interest" exemption for security.</p>
</section>

<!-- Contact -->
<section id="contact">
<h2>Questions?</h2>
<p>If you have questions about these Community Standards, contact us:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
<p><strong>Note:</strong> Do not attempt to contact the SYSTEM account. It cannot receive messages.</p>
</section>

<?php render_policy_footer(); ?>

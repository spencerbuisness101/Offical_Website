<?php
/**
 * Accessibility Statement — Spencer's Website v7.0
 * WCAG 2.1 AA commitment statement.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'Accessibility Statement',
    'subtitle'     => 'Our commitment to making Spencer\'s Website usable by everyone.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'accessibility',
    'toc'          => [
        ['id' => 'commitment',     'label' => '1. Our Commitment'],
        ['id' => 'standards',      'label' => '2. Standards We Follow'],
        ['id' => 'features',       'label' => '3. Accessibility Features'],
        ['id' => 'known-issues',   'label' => '4. Known Limitations'],
        ['id' => 'assistive-tech', 'label' => '5. Assistive Technology'],
        ['id' => 'feedback',       'label' => '6. Feedback &amp; Contact'],
    ],
]);
?>

<p>Spencer's Website is committed to ensuring that our Service is accessible to all users, including those with disabilities. We strive to meet the <strong>Web Content Accessibility Guidelines (WCAG) 2.1 Level AA</strong> standards.</p>

<section id="commitment">
<h2>1. Our Commitment</h2>
<p>We believe that the web should be usable by everyone, regardless of ability. We are continuously improving our platform to meet and exceed accessibility standards. Our goals include:</p>
<ul>
    <li>Ensuring keyboard navigation works throughout the site</li>
    <li>Providing sufficient color contrast for readable text</li>
    <li>Supporting screen readers with semantic HTML and ARIA attributes</li>
    <li>Respecting user preferences like reduced-motion settings</li>
    <li>Providing alternative text for meaningful images and icons</li>
</ul>
</section>

<section id="standards">
<h2>2. Standards We Follow</h2>
<ul>
    <li><strong>WCAG 2.1 Level AA</strong> — The W3C's Web Content Accessibility Guidelines at conformance level AA</li>
    <li><strong>Section 508</strong> (U.S.) — Federal accessibility requirements (for reference)</li>
    <li><strong>EN 301 549</strong> (EU) — European accessibility standard (for reference)</li>
</ul>
</section>

<section id="features">
<h2>3. Accessibility Features</h2>

<h3>3.1 Keyboard Navigation</h3>
<ul>
    <li>All interactive elements are reachable with Tab/Shift+Tab</li>
    <li>Focus indicators are visible (2px solid violet outline)</li>
    <li>"Skip to login" / "Skip to content" links are available at the top of most pages</li>
    <li>No keyboard traps — you can always navigate away from any component</li>
</ul>

<h3>3.2 Reduced Motion</h3>
<p>We honor the <code>prefers-reduced-motion</code> CSS media query. If you have reduced motion enabled in your OS settings:</p>
<ul>
    <li>Animated backgrounds (starfield, nebula, particles) are disabled</li>
    <li>Kinetic text animations are disabled</li>
    <li>Page transition wipe effects are disabled</li>
    <li>Scroll indicators are static</li>
</ul>

<h3>3.3 Color &amp; Contrast</h3>
<ul>
    <li>Text contrast meets WCAG 2.1 AA (4.5:1 for normal text, 3:1 for large text)</li>
    <li>UI components meet 3:1 contrast against their backgrounds</li>
    <li>Information is never conveyed by color alone — icons, text labels, and shapes reinforce meaning</li>
</ul>

<h3>3.4 Screen Reader Support</h3>
<ul>
    <li>Semantic HTML5 elements (<code>&lt;nav&gt;</code>, <code>&lt;main&gt;</code>, <code>&lt;article&gt;</code>, <code>&lt;aside&gt;</code>)</li>
    <li>ARIA labels on icon-only buttons</li>
    <li><code>aria-hidden</code> on decorative background layers</li>
    <li>Form fields are properly labelled with <code>&lt;label&gt;</code> elements</li>
    <li>Error messages are associated with form fields via <code>aria-describedby</code></li>
</ul>

<h3>3.5 Responsive &amp; Zoom-Safe Design</h3>
<ul>
    <li>Layout adapts to screens from 320px (small phones) to 1920+ (wide monitors)</li>
    <li>Content remains readable and functional at 200% zoom</li>
    <li>Text can be resized without loss of functionality</li>
</ul>

<h3>3.6 Forms</h3>
<ul>
    <li>All inputs have associated labels</li>
    <li>Required fields are clearly marked</li>
    <li>Error messages are specific and actionable</li>
    <li>Password visibility toggles are keyboard-accessible</li>
</ul>
</section>

<section id="known-issues">
<h2>4. Known Limitations</h2>
<p>We're continuously improving accessibility. Currently known issues:</p>
<ul>
    <li><strong>Embedded third-party games</strong> — Games are hosted externally and may not meet our accessibility standards. We are unable to control their content.</li>
    <li><strong>AI chat context menus</strong> — Some advanced AI features may have limited screen-reader support. We are working on improvements.</li>
    <li><strong>Dynamic notifications</strong> — Toast notifications may not always be announced to screen readers; we are adding <code>aria-live</code> regions.</li>
</ul>
<p>If you encounter an accessibility issue not listed here, please let us know (see Section 6).</p>
</section>

<section id="assistive-tech">
<h2>5. Assistive Technology Compatibility</h2>
<p>We test Spencer's Website with the following assistive technologies:</p>
<ul>
    <li><strong>Screen readers:</strong> NVDA (Windows), JAWS (Windows), VoiceOver (macOS/iOS), TalkBack (Android)</li>
    <li><strong>Browsers:</strong> Latest versions of Chrome, Firefox, Safari, Edge</li>
    <li><strong>OS accessibility features:</strong> High-contrast mode, reduced-motion settings, OS-level zoom</li>
</ul>
<p>If you use a different combination and experience issues, please report it.</p>
</section>

<section id="feedback">
<h2>6. Feedback &amp; Contact</h2>
<p>We welcome your feedback on the accessibility of Spencer's Website. If you encounter barriers or have suggestions for improvement:</p>
<ul>
    <li><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></li>
    <li><strong>Subject line:</strong> "Accessibility Feedback"</li>
    <li><strong>Response time:</strong> We aim to respond within 5 business days.</li>
</ul>
<p>Please include:</p>
<ul>
    <li>The page URL where you experienced the issue</li>
    <li>The assistive technology and browser you use</li>
    <li>A description of the problem</li>
    <li>Any suggestions for improvement</li>
</ul>
</section>

<?php render_policy_footer(); ?>

<?php
/**
 * DMCA & Copyright Policy — Spencer's Website v7.0
 * Redesigned 2026-04-18 with dark-futuristic layout.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/policy_layout.php';

render_policy_header([
    'title'        => 'DMCA & Copyright Policy',
    'subtitle'     => 'Reporting copyright infringement, counter-notification procedures, and repeat-infringer policy.',
    'last_updated' => '2026-04-18',
    'version'      => '9.0',
    'icon'         => 'dmca',
    'toc'          => [
        ['id' => 'infringement', 'label' => '1. Reporting Copyright Infringement'],
        ['id' => 'counter',      'label' => '2. Counter-Notification'],
        ['id' => 'repeat',       'label' => '3. Repeat Infringer Policy'],
        ['id' => 'third-party',  'label' => '4. Third-Party Game Content'],
        ['id' => 'agent',        'label' => '5. Designated Copyright Agent'],
        ['id' => 'contact',      'label' => '6. Contact'],
    ],
]);
?>

<p>Spencer's Website respects the intellectual property rights of others and expects its users to do the same. This policy outlines our procedures for reporting copyright infringement in accordance with the Digital Millennium Copyright Act (DMCA).</p>

<div class="info-box">
    <strong>Important:</strong> This policy applies to all content hosted on or embedded within <strong>thespencerwebsite.com</strong> and <strong>thespencergamingwebsite.com</strong>, including third-party game content displayed via iframes.
</div>

<!-- 1 -->
<section id="infringement">
<h2>1. Reporting Copyright Infringement</h2>
<p>If you believe that your copyrighted work has been copied in a way that constitutes copyright infringement and is accessible on this Service, please notify our designated copyright agent by providing the following information:</p>

<h3>1.1 Required Information</h3>
<ol>
    <li><strong>Identification of the copyrighted work</strong> claimed to have been infringed, or a representative list if multiple works are covered by a single notification.</li>
    <li><strong>Identification of the material</strong> that is claimed to be infringing, with information reasonably sufficient to permit the service provider to locate it (e.g., the URL or page where the material appears).</li>
    <li><strong>Your contact information</strong> — full name, mailing address, telephone number, and email address.</li>
    <li><strong>A statement</strong> that you have a good faith belief that the use of the material is not authorized by the copyright owner, its agent, or the law.</li>
    <li><strong>A statement</strong>, under penalty of perjury, that the information in the notification is accurate, and that you are authorized to act on behalf of the owner of an exclusive right that is allegedly infringed.</li>
    <li><strong>A physical or electronic signature</strong> of a person authorized to act on behalf of the copyright owner.</li>
</ol>

<h3>1.2 Filing a DMCA Takedown Notice</h3>
<p>Send the required information to our designated copyright agent (see <a href="#agent">Section 5</a>). Include "DMCA Takedown Notice" in the subject line of your email.</p>

<div class="critical-box">
    <strong>False Claims:</strong> Under Section 512(f) of the DMCA, any person who knowingly materially misrepresents that material or activity is infringing may be subject to liability for damages. Do not submit a DMCA notice if you are not the copyright owner or authorized to act on their behalf.
</div>
</section>

<!-- 2 -->
<section id="counter">
<h2>2. Counter-Notification</h2>
<p>If you believe that your content was removed or disabled as a result of a mistake or misidentification, you may submit a counter-notification.</p>

<h3>2.1 Required Information</h3>
<ol>
    <li><strong>Identification of the material</strong> that has been removed or to which access has been disabled, and the location at which the material appeared before removal.</li>
    <li><strong>A statement</strong>, under penalty of perjury, that you have a good faith belief that the material was removed or disabled as a result of mistake or misidentification.</li>
    <li><strong>Your name, address, telephone number,</strong> and a statement that you consent to the jurisdiction of the Federal District Court for the judicial district in which your address is located (or, if outside the U.S., any judicial district in which the service provider may be found), and that you will accept service of process from the complainant.</li>
    <li><strong>A physical or electronic signature.</strong></li>
</ol>

<h3>2.2 Process</h3>
<p>Upon receipt of a valid counter-notification, we will forward it to the original complainant. The disputed material will be restored within <strong>10–14 business days</strong> unless the original complainant files a court action against you.</p>
</section>

<!-- 3 -->
<section id="repeat">
<h2>3. Repeat Infringer Policy</h2>
<p>In accordance with Section 512(i) of the DMCA, we will terminate the accounts of users who are found to be repeat infringers. A "repeat infringer" is a user who has had two or more valid DMCA takedown notices filed against them.</p>
<table>
    <thead><tr><th>Notice</th><th>Action</th></tr></thead>
    <tbody>
        <tr><td>First valid DMCA notice</td><td>Content removed, user receives a warning</td></tr>
        <tr><td>Second valid DMCA notice</td><td>Account suspended for 14 days</td></tr>
        <tr><td>Third valid DMCA notice</td><td>Account permanently terminated</td></tr>
    </tbody>
</table>
</section>

<!-- 4 -->
<section id="third-party">
<h2>4. Third-Party Game Content</h2>
<p>Our Service embeds third-party HTML games via <code>&lt;iframe&gt;</code> elements. These games are hosted on external servers and remain the intellectual property of their respective creators. We do not claim ownership of any embedded game content.</p>

<h3>4.1 Game Takedown Procedure</h3>
<ol>
    <li>Submit a DMCA takedown notice as described in Section 1.</li>
    <li>Include the URL of the page on our site where the game is embedded.</li>
    <li>We will remove the embedded game from our site within <strong>5 business days</strong> of receiving a valid notice.</li>
</ol>

<h3>4.2 Game Developer Self-Service</h3>
<p>If you are a game developer and your game is embedded on our Service, you may also contact us directly to discuss licensing, attribution, or removal. We are committed to working with content creators to resolve any concerns.</p>
</section>

<!-- 5 -->
<section id="agent">
<h2>5. Designated Copyright Agent</h2>
<p>All DMCA takedown notices and counter-notifications should be sent to our designated copyright agent:</p>
<div class="info-box">
    <strong>Copyright Agent</strong><br>
    Email: <span class="contact-email">spencerbuisness101@gmail.com</span><br>
    Subject Line: <em>"DMCA Takedown Notice"</em> or <em>"DMCA Counter-Notification"</em>
</div>
<p>Please allow up to <strong>5 business days</strong> for a response to your notice.</p>
</section>

<!-- 6 -->
<section id="contact">
<h2>6. Contact</h2>
<p>For questions about this DMCA &amp; Copyright Policy, contact us:</p>
<p><strong>Email:</strong> <span class="contact-email">spencerbuisness101@gmail.com</span></p>
</section>

<?php render_policy_footer(); ?>

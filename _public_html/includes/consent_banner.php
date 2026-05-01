<?php
/**
 * Cookie & Tracking Consent Banner - Spencer's Website v7.0
 * Phase 1 Compliance: Informational banner for cookies, tracking, and embedded content.
 * Include this file on pages after the opening <body> tag or before closing </body>.
 * Uses localStorage to remember dismissal — no extra cookies needed.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
?>
<!-- Consent Banner -->
<div id="consentBanner" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999; background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); border-top:1px solid rgba(78,205,196,0.3); padding:16px 24px; box-shadow:0 -4px 20px rgba(0,0,0,0.4);">
    <div style="max-width:1100px; margin:0 auto; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:280px; color:#cbd5e1; font-size:0.88rem; line-height:1.55;">
            This site uses <strong style="color:#e2e8f0;">essential cookies</strong> for login sessions, <strong style="color:#e2e8f0;">device fingerprinting</strong> for security, and embeds <strong style="color:#e2e8f0;">third-party game content</strong>.
            See our <a href="privacy.php" style="color:#4ECDC4; text-decoration:underline;">Privacy Policy</a> for details.
        </div>
        <button onclick="dismissConsentBanner()" style="flex-shrink:0; padding:9px 22px; border:none; border-radius:8px; background:linear-gradient(135deg,#4ECDC4,#3b82f6); color:white; font-size:0.88rem; font-weight:600; cursor:pointer; white-space:nowrap; transition:opacity 0.2s;">
            Got it
        </button>
    </div>
</div>
<script>
(function(){
    if (localStorage.getItem('consent_dismissed')) return;
    var b = document.getElementById('consentBanner');
    if (b) b.style.display = 'block';
})();
function dismissConsentBanner(){
    localStorage.setItem('consent_dismissed', '1');
    var b = document.getElementById('consentBanner');
    if (b) b.style.display = 'none';
}
</script>

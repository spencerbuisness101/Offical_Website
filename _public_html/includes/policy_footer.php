<?php
/**
 * Policy Footer Links - Spencer's Website v7.0
 * Phase 1 Compliance: Footer links to legal policy pages.
 * Include this at the bottom of page content, before closing </body>.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
?>
<footer class="pf-footer">
    <div class="pf-links">
        <a href="/privacy.php">Privacy Policy</a>
        <span class="pf-dot">·</span>
        <a href="/terms.php">Terms of Service</a>
        <span class="pf-dot">·</span>
        <a href="/refund-policy.php">Refund Policy</a>
    </div>
    <div class="pf-brand">Spencer's Website v7.0 &mdash; Zero-Trust Architecture</div>
</footer>
<style>
.pf-footer{text-align:center;padding:24px 16px 16px;margin-top:48px;border-top:1px solid rgba(255,255,255,0.05);font-family:'Segoe UI',system-ui,sans-serif;}
.pf-links{display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:8px;}
.pf-links a{color:#64748b;text-decoration:none;font-size:0.78rem;font-weight:500;padding:4px 8px;border-radius:6px;transition:all .2s;}
.pf-links a:hover{color:#4ECDC4;background:rgba(78,205,196,0.08);}
.pf-dot{color:#334155;font-size:0.6rem;}
.pf-brand{color:#334155;font-size:0.68rem;letter-spacing:0.3px;}
</style>

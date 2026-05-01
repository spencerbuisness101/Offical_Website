<?php
/**
 * Shared Site Footer — Spencer's Website v7.0
 *
 * Single source of truth for the site-wide footer. Drop this include
 * right before </body> on every top-level PHP page:
 *
 *     <?php require __DIR__ . '/includes/site_footer.php'; ?>
 *
 * Renders:
 *  - The full policy / support link row
 *  - Copyright notice ("© <current year> Spencer's Website. All rights reserved.")
 *
 * The inline <style> block is self-contained and matches the dark
 * futuristic aesthetic of index.php so it renders consistently even on
 * pages that don't load shared stylesheets.
 */

// Guard: render once per request even if accidentally included twice.
if (!defined('SPENCER_SITE_FOOTER_RENDERED')) {
    define('SPENCER_SITE_FOOTER_RENDERED', true);
?>
<style>
    .spencer-site-footer {
        position: relative;
        z-index: 5;
        margin-top: 60px;
        padding: 40px 24px 32px;
        background: rgba(4, 4, 10, 0.92);
        border-top: 0.5px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, sans-serif;
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.6;
    }
    .spencer-site-footer__inner {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
        text-align: center;
    }
    .spencer-site-footer__links {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px 22px;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .spencer-site-footer__links a {
        color: #cbd5e1;
        text-decoration: none;
        font-size: 13px;
        font-weight: 400;
        letter-spacing: 0.01em;
        padding: 4px 2px;
        transition: color 0.2s ease, text-shadow 0.2s ease;
    }
    .spencer-site-footer__links a:hover,
    .spencer-site-footer__links a:focus-visible {
        color: #7B6EF6;
        text-shadow: 0 0 12px rgba(123, 110, 246, 0.35);
        outline: none;
    }
    .spencer-site-footer__copy {
        color: #64748B;
        font-size: 12px;
        letter-spacing: 0.02em;
    }
    .spencer-site-footer__brand {
        color: #E2E8F0;
        font-weight: 500;
        letter-spacing: 0.04em;
    }
    @media (max-width: 600px) {
        .spencer-site-footer { padding: 28px 16px 24px; margin-top: 40px; }
        .spencer-site-footer__links { gap: 8px 16px; }
        .spencer-site-footer__links a { font-size: 12px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .spencer-site-footer__links a { transition: none; }
    }
</style>
<footer class="spencer-site-footer" role="contentinfo">
    <div class="spencer-site-footer__inner">
        <nav class="spencer-site-footer__links" aria-label="Site policies and support">
            <a href="/privacy.php">Privacy</a>
            <a href="/terms.php">Terms</a>
            <a href="/acceptable-use.php">Acceptable Use</a>
            <a href="/cookie-policy.php">Cookies</a>
            <a href="/community-standards.php">Community Standards</a>
            <a href="/refund-policy.php">Refunds</a>
            <a href="/dmca.php">DMCA</a>
            <a href="/childrens-privacy.php">Children's Privacy</a>
            <a href="/accessibility.php">Accessibility</a>
            <a href="/supporthelp.php">Support</a>
        </nav>
        <div class="spencer-site-footer__copy">
            &copy; <?php echo date('Y'); ?> <span class="spencer-site-footer__brand">Spencer's Website</span>. All rights reserved.
        </div>
    </div>
</footer>
<?php } // end SPENCER_SITE_FOOTER_RENDERED guard ?>

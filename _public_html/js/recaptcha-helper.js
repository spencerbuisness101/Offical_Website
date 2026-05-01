/* ============================================================================
 * recaptcha-helper.js  (v7.1)
 *
 * Provides a forgiving wrapper around Google reCAPTCHA v3 that:
 *   - Detects when grecaptcha never loads (ad-blockers, browser
 *     tracking-prevention, network filters) and resolves with an empty
 *     token instead of hanging the form.
 *   - Suppresses the noisy "tracking prevention" warning by silently
 *     exiting if grecaptcha never becomes ready within 5 s.
 *   - Provides a single async function: window.getRecaptchaToken(action)
 *     that returns a string (token or '').
 *
 * The server-side login handler in /auth/login.php is fail-OPEN: missing
 * or invalid tokens just log a warning and continue — login is still
 * protected by CSRF tokens and tiered rate limiting. So this wrapper
 * cannot weaken security; it only smooths the user experience.
 * ========================================================================= */

(function () {
    'use strict';
    if (typeof window === 'undefined') return;

    const TIMEOUT_MS = 5000;

    function getSiteKey() {
        // The site key is exposed via a meta tag or window global by index.php
        const meta = document.querySelector('meta[name="recaptcha-site-key"]');
        if (meta && meta.content) return meta.content.trim();
        if (window.__RECAPTCHA_SITE_KEY) return window.__RECAPTCHA_SITE_KEY;
        return '';
    }

    function waitForGrecaptcha(timeoutMs) {
        return new Promise((resolve) => {
            if (window.grecaptcha && window.grecaptcha.execute) {
                if (window.grecaptcha.ready) {
                    window.grecaptcha.ready(() => resolve(true));
                } else {
                    resolve(true);
                }
                return;
            }
            const start = Date.now();
            const tick = () => {
                if (window.grecaptcha && window.grecaptcha.execute) {
                    if (window.grecaptcha.ready) {
                        window.grecaptcha.ready(() => resolve(true));
                    } else {
                        resolve(true);
                    }
                    return;
                }
                if (Date.now() - start > timeoutMs) return resolve(false);
                setTimeout(tick, 100);
            };
            tick();
        });
    }

    /**
     * Get a reCAPTCHA v3 token for a given action.
     * @param {string} action  Logical action name, e.g. "login", "register".
     * @returns {Promise<string>}  Token string, or empty string if reCAPTCHA failed/blocked.
     */
    window.getRecaptchaToken = async function (action) {
        const siteKey = getSiteKey();
        if (!siteKey) return '';
        try {
            const ready = await waitForGrecaptcha(TIMEOUT_MS);
            if (!ready) return '';
            const token = await window.grecaptcha.execute(siteKey, { action: action || 'submit' });
            return typeof token === 'string' ? token : '';
        } catch (err) {
            // Tracking prevention can throw here. Silent failure is intentional.
            return '';
        }
    };
})();

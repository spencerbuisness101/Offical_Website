<?php
/**
 * Donate — Dedicated donation landing page
 *
 * Heartfelt message + custom amount picker. Uses the same Stripe flow as
 * `shop.php?plan=donation` for the actual payment; here we surface a clean,
 * distraction-free experience and let the user choose a tip amount before
 * being taken to the checkout modal.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';

$csrfToken = generateCsrfToken();
$recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate — Spencer's Website</title>
    <meta name="description" content="Support Spencer's Website with a one-time donation. Every dollar keeps the servers humming and the platform growing.">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/tokens.css">
    <style>
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* Background */
        .bg-base {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(255,107,179,0.10) 0%, transparent 55%),
                radial-gradient(ellipse 70% 60% at 20% 80%, rgba(123,110,246,0.12) 0%, transparent 55%),
                radial-gradient(ellipse 60% 70% at 90% 40%, rgba(29,255,196,0.06) 0%, transparent 55%),
                linear-gradient(180deg, #04040A 0%, #08081A 50%, #04040A 100%);
            pointer-events: none;
        }
        .bg-nebula {
            position: fixed; inset: 0; z-index: 0;
            pointer-events: none; overflow: hidden; opacity: 0.35;
            filter: blur(80px);
        }
        .bg-nebula .blob {
            position: absolute; border-radius: 50%;
            mix-blend-mode: screen; opacity: 0.6;
            animation: blob-drift 30s ease-in-out infinite;
        }
        .bg-nebula .blob.b1 { width: 520px; height: 520px; background: radial-gradient(circle, #FF6BB3, transparent 70%); top: -10%; left: 50%; transform: translateX(-50%); }
        .bg-nebula .blob.b2 { width: 460px; height: 460px; background: radial-gradient(circle, #7B6EF6, transparent 70%); top: 55%; right: -10%; animation-delay: -10s; }
        .bg-nebula .blob.b3 { width: 500px; height: 500px; background: radial-gradient(circle, #1DFFC4, transparent 70%); bottom: -15%; left: 20%; animation-delay: -18s; }
        @keyframes blob-drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(40px, -30px) scale(1.08); }
            66% { transform: translate(-30px, 40px) scale(0.95); }
        }
        .bg-vignette {
            position: fixed; inset: 0; z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 40%, rgba(0,0,0,0.5) 100%);
        }

        /* Navbar */
        .nav {
            position: sticky; top: 0; z-index: 100;
            padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(4,4,10,0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 0.5px solid rgba(255,255,255,0.06);
        }
        .nav-brand {
            display: flex; align-items: center; gap: 10px;
            color: var(--text); font-weight: 300;
            letter-spacing: 0.08em; font-size: 15px;
        }
        .nav-brand svg { width: 24px; height: 24px; }
        .nav-brand:hover { text-decoration: none; }
        .nav-back {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--text-muted); font-size: 13px;
            padding: 8px 14px;
            border: 0.5px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            transition: all 0.2s ease;
        }
        .nav-back:hover { color: var(--accent); border-color: var(--accent); text-decoration: none; }
        .nav-back svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.8; }

        /* Hero */
        .donate-hero {
            position: relative; z-index: 1;
            max-width: 760px;
            margin: 0 auto;
            padding: 72px 24px 24px;
            text-align: center;
        }
        .heart-badge {
            width: 72px; height: 72px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,107,179,0.18), rgba(123,110,246,0.14));
            border: 0.5px solid rgba(255,107,179,0.35);
            box-shadow: 0 0 60px rgba(255,107,179,0.3);
            display: flex; align-items: center; justify-content: center;
            animation: heart-pulse 2.4s ease-in-out infinite;
        }
        @keyframes heart-pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 60px rgba(255,107,179,0.3); }
            50%      { transform: scale(1.08); box-shadow: 0 0 80px rgba(255,107,179,0.5); }
        }
        .heart-badge svg { width: 34px; height: 34px; fill: #FF6BB3; filter: drop-shadow(0 0 12px rgba(255,107,179,0.6)); }

        .donate-hero h1 {
            font-size: clamp(34px, 5.5vw, 54px);
            font-weight: 200;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin-bottom: 18px;
        }
        .donate-hero h1 .accent {
            background: linear-gradient(135deg, #FF6BB3 0%, #7B6EF6 50%, #1DFFC4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .donate-hero .lede {
            font-size: 18px;
            color: var(--text-muted);
            font-weight: 300;
            max-width: 580px;
            margin: 0 auto 8px;
        }
        .donate-hero .signature {
            display: inline-block;
            margin-top: 12px;
            font-size: 13px;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Message card */
        .donate-message {
            position: relative; z-index: 1;
            max-width: 720px;
            margin: 32px auto 0;
            padding: 32px 36px;
            background: var(--glass-bg);
            border: var(--glass-border);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius);
        }
        .donate-message p {
            color: #CBD5E1;
            font-size: 15.5px;
            margin-bottom: 14px;
        }
        .donate-message p:last-child { margin-bottom: 0; }
        .donate-message strong { color: var(--text); font-weight: 500; }
        .donate-message em { color: var(--teal); font-style: normal; }

        /* Amount chooser */
        .donate-picker {
            position: relative; z-index: 1;
            max-width: 560px;
            margin: 32px auto 0;
            padding: 36px 32px;
            background: var(--glass-bg);
            border: 1px solid rgba(255,107,179,0.25);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius);
            box-shadow: 0 0 80px rgba(255,107,179,0.08);
        }
        .picker-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            margin-bottom: 14px;
            text-align: center;
        }
        .preset-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .preset-btn {
            padding: 14px 6px;
            background: rgba(255,255,255,0.03);
            border: 0.5px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            color: var(--text);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        .preset-btn:hover {
            background: rgba(255,107,179,0.08);
            border-color: rgba(255,107,179,0.4);
            color: #FF6BB3;
        }
        .preset-btn.selected {
            background: rgba(255,107,179,0.14);
            border-color: #FF6BB3;
            color: #FF6BB3;
            box-shadow: 0 0 24px rgba(255,107,179,0.25);
        }
        .custom-amount-wrap {
            display: flex; align-items: center;
            background: rgba(255,255,255,0.04);
            border: 0.5px solid rgba(255,255,255,0.14);
            border-radius: 12px;
            padding: 2px 16px;
            margin-bottom: 20px;
            transition: border-color 0.2s;
        }
        .custom-amount-wrap:focus-within { border-color: var(--accent); }
        .custom-amount-wrap .prefix {
            color: var(--text-muted);
            font-size: 18px;
            margin-right: 8px;
        }
        .custom-amount-wrap input {
            flex: 1;
            background: none;
            border: none;
            outline: none;
            padding: 14px 0;
            font-size: 18px;
            color: var(--text);
            font-family: inherit;
            font-weight: 500;
            -moz-appearance: textfield;
        }
        .custom-amount-wrap input::-webkit-outer-spin-button,
        .custom-amount-wrap input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        .msg-box {
            background: rgba(255,255,255,0.03);
            border: 0.5px solid rgba(255,255,255,0.10);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 22px;
            resize: vertical;
            min-height: 70px;
            max-height: 200px;
            width: 100%;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
        }
        .msg-box:focus { outline: none; border-color: var(--accent); }
        .msg-counter { font-size: 11px; color: var(--text-dim); text-align: right; margin-top: -18px; margin-bottom: 14px; }

        .donate-cta {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #FF6BB3 0%, #7B6EF6 100%);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: inherit;
            box-shadow: 0 10px 30px rgba(255,107,179,0.25);
        }
        .donate-cta:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 14px 40px rgba(255,107,179,0.4);
        }
        .donate-cta:disabled { opacity: 0.6; cursor: not-allowed; }
        .donate-cta svg { width: 18px; height: 18px; fill: currentColor; }

        .secure-note {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            margin-top: 14px;
            font-size: 12px;
            color: var(--text-dim);
        }
        .secure-note svg { width: 12px; height: 12px; fill: currentColor; }

        .error-banner {
            background: rgba(239,68,68,0.08);
            border: 0.5px solid rgba(239,68,68,0.3);
            color: #FCA5A5;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .error-banner.visible { display: block; }

        /* Footer */
        .donate-footer {
            position: relative; z-index: 1;
            text-align: center;
            padding: 40px 24px 60px;
            color: var(--text-dim);
            font-size: 13px;
        }
        .donate-footer a { color: var(--text-muted); margin: 0 10px; }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .bg-nebula .blob, .heart-badge { animation: none; }
            html { scroll-behavior: auto; }
        }
        @media (max-width: 540px) {
            .preset-row { grid-template-columns: repeat(2, 1fr); }
            .donate-picker { padding: 28px 22px; }
            .donate-message { padding: 24px 22px; }
        }
    </style>
</head>
<body>
    <div class="bg-base"></div>
    <div class="bg-nebula" aria-hidden="true">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>
    </div>
    <div class="bg-vignette" aria-hidden="true"></div>

    <nav class="nav">
        <a href="index.php" class="nav-brand">
            <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14 2L26 8V20L14 26L2 20V8L14 2Z" stroke="#7B6EF6" stroke-width="1" fill="rgba(123,110,246,0.08)"/>
                <path d="M14 8L20 11V17L14 20L8 17V11L14 8Z" fill="#7B6EF6" opacity="0.6"/>
                <circle cx="14" cy="14" r="2" fill="#1DFFC4"/>
            </svg>
            <span>SPENCER'S</span>
        </a>
        <a href="index.php" class="nav-back">
            <svg viewBox="0 0 24 24"><path d="M19 12H5m7-7l-7 7 7 7"/></svg>
            Back to home
        </a>
    </nav>

    <header class="donate-hero">
        <div class="heart-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
        <h1>Fuel the <span class="accent">Universe</span></h1>
        <p class="lede">
            No ads. No trackers selling your data. Just a passion project funded by people who love it.
        </p>
        <span class="signature">— Support keeps the lights on</span>
    </header>

    <section class="donate-message" aria-labelledby="msg-title">
        <p><strong id="msg-title">Hey, I'm Spencer.</strong></p>
        <p>
            Spencer's Website runs on late-night commits, too much coffee, and a genuine love for building things that feel
            alive. There are no ads here. No data brokers. No dark patterns. Just games, AI chat, community, and a whole
            universe I keep expanding whenever I get the chance.
        </p>
        <p>
            Every dollar you chip in goes directly to <em>keeping the servers humming</em>, paying Stripe fees, funding new
            game content, and occasionally — I'll admit — a very-much-needed espresso. Donations are completely optional
            and never unlock anything that isn't already free for the community. You're not buying a feature; you're
            fueling something that wouldn't exist without people like you.
        </p>
        <p>
            If the platform has ever made you smile, saved you from boredom, or sparked a late-night rabbit hole — that's
            already enough. But if you want to leave a tip, I'd be genuinely grateful.
        </p>
    </section>

    <section class="donate-picker" aria-label="Donation amount">
        <div class="picker-label">Choose an amount</div>
        <div class="preset-row" id="presetRow">
            <button type="button" class="preset-btn" data-amount="3">$3</button>
            <button type="button" class="preset-btn selected" data-amount="5">$5</button>
            <button type="button" class="preset-btn" data-amount="10">$10</button>
            <button type="button" class="preset-btn" data-amount="25">$25</button>
        </div>
        <div class="custom-amount-wrap">
            <span class="prefix">$</span>
            <input type="number" id="donateAmount" min="1" max="100" step="0.01" value="5" inputmode="decimal" aria-label="Custom donation amount">
        </div>
        <textarea class="msg-box" id="donateMessage" maxlength="500" placeholder="Leave a note, share an idea, or just say hi (optional)"></textarea>
        <div class="msg-counter"><span id="msgCounter">0</span> / 500</div>

        <div class="error-banner" id="errorBanner"></div>

        <button type="button" class="donate-cta" id="donateCTA" onclick="startDonation()">
            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            <span id="ctaLabel">Donate $5</span>
        </button>

        <div class="secure-note">
            <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            Secured by Stripe. We never see your card details.
        </div>
    </section>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <script>
        const presetRow   = document.getElementById('presetRow');
        const amountInput = document.getElementById('donateAmount');
        const msgBox      = document.getElementById('donateMessage');
        const msgCounter  = document.getElementById('msgCounter');
        const ctaLabel    = document.getElementById('ctaLabel');
        const errorBanner = document.getElementById('errorBanner');

        function updateCtaLabel() {
            const v = parseFloat(amountInput.value);
            if (!isNaN(v) && v >= 1 && v <= 100) {
                ctaLabel.textContent = 'Donate $' + v.toFixed(v % 1 === 0 ? 0 : 2);
            } else {
                ctaLabel.textContent = 'Donate';
            }
        }

        presetRow.addEventListener('click', (e) => {
            const btn = e.target.closest('.preset-btn');
            if (!btn) return;
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            amountInput.value = btn.dataset.amount;
            updateCtaLabel();
            errorBanner.classList.remove('visible');
        });

        amountInput.addEventListener('input', () => {
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
            const v = parseFloat(amountInput.value);
            document.querySelectorAll('.preset-btn').forEach(b => {
                if (parseFloat(b.dataset.amount) === v) b.classList.add('selected');
            });
            updateCtaLabel();
            errorBanner.classList.remove('visible');
        });

        msgBox.addEventListener('input', () => {
            msgCounter.textContent = msgBox.value.length;
        });

        function showError(msg) {
            errorBanner.textContent = msg;
            errorBanner.classList.add('visible');
        }

        // Hand off to the existing shop.php donation flow (Stripe Elements modal)
        // by redirecting with the pre-filled amount + message as query params.
        function startDonation() {
            const amount = parseFloat(amountInput.value);
            if (isNaN(amount) || amount < 1 || amount > 100) {
                showError('Please choose an amount between $1.00 and $100.00.');
                return;
            }
            const msg = (msgBox.value || '').trim();
            const params = new URLSearchParams({
                plan: 'donation',
                amount: amount.toFixed(2)
            });
            if (msg) params.set('msg', msg.substring(0, 500));
            window.location.href = 'shop.php?' + params.toString();
        }
    </script>
</body>
</html>

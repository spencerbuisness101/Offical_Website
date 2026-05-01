<?php
/**
 * Policy Page Layout — Spencer's Website v7.0
 *
 * Shared dark-futuristic layout for all policy/legal pages.
 * Matches index.php aesthetic: glassmorphism, particle background,
 * violet/teal accent system.
 *
 * Usage:
 *   $policy_meta = [
 *       'title'         => 'Privacy Policy',
 *       'subtitle'      => 'How we collect, use, and protect your data',
 *       'last_updated'  => '2026-04-18',
 *       'version'       => '9.0',
 *       'icon'          => 'privacy', // 'privacy'|'terms'|'dmca'|'refunds'|'community'|'aup'|'cookies'|'children'|'accessibility'
 *       'toc'           => [ ['id'=>'section-1','label'=>'Overview'], ... ],
 *   ];
 *   render_policy_header($policy_meta);
 *   // ... policy body HTML with <section id="..."><h2>...</h2>...</section> blocks ...
 *   render_policy_footer();
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

/**
 * Emit the full <!DOCTYPE html>...<main class="policy-container"> opening.
 * Inside your policy page, after this call, output your <section> blocks.
 */
function render_policy_header(array $meta) {
    $title       = $meta['title']        ?? 'Policy';
    $subtitle    = $meta['subtitle']     ?? '';
    $lastUpdated = $meta['last_updated'] ?? date('Y-m-d');
    $version     = $meta['version']      ?? '1.0';
    $iconKey     = $meta['icon']         ?? 'privacy';
    $toc         = $meta['toc']          ?? [];

    // Pretty-print the last-updated date
    $lastUpdatedPretty = $lastUpdated;
    try {
        $dt = new DateTime($lastUpdated);
        $lastUpdatedPretty = $dt->format('F j, Y');
    } catch (Exception $e) { /* fall back to raw */ }

    $icons = [
        'privacy'       => '<svg viewBox="0 0 24 24"><path d="M12 2L4 6v6c0 5 3 9 8 10 5-1 8-5 8-10V6l-8-4z"/><path d="M9 12l2 2 4-4"/></svg>',
        'terms'         => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
        'dmca'          => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
        'refunds'       => '<svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 11-6.22-8.55"/><path d="M21 4v5h-5"/></svg>',
        'community'     => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        'aup'           => '<svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        'cookies'       => '<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1010 10 4 4 0 01-5-5 4 4 0 01-5-5"/><circle cx="8.5" cy="8.5" r=".5"/><circle cx="15.5" cy="15.5" r=".5"/><circle cx="15.5" cy="8.5" r=".5"/></svg>',
        'children'      => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 10-16 0"/></svg>',
        'accessibility' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><path d="M5 10l7-2 7 2M9 22l3-8 3 8M6 14h12"/></svg>',
    ];
    $iconSvg = $icons[$iconKey] ?? $icons['privacy'];

    // Derive CSRF not required for policy pages
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> — Spencer's Website</title>
    <meta name="description" content="<?php echo htmlspecialchars($subtitle ?: $title); ?>">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/css/tokens.css">
    <style>
        html { scroll-behavior: smooth; scrollbar-width: thin; scrollbar-color: rgba(123,110,246,0.3) transparent; }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            line-height: 1.75;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* === BACKGROUND LAYERS === */
        .bg-base {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(123,110,246,0.12) 0%, transparent 50%),
                radial-gradient(ellipse 60% 80% at 90% 30%, rgba(29,255,196,0.06) 0%, transparent 50%),
                radial-gradient(ellipse 70% 50% at 50% 100%, rgba(168,85,247,0.10) 0%, transparent 60%),
                linear-gradient(180deg, #04040A 0%, #08081A 50%, #04040A 100%);
            pointer-events: none;
        }
        .bg-nebula {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0.4;
            overflow: hidden;
            filter: blur(80px);
        }
        .bg-nebula .blob {
            position: absolute;
            border-radius: 50%;
            mix-blend-mode: screen;
            opacity: 0.6;
            animation: blob-drift 30s ease-in-out infinite;
        }
        .bg-nebula .blob.b1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #7B6EF6, transparent 70%);
            top: -10%; left: -15%;
        }
        .bg-nebula .blob.b2 {
            width: 450px; height: 450px;
            background: radial-gradient(circle, #1DFFC4, transparent 70%);
            top: 50%; right: -10%;
            animation-delay: -10s;
        }
        .bg-nebula .blob.b3 {
            width: 550px; height: 550px;
            background: radial-gradient(circle, #A855F7, transparent 70%);
            bottom: -20%; left: 30%;
            animation-delay: -18s;
        }
        @keyframes blob-drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(50px, -30px) scale(1.08); }
            66% { transform: translate(-30px, 40px) scale(0.95); }
        }
        .bg-vignette {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 40%, rgba(0,0,0,0.5) 100%);
        }

        /* === NAVBAR === */
        .nav {
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(4,4,10,0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 0.5px solid rgba(255,255,255,0.06);
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-weight: 300;
            letter-spacing: 0.08em;
            font-size: 15px;
        }
        .nav-brand svg { width: 24px; height: 24px; }
        .nav-brand:hover { text-decoration: none; }
        .nav-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 13px;
            padding: 8px 14px;
            border: 0.5px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            transition: all 0.2s ease;
        }
        .nav-back:hover {
            color: var(--accent);
            border-color: var(--accent);
            text-decoration: none;
        }
        .nav-back svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.8; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .nav-print {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 13px;
            padding: 8px 14px;
            border: 0.5px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            background: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .nav-print:hover {
            color: var(--teal);
            border-color: var(--teal);
        }
        .nav-print svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.8; }
        @media print {
            .nav, .policy-toc, .bg-base, .bg-nebula, .bg-vignette, .policy-footer, .nav-print { display: none !important; }
            .policy-layout { grid-template-columns: 1fr !important; }
            .policy-content { border: none; background: none; backdrop-filter: none; padding: 0; }
            body { background: #fff; color: #111; }
            .policy-content h2::before { background: #7B6EF6; }
        }

        /* === HERO === */
        .policy-hero {
            position: relative;
            z-index: 1;
            padding: 60px 24px 40px;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
        }
        .policy-hero-icon {
            width: 64px; height: 64px;
            margin: 0 auto 20px;
            padding: 14px;
            background: linear-gradient(135deg, rgba(123,110,246,0.18), rgba(29,255,196,0.1));
            border: 0.5px solid rgba(123,110,246,0.35);
            border-radius: 18px;
            box-shadow: 0 0 40px rgba(123,110,246,0.2);
        }
        .policy-hero-icon svg {
            width: 100%; height: 100%;
            fill: none;
            stroke: var(--accent);
            stroke-width: 1.6;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .policy-hero h1 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 200;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
            line-height: 1.1;
        }
        .policy-hero h1 .accent {
            background: linear-gradient(135deg, #7B6EF6, #1DFFC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .policy-hero-subtitle {
            font-size: 17px;
            color: var(--text-muted);
            font-weight: 300;
            max-width: 640px;
            margin: 0 auto 24px;
            line-height: 1.5;
        }
        .policy-chips {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .policy-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.02em;
            border: 0.5px solid;
        }
        .policy-chip.updated {
            background: rgba(29,255,196,0.08);
            border-color: rgba(29,255,196,0.3);
            color: var(--teal);
        }
        .policy-chip.version {
            background: rgba(123,110,246,0.08);
            border-color: rgba(123,110,246,0.3);
            color: var(--accent);
        }
        .policy-chip .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: chip-pulse 2s ease-in-out infinite;
        }
        @keyframes chip-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* === LAYOUT === */
        .policy-layout {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px 80px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 48px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .policy-layout {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        /* === TOC SIDEBAR === */
        .policy-toc {
            position: sticky;
            top: 90px;
            background: var(--glass-bg);
            border: var(--glass-border);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-sm);
            padding: 20px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        @media (max-width: 900px) {
            .policy-toc { position: static; max-height: none; }
        }
        .policy-toc-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 12px;
        }
        .policy-toc ul { list-style: none; }
        .policy-toc li a {
            display: block;
            padding: 7px 10px;
            font-size: 13.5px;
            color: var(--text-muted);
            border-radius: 8px;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
        }
        .policy-toc li a:hover {
            color: var(--text);
            background: rgba(123,110,246,0.08);
            border-left-color: var(--accent);
            text-decoration: none;
            padding-left: 14px;
        }
        .policy-toc li a.active {
            color: var(--accent);
            background: rgba(123,110,246,0.12);
            border-left-color: var(--accent);
        }

        /* === CONTENT === */
        .policy-content {
            background: var(--glass-bg);
            border: var(--glass-border);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius);
            padding: 48px 52px;
            min-height: 60vh;
        }
        @media (max-width: 700px) {
            .policy-content { padding: 28px 22px; }
        }
        .policy-content section {
            scroll-margin-top: 100px;
            margin-bottom: 40px;
        }
        .policy-content section:last-child { margin-bottom: 0; }
        .policy-content h2 {
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 14px;
            color: var(--text);
            padding-bottom: 10px;
            border-bottom: 0.5px solid rgba(123,110,246,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .policy-content h2::before {
            content: '';
            width: 6px; height: 22px;
            background: linear-gradient(180deg, var(--accent), var(--teal));
            border-radius: 3px;
        }
        .policy-content h3 {
            font-size: 17px;
            font-weight: 500;
            margin: 28px 0 10px;
            color: var(--text);
        }
        .policy-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 18px 0 8px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .policy-content p, .policy-content li {
            color: #CBD5E1;
            font-size: 15px;
            line-height: 1.75;
        }
        .policy-content p { margin-bottom: 14px; }
        .policy-content ul, .policy-content ol {
            margin: 10px 0 16px 22px;
        }
        .policy-content li { margin-bottom: 6px; }
        .policy-content strong { color: var(--text); font-weight: 600; }
        .policy-content em { color: var(--text); font-style: italic; }
        .policy-content code {
            background: rgba(123,110,246,0.1);
            color: var(--accent);
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 13px;
            font-family: 'SF Mono', Consolas, monospace;
        }

        /* Info box */
        .policy-content .info-box,
        .policy-content .warning-box,
        .policy-content .success-box,
        .policy-content .critical-box {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin: 16px 0;
            border-left: 3px solid;
        }
        .policy-content .info-box {
            background: rgba(59,130,246,0.08);
            border-color: #3b82f6;
            color: #BFDBFE;
        }
        .policy-content .warning-box {
            background: rgba(245,158,11,0.08);
            border-color: #F59E0B;
            color: #FDE68A;
        }
        .policy-content .success-box {
            background: rgba(29,255,196,0.08);
            border-color: var(--teal);
            color: #A7F3D0;
        }
        .policy-content .critical-box {
            background: rgba(239,68,68,0.08);
            border-color: #EF4444;
            color: #FECACA;
        }
        .policy-content .info-box strong,
        .policy-content .warning-box strong,
        .policy-content .success-box strong,
        .policy-content .critical-box strong { color: inherit; }

        /* Pricing table (for refund-policy etc) */
        .policy-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .policy-content th, .policy-content td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 0.5px solid rgba(255,255,255,0.08);
        }
        .policy-content th {
            background: rgba(123,110,246,0.12);
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.06em;
        }
        .policy-content tbody tr:hover { background: rgba(123,110,246,0.05); }

        /* Contact line */
        .policy-content .contact-email {
            display: inline-block;
            background: rgba(123,110,246,0.1);
            padding: 4px 10px;
            border-radius: 6px;
            color: var(--accent);
            font-family: 'SF Mono', Consolas, monospace;
            font-size: 13px;
        }

        /* === FOOTER === */
        .policy-footer {
            position: relative;
            z-index: 1;
            padding: 40px 24px 60px;
            text-align: center;
            border-top: 0.5px solid rgba(255,255,255,0.06);
            margin-top: 40px;
        }
        .policy-footer-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 16px;
        }
        .policy-footer-links a {
            color: var(--text-dim);
            font-size: 13px;
        }
        .policy-footer-links a:hover { color: var(--text); }
        .policy-footer-copy {
            font-size: 12px;
            color: var(--text-dim);
        }

        /* === REDUCED MOTION === */
        @media (prefers-reduced-motion: reduce) {
            .bg-nebula .blob { animation: none; }
            .policy-chip .dot { animation: none; }
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body>
    <!-- Background layers -->
    <div class="bg-base"></div>
    <div class="bg-nebula" aria-hidden="true">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>
    </div>
    <div class="bg-vignette" aria-hidden="true"></div>

    <!-- Navbar -->
    <nav class="nav">
        <a href="index.php" class="nav-brand">
            <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14 2L26 8V20L14 26L2 20V8L14 2Z" stroke="#7B6EF6" stroke-width="1" fill="rgba(123,110,246,0.08)"/>
                <path d="M14 8L20 11V17L14 20L8 17V11L14 8Z" fill="#7B6EF6" opacity="0.6"/>
                <circle cx="14" cy="14" r="2" fill="#1DFFC4"/>
            </svg>
            <span>SPENCER'S</span>
        </a>
        <div class="nav-actions">
            <button class="nav-print" onclick="window.print()" title="Print or Save as PDF">
                <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF
            </button>
            <a href="index.php" class="nav-back">
                <svg viewBox="0 0 24 24"><path d="M19 12H5m7-7l-7 7 7 7"/></svg>
                Back to home
            </a>
        </div>
    </nav>

    <!-- Hero -->
    <header class="policy-hero">
        <div class="policy-hero-icon"><?php echo $iconSvg; ?></div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <?php if ($subtitle): ?>
        <p class="policy-hero-subtitle"><?php echo htmlspecialchars($subtitle); ?></p>
        <?php endif; ?>
        <div class="policy-chips">
            <span class="policy-chip updated"><span class="dot"></span> Updated <?php echo htmlspecialchars($lastUpdatedPretty); ?></span>
            <span class="policy-chip version">Version <?php echo htmlspecialchars($version); ?></span>
        </div>
    </header>

    <!-- Layout grid: TOC sidebar + content -->
    <div class="policy-layout">
        <aside class="policy-toc" aria-label="Table of contents">
            <div class="policy-toc-label">On this page</div>
            <ul>
                <?php foreach ($toc as $item): ?>
                <li><a href="#<?php echo htmlspecialchars($item['id']); ?>"><?php echo htmlspecialchars($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <article class="policy-content">
<?php
}

/**
 * Close the policy layout and emit the footer.
 */
function render_policy_footer() {
    ?>
        </article>
    </div>

    <footer class="policy-footer">
        <div class="policy-footer-links">
            <a href="privacy.php">Privacy</a>
            <a href="terms.php">Terms</a>
            <a href="acceptable-use.php">Acceptable Use</a>
            <a href="cookie-policy.php">Cookies</a>
            <a href="community-standards.php">Community Standards</a>
            <a href="refund-policy.php">Refunds</a>
            <a href="dmca.php">DMCA</a>
            <a href="childrens-privacy.php">Children's Privacy</a>
            <a href="accessibility.php">Accessibility</a>
            <a href="supporthelp.php">Support</a>
        </div>
        <div class="policy-footer-copy">&copy; <?php echo date('Y'); ?> Spencer's Website. All rights reserved.</div>
    </footer>

    <script>
        // Active-section highlight in TOC
        (function() {
            const links = document.querySelectorAll('.policy-toc a');
            const sections = document.querySelectorAll('.policy-content section[id]');
            if (!links.length || !sections.length) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });

            sections.forEach(s => observer.observe(s));
        })();
    </script>
</body>
</html>
<?php
}

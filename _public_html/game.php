<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
$__bgfile = __DIR__ . '/load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }

// Fetch active designer background
$active_designer_background = null;
$available_backgrounds = [];

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Get active designer background
    $bgStmt = $db->query("
        SELECT db.image_url, db.title, u.username as designer_name
        FROM designer_backgrounds db
        LEFT JOIN users u ON db.user_id = u.id
        WHERE db.is_active = 1 AND db.status = 'approved'
        LIMIT 1
    ");
    $active_designer_background = $bgStmt->fetch(PDO::FETCH_ASSOC);

    // Get all approved backgrounds for user selection
    $allBgStmt = $db->query("
        SELECT db.id, db.image_url, db.title, u.username as designer_name
        FROM designer_backgrounds db
        LEFT JOIN users u ON db.user_id = u.id
        WHERE db.status = 'approved'
        ORDER BY db.is_active DESC, db.created_at DESC
    ");
    $available_backgrounds = $allBgStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Database fetch error: " . $e->getMessage());
}

// Get user info for display
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'];
?>

<!doctype html>
<html lang="en">
<head>
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <script src="common.js"></script>
    <link rel="stylesheet" href="control_buttons.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">

    <link rel="icon" href="/assets/images/favicon.webp">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore Spencer's Game Collection - HTML5 games and entertainment">
    <title>Game Collection - Spencer's Website</title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/command-palette.css">
    <link rel="stylesheet" href="css/cinematic-bg.css?v=<?php echo SITE_VERSION; ?>">
    <link rel="stylesheet" href="main/css/main-page.css?v=<?php echo SITE_VERSION; ?>">

    <!-- Cinematic background module -->
    <script src="js/cinematic-bg.js?v=<?php echo SITE_VERSION; ?>" defer></script>
<style>
    /* ========================================
       Game Center — Core UI Overhaul v7.0
       ======================================== */
    
    :root {
        --game-card-bg: var(--glass-bg);
        --game-card-border: var(--border-subtle);
        --game-card-shadow: var(--shadow-sm);
        --spotlight-height: 480px;
        --grid-gap: var(--space-6);
    }

    body {
        background-color: var(--bg);
        color: var(--text);
        font-family: var(--font-sans);
        margin: 0;
        overflow-x: hidden;
    }

    .mp-root {
        max-width: var(--container-max);
        margin: 0 auto;
        padding: var(--space-10) var(--space-6);
        position: relative;
        z-index: 1;
    }

    /* --- Back Link --- */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--text-muted);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: var(--space-8);
        transition: var(--transition);
    }
    .back-link:hover {
        color: var(--teal);
        transform: translateX(-4px);
    }

    /* --- Page Header --- */
    .page-header {
        text-align: center;
        margin-bottom: var(--space-10);
        position: relative;
        z-index: 2;
    }
    .page-header h1 {
        font-size: clamp(32px, 5vw, 48px);
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: var(--space-3);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .page-header p {
        color: var(--text-muted);
        font-size: 18px;
        max-width: 600px;
        margin: 0 auto var(--space-6);
    }

    .header-actions {
        display: flex;
        justify-content: center;
        gap: var(--space-4);
        margin-top: var(--space-6);
    }

    .btn-action {
        padding: 10px 20px;
        border-radius: var(--radius-pill);
        font-weight: 600;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: var(--transition);
        border: var(--border);
        background: var(--bg-elevated);
        color: #fff;
    }

    .btn-action:hover {
        background: var(--glass-bg-hover);
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    .btn-action.primary {
        background: var(--gradient);
        border-color: transparent;
        box-shadow: 0 4px 15px var(--accent-glow);
    }


    /* --- Spotlight (Hero Carousel) --- */
    .hero-carousel {
        position: relative;
        height: var(--spotlight-height);
        margin-bottom: var(--space-10);
        border-radius: var(--radius-xl);
        overflow: hidden;
        border: var(--border-violet);
        box-shadow: var(--shadow-violet);
    }
    .hero-card {
        position: absolute;
        inset: 0;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.8s var(--ease-out), visibility 0.8s;
        display: flex;
        align-items: center;
        justify-content: center;
        background-size: cover;
        background-position: center;
    }
    .hero-card.active {
        opacity: 1;
        visibility: visible;
        z-index: 2;
    }
    .hero-card-inner {
        width: 100%;
        height: 100%;
        padding: var(--space-10);
        background: linear-gradient(to right, rgba(4, 4, 10, 0.9) 0%, rgba(4, 4, 10, 0.4) 60%, transparent 100%);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        backdrop-filter: blur(4px);
    }
    .hero-emoji {
        font-size: 48px;
        margin-bottom: var(--space-4);
        filter: drop-shadow(0 0 10px var(--accent));
    }
    .hero-card-inner h2 {
        font-size: clamp(28px, 4vw, 42px);
        margin: 0 0 var(--space-3);
        color: #fff;
    }
    .hero-card-inner p {
        max-width: 500px;
        color: var(--text-soft);
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: var(--space-6);
    }
    .hero-stats {
        display: flex;
        gap: var(--space-6);
        margin-bottom: var(--space-8);
    }
    .hero-stat {
        display: flex;
        flex-direction: column;
    }
    .hero-stat-val {
        font-size: 18px;
        font-weight: 700;
        color: var(--teal);
    }
    .hero-stat-lbl {
        font-size: 12px;
        text-transform: uppercase;
        color: var(--text-dim);
        letter-spacing: 0.05em;
    }
    .hero-play-btn {
        padding: 14px 32px;
        background: var(--gradient);
        color: #fff;
        text-decoration: none;
        border-radius: var(--radius-pill);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        transition: var(--transition);
        box-shadow: 0 10px 20px -5px var(--accent-glow);
    }
    .hero-play-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px -5px var(--accent-glow);
    }

    .carousel-dots {
        position: absolute;
        bottom: var(--space-6);
        right: var(--space-8);
        display: flex;
        gap: var(--space-3);
        z-index: 10;
    }
    .carousel-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        border: none;
        padding: 0;
        cursor: pointer;
        transition: var(--transition);
    }
    .carousel-dot.active {
        background: var(--teal);
        transform: scale(1.3);
        box-shadow: 0 0 10px var(--teal-glow);
    }

    /* --- Search & Filters --- */
    .controls-wrapper {
        position: sticky;
        top: calc(var(--ib-height) + 10px);
        z-index: 100;
        margin-bottom: var(--space-8);
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }
    
    .search-container {
        position: relative;
        max-width: 600px;
        margin: 0 auto;
        width: 100%;
    }
    .search-input {
        width: 100%;
        padding: 16px 20px 16px 52px;
        background: var(--glass-bg);
        border: var(--border);
        border-radius: var(--radius-lg);
        color: #fff;
        font-size: 16px;
        backdrop-filter: var(--glass-blur);
        transition: var(--transition);
    }
    .search-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-halo);
        outline: none;
    }
    .search-icon {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-dim);
        font-size: 18px;
    }
    .search-clear {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-dim);
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
    }
    .search-clear.visible {
        opacity: 1;
        visibility: visible;
    }

    .category-tabs {
        display: flex;
        gap: var(--space-2);
        overflow-x: auto;
        padding: var(--space-2) 0;
        scrollbar-width: none;
        justify-content: center;
    }
    .category-tabs::-webkit-scrollbar { display: none; }
    
    .category-tab {
        padding: 8px 20px;
        background: var(--bg-elevated);
        border: var(--border);
        border-radius: var(--radius-pill);
        color: var(--text-soft);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .category-tab:hover {
        background: var(--glass-bg-hover);
        border-color: var(--accent);
        color: #fff;
    }
    .category-tab.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
        box-shadow: 0 4px 12px var(--accent-glow);
    }
    .tab-count {
        font-size: 11px;
        background: rgba(255,255,255,0.15);
        padding: 1px 6px;
        border-radius: 6px;
        opacity: 0.8;
    }

    /* --- Games Grid (Bento Style) --- */
    .games-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: var(--grid-gap);
    }
    
    .game-card {
        background: var(--game-card-bg);
        border: var(--game-card-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: transform 0.4s var(--ease-out), box-shadow 0.4s var(--ease-out), border-color 0.4s;
        display: flex;
        flex-direction: column;
        position: relative;
        backdrop-filter: var(--glass-blur);
        opacity: 0;
        transform: translateY(20px);
    }
    .game-card.visible {
        opacity: 1;
        transform: translateY(0);
    }
    .game-card:hover {
        transform: translateY(-8px) scale(1.02);
        border-color: var(--accent-soft);
        box-shadow: var(--shadow-lg), 0 0 20px var(--accent-halo);
        z-index: 10;
    }
    
    .card-thumb {
        height: 160px;
        background: var(--bg-raised);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        font-size: 64px;
        overflow: hidden;
    }
    .card-thumb span {
        transition: transform 0.5s var(--ease-out);
        z-index: 2;
    }
    .game-card:hover .card-thumb span {
        transform: scale(1.2) rotate(5deg);
    }
    .card-thumb::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at center, var(--accent-glow), transparent 70%);
        opacity: 0;
        transition: opacity 0.4s;
    }
    .game-card:hover .card-thumb::after { opacity: 0.4; }

    .popularity, .ribbon-new {
        position: absolute;
        z-index: 5;
        padding: 4px 12px;
        border-radius: var(--radius-pill);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(8px);
    }
    .popularity {
        top: 12px;
        left: 12px;
        background: rgba(255, 107, 179, 0.2);
        border: 1px solid rgba(255, 107, 179, 0.3);
        color: var(--pink);
    }
    .ribbon-new {
        top: 12px;
        right: 12px;
        background: rgba(29, 255, 196, 0.2);
        border: 1px solid rgba(29, 255, 196, 0.3);
        color: var(--teal);
    }

    .card-body {
        padding: var(--space-5);
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .card-category-badge {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--accent-soft);
        margin-bottom: var(--space-2);
        display: block;
    }
    .card-body h3 {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 var(--space-2);
        color: #fff;
    }
    .card-body p {
        font-size: 14px;
        color: var(--text-muted);
        line-height: 1.5;
        margin: 0 0 var(--space-5);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .game-button {
        margin-top: auto;
        width: 100%;
        padding: 12px;
        background: var(--bg-elevated);
        border: var(--border);
        border-radius: var(--radius-md);
        color: #fff;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: var(--transition);
    }
    .game-card:hover .game-button {
        background: var(--gradient);
        border-color: transparent;
        box-shadow: 0 4px 15px var(--accent-glow);
    }

    /* --- Modals & Notification --- */
    .background-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.85);
        z-index: 10000;
        backdrop-filter: blur(12px);
        padding: var(--space-6);
        align-items: center;
        justify-content: center;
    }
    .background-modal-content {
        background: var(--bg-raised);
        border: var(--border-violet);
        border-radius: var(--radius-xl);
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: var(--space-8);
        position: relative;
    }

    .background-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: var(--border);
    }

    .background-modal-title {
        font-size: 24px;
        font-weight: 700;
        color: #fff;
    }

    .background-modal-close {
        background: none;
        border: none;
        color: var(--text-dim);
        font-size: 24px;
        cursor: pointer;
        transition: var(--transition);
    }
    .background-modal-close:hover { color: #fff; }

    .backgrounds-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--space-5);
    }

    .background-item {
        background: var(--bg-elevated);
        border: var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        cursor: pointer;
        transition: var(--transition);
    }
    .background-item:hover {
        border-color: var(--accent);
        transform: translateY(-4px);
    }
    .background-item.active {
        border-color: var(--teal);
        box-shadow: 0 0 15px var(--teal-glow);
    }

    .background-preview {
        height: 120px;
        background-size: cover;
        background-position: center;
    }

    .background-info { padding: var(--space-4); }
    .background-title { font-weight: 600; color: #fff; margin-bottom: 4px; }
    .background-designer { font-size: 12px; color: var(--text-dim); }

    .btn-set-background {
        width: 100%;
        margin-top: var(--space-3);
        padding: 8px;
        background: var(--bg-raised);
        border: var(--border);
        border-radius: var(--radius-sm);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .background-item:hover .btn-set-background {
        background: var(--accent);
        border-color: var(--accent);
    }
    
    /* --- Footer Navigation --- */
    .footer-back {
        text-align: center;
        margin-top: var(--space-10);
    }
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 16px 40px;
        background: var(--bg-elevated);
        border: var(--border);
        border-radius: var(--radius-pill);
        color: #fff;
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
    }
    .back-button:hover {
        background: var(--glass-bg-hover);
        border-color: var(--accent);
        transform: translateY(-3px);
    }

    /* --- Responsive Fixes --- */
    @media (max-width: 768px) {
        .hero-carousel { height: 400px; }
        .hero-card-inner { padding: var(--space-6); }
        .hero-stats { gap: var(--space-4); }
        .hero-play-btn { width: 100%; justify-content: center; }
        .controls-wrapper { top: var(--ib-height); }
        .category-tabs { justify-content: flex-start; }
    }

    /* --- Toast Notifications --- */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: var(--radius-lg);
        background: var(--bg-raised);
        border: var(--border);
        color: #fff;
        font-weight: 600;
        z-index: 10000;
        transform: translateX(450px);
        transition: transform 0.4s var(--ease-out);
        box-shadow: var(--shadow-xl);
        display: flex;
        align-items: center;
        gap: 12px;
        backdrop-filter: blur(12px);
    }
    .toast.visible { transform: translateX(0); }
    .toast-success { border-color: var(--teal); background: rgba(13, 148, 136, 0.2); }
    .toast-error { border-color: var(--pink); background: rgba(219, 39, 119, 0.2); }
    .toast-info { border-color: var(--accent); background: rgba(37, 99, 235, 0.2); }

</style>
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>'
      data-cinematic-bg="dim"
      data-active-background='<?php echo htmlspecialchars($active_designer_background['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
    <?php require_once __DIR__ . '/includes/identity_bar_v2.php'; ?>
    <!-- Background Theme Override Element -->
    <div class="bg-theme-override" id="bgThemeOverride"></div>

    <!-- Background Selection Modal -->
    <div id="backgroundModal" class="background-modal">
        <div class="background-modal-content">
            <div class="background-modal-header">
                <h2 class="background-modal-title"><i class="fa-solid fa-palette"></i> Choose Your Background</h2>
                <button class="background-modal-close" onclick="closeBackgroundModal()">&times;</button>
            </div>

            <div class="backgrounds-grid" id="backgroundsGrid">
                <!-- Background items will be populated by JavaScript -->
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn-remove-background" onclick="removeCustomBackground()" style="padding: 12px 24px; font-size: 1rem;">
                    <i class="fa-solid fa-trash"></i> Remove Custom Background
                </button>
            </div>
        </div>
    </div>


    <main id="mainContent" class="mp-root" role="main">

        <!-- Back Link -->
        <a href="main.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>

        <!-- Page Header -->
        <header class="page-header">
            <h1><i class="fa-solid fa-gamepad"></i> Game Center</h1>
            <p>Discover our collection of handpicked HTML5 games and interactive experiences</p>
            <div class="header-actions">
                <button class="btn-action primary" onclick="openBackgroundModal()">
                    <i class="fa-solid fa-palette"></i> Choose Background
                </button>
                <a href="set.php" class="btn-action">
                    <i class="fa-solid fa-gear"></i> Settings
                </a>
            </div>
        </header>

        <!-- Announcements Section -->
        <?php include_once 'includes/announcements.php'; ?>

        <!-- Active Background Info -->
        <?php if ($active_designer_background): ?>
        <div class="background-info-section">
            <h3><i class="fa-solid fa-palette"></i> Active Community Background</h3>
            <p><strong>"<?php echo htmlspecialchars($active_designer_background['title']); ?>"</strong></p>
            <p>Designed by: <?php echo htmlspecialchars($active_designer_background['designer_name']); ?></p>
            <button class="btn-set-background" onclick="setAsBackground('<?php echo $active_designer_background['image_url']; ?>', '<?php echo htmlspecialchars($active_designer_background['title']); ?>')" style="margin-top: 10px;">
                Use This Background
            </button>
        </div>
        <?php endif; ?>

        <!-- ========================================
             Hero Carousel - Featured Games
             ======================================== -->
        <section class="hero-carousel" aria-label="Featured Games">
            <!-- Card 1: Crazy Cattle 3D -->
            <div class="hero-card active" data-hero="0">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-cow"></i></div>
                    <h2>Crazy Cattle 3D</h2>
                    <p>Wild cattle action in 3D! One of our most popular games with fast-paced gameplay and hilarious physics.</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.9/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">POPULAR</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">3D</div><div class="hero-stat-lbl">Graphics</div></div>
                    </div>
                    <a href="Games/crazycattle3d.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Crazy Cattle 3D</a>
                </div>
            </div>

            <!-- Card 2: Tomb of the Mask -->
            <div class="hero-card" data-hero="1">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-crosshairs"></i></div>
                    <h2>Tomb of the Mask</h2>
                    <p>Navigate through challenging maze-like levels in this fast-paced arcade adventure. Collect power-ups and avoid traps!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.8/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Easy</div><div class="hero-stat-lbl">Difficulty</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">HTML5</div><div class="hero-stat-lbl">Engine</div></div>
                    </div>
                    <a href="Games/tomb.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Tomb of the Mask</a>
                </div>
            </div>

            <!-- Card 3: Solar Smash -->
            <div class="hero-card" data-hero="2">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-earth-americas"></i></div>
                    <h2>Solar Smash</h2>
                    <p>An explosive planetary destruction simulator where you wield cosmic weapons to obliterate celestial bodies. Brand new!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">NEW</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">4.7/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Sandbox</div><div class="hero-stat-lbl">Genre</div></div>
                    </div>
                    <a href="Games/solar.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Solar Smash</a>
                </div>
            </div>

            <!-- Card 4: Retro Bowl -->
            <div class="hero-card" data-hero="3">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-football"></i></div>
                    <h2>Retro Bowl</h2>
                    <p>Classic football action with retro-style graphics. Build your team, manage your roster, and compete for the championship!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.9/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Fan Fav</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Retro</div><div class="hero-stat-lbl">Style</div></div>
                    </div>
                    <a href="Games/retro.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Retro Bowl</a>
                </div>
            </div>

            <!-- Carousel Dots -->
            <div class="carousel-dots">
                <button class="carousel-dot active" data-dot="0" aria-label="Featured game 1"></button>
                <button class="carousel-dot" data-dot="1" aria-label="Featured game 2"></button>
                <button class="carousel-dot" data-dot="2" aria-label="Featured game 3"></button>
                <button class="carousel-dot" data-dot="3" aria-label="Featured game 4"></button>
            </div>
        </section>

        <!-- ========================================
             Search Bar
             ======================================== -->
        <div class="search-container">
            <input type="text" class="search-input" id="gameSearch" placeholder="Search games..." autocomplete="off">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <button class="search-clear" id="searchClear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- ========================================
             Category Tabs
             ======================================== -->
        <div class="category-tabs" id="categoryTabs">
            <button class="category-tab active" data-category="all">All Games <span class="tab-count" id="count-all"></span></button>
            <button class="category-tab" data-category="action">Action <span class="tab-count" id="count-action"></span></button>
            <button class="category-tab" data-category="arcade">Arcade <span class="tab-count" id="count-arcade"></span></button>
            <button class="category-tab" data-category="strategy">Strategy <span class="tab-count" id="count-strategy"></span></button>
            <button class="category-tab" data-category="puzzle">Puzzle <span class="tab-count" id="count-puzzle"></span></button>
            <button class="category-tab" data-category="horror">Horror <span class="tab-count" id="count-horror"></span></button>
            <button class="category-tab" data-category="fnaf">FNAF 1-4 <span class="tab-count" id="count-fnaf"></span></button>
            <button class="category-tab" data-category="fix">Fixed Games <span class="tab-count" id="count-fix"></span></button>
            <button class="category-tab" data-category="NEW!">Newly Added <span class="tab-count" id="count-NEW!"></span></button>
            <button class="category-tab" data-category="broke">Broken <span class="tab-count" id="count-broke"></span></button>
        </div>

        <!-- No Results Message -->
        <div class="search-no-results" id="noResults">
            <i class="fa-solid fa-ghost"></i>
            No games found matching your search.
        </div>

        <!-- ========================================
             Games Grid
             ======================================== -->
        <div class="games-grid" id="gamesGrid">

            <!-- Tomb of the Mask -->
            <div class="game-card" data-category="arcade" data-title="Tomb of the Mask" data-popularity="95">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#127919;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Tomb of the Mask</h3>
                    <p>Fast-paced arcade adventure through challenging mazes. Collect power-ups and avoid traps in this addictive endless runner!</p>
                    <a href="Games/tomb.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- CS:GO Clicker -->
            <div class="game-card" data-category="strategy" data-title="CS:GO Clicker" data-popularity="78">
                <div class="card-thumb">
                    <span>&#128433;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>CS:GO Clicker</h3>
                    <p>Play CSGO Clicker, the addictive case opening game. Click to earn money, unlock weapon cases, and build your inventory.</p>
                    <a href="Games/cs.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Basketball Io -->
            <div class="game-card" data-category="strategy" data-title="Basketball Io" data-popularity="70">
                <div class="card-thumb">
                    <span>&#127936;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Basketball Io</h3>
                    <p>Play Basketball.io!</p>
                    <a href="Games/basketball.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Block Blast Adventure -->
            <div class="game-card" data-category="fix" data-title="Block Blast Adventure" data-popularity="72">
                <div class="card-thumb">
                    <span>&#129513;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>Block Blast Adventure (FIXED)</h3>
                    <p>Strategic puzzle game where you match colorful blocks. Plan your moves carefully to achieve high scores and unlock new levels.</p>
                    <a href="Games/block.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Slope Runner -->
            <div class="game-card" data-category="arcade" data-title="Slope Runner" data-popularity="88">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#9975;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Slope Runner</h3>
                    <p>3D running game with simple controls but challenging gameplay. Navigate through slopes and avoid obstacles at high speed.</p>
                    <a href="Games/slope.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Mortal Kombat -->
            <div class="game-card" data-category="action" data-title="Mortal Kombat" data-popularity="90">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#129355;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Mortal Kombat</h3>
                    <p>Enter the arena and fight to the death in this legendary action game. Choose your fighter, master deadly combos, and claim victory!</p>
                    <a href="Games/com.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Cookie Clicker -->
            <div class="game-card" data-category="strategy" data-title="Cookie Clicker" data-popularity="82">
                <div class="card-thumb">
                    <span>&#127850;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Cookie Clicker</h3>
                    <p>Addictive incremental game where you bake cookies, purchase upgrades, and build your cookie empire. How many cookies can you create?</p>
                    <a href="Games/cookie.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Granny -->
            <div class="game-card" data-category="horror" data-title="Granny" data-popularity="85">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128682;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge horror">Horror</span>
                    <h3>Granny</h3>
                    <p>Granny is a tense first-person survival horror -- sneak through a locked house, solve puzzles, and escape without being caught.</p>
                    <a href="Games/gran.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Retro Bowl -->
            <div class="game-card" data-category="arcade" data-title="Retro Bowl" data-popularity="96">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> TOP</div>
                    <span>&#127944;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Retro Bowl</h3>
                    <p>Classic football action with retro-style graphics. Build your team, manage your roster, and compete for the championship!</p>
                    <a href="Games/retro.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Retro Bowl College -->
            <div class="game-card" data-category="arcade" data-title="Retro Bowl College" data-popularity="89">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#127944;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Retro Bowl College</h3>
                    <p>Retro Bowl College is a retro-style football simulation game that puts you in the shoes of a college football coach.</p>
                    <a href="Games/retro2.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Geometry Dash LITE -->
            <div class="game-card" data-category="arcade" data-title="Geometry Dash LITE" data-popularity="91">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128313;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Geometry Dash LITE</h3>
                    <p>Geometry Dash Lite is a free action rhythm-based platformer</p>
                    <a href="Games/geometrydash.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Awesome Tank 2 -->
            <div class="game-card" data-category="FIXED" data-title="Awesome Tank 2" data-popularity="74">
                <div class="card-thumb">
                    <span>&#128668;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>Awesome Tank 2 (FIXED!)</h3>
                    <p>Awesome Tanks 2 is an engaging sequel to the original game, featuring 15 unique levels filled with various enemies and challenges</p>
                    <a href="Games/tank.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- FNAF 1 -->
            <div class="game-card" data-category="fnaf" data-title="Five Night's Of Freddy's" data-popularity="92">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128059;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fnaf">FNAF</span>
                    <h3>Five Night's Of Freddy's</h3>
                    <p>Five Nights at Freddy's 1 is an indie horror game developed by Scott Cawthon.</p>
                    <a href="Games/fnaf1.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Super Mario 64 -->
            <div class="game-card" data-category="arcade" data-title="Super Mario 64" data-popularity="93">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> TOP</div>
                    <span>&#127812;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Super Mario 64</h3>
                    <p>Super Mario 64 is a 3D platforming adventure where Mario explores worlds, collects stars, and saves Princess Peach.</p>
                    <a href="Games/super.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Ragdoll Archers -->
            <div class="game-card" data-category="strategy" data-title="Ragdoll Archers" data-popularity="76">
                <div class="card-thumb">
                    <span>&#127993;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Ragdoll Archers</h3>
                    <p>Ragdoll Archers is a fun and chaotic physics-based archery game where floppy stickman characters battle using bows and arrows.</p>
                    <a href="Games/ragdoll.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Final Earth 2 -->
            <div class="game-card" data-category="strategy" data-title="Final Earth 2" data-popularity="73">
                <div class="card-thumb">
                    <span>&#127759;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Final Earth 2</h3>
                    <p>The Final Earth 2 is a city builder and resource management game developed by Florian van Strien.</p>
                    <a href="Games/finalearth2.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Doom -->
            <div class="game-card" data-category="arcade" data-title="Doom" data-popularity="94">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> TOP</div>
                    <span>&#128128;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Doom</h3>
                    <p>Doom is a first-person shooter game developed and published by id Software.</p>
                    <a href="Games/doom.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Funny Battle 2 -->
            <div class="game-card" data-category="arcade" data-title="Funny Battle 2" data-popularity="71">
                <div class="card-thumb">
                    <span>&#9876;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Funny Battle 2</h3>
                    <p>Funny Battle Simulator 2 is a humorous battle simulation game where players tactically place an army to take them into battle.</p>
                    <a href="Games/funnybattle.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Baldi Basics -->
            <div class="game-card" data-category="arcade" data-title="Baldi Basics" data-popularity="80">
                <div class="card-thumb">
                    <span>&#128207;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Baldi Basics</h3>
                    <p>Baldi's Basics in Education and Learning is a 2018 puzzle horror game developed by Micah McGonigal.</p>
                    <a href="Games/baldisbasics.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Chess (BROKEN) -->
            <div class="game-card" data-category="broke" data-title="Chess" data-popularity="60">
                <div class="card-thumb">
                    <span>&#9823;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge broke">Broken</span>
                    <h3>Chess (BROKEN)</h3>
                    <p>Chess is a two-player strategy board game played on a checkered board with 64 squares arranged in an 8x8 grid.</p>
                    <a href="Games/chess.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Pre-Civilization Bronze Age -->
            <div class="game-card" data-category="strategy" data-title="Pre-Civilization Bronze Age" data-popularity="69">
                <div class="card-thumb">
                    <span>&#127918;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Pre-Civilization Bronze Age!</h3>
                    <p>Pre-Civilization: Bronze Age is a turn-based strategy and management game that places you in control of a prehistoric tribe.</p>
                    <a href="Games/precivilationbronzeage.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Territorial.io -->
            <div class="game-card" data-category="strategy" data-title="Territorial.io" data-popularity="83">
                <div class="card-thumb">
                    <span>&#127758;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Territorial.io</h3>
                    <p>Territorial.io is a multiplayer online strategy game where players compete to conquer territory and expand their kingdoms.</p>
                    <a href="Games/territorialio.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Friday Night Funkin -->
            <div class="game-card" data-category="action" data-title="Friday Night Funkin" data-popularity="87">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#127908;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Friday Night Funkin</h3>
                    <p>Friday Night Funkin is a free rhythm game where players press buttons in time with music tracks, reminiscent of classic arcade games like Dance Dance Revolution.</p>
                    <a href="Games/fridaynightfunkin.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Bitlife -->
            <div class="game-card" data-category="strategy" data-title="Bitlife" data-popularity="81">
                <div class="card-thumb">
                    <span>&#128100;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge strategy">Strategy</span>
                    <h3>Bitlife</h3>
                    <p>Classic game where you play as anyone and live out your virtual life.</p>
                    <a href="Games/bit.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Time Shooters 2 -->
            <div class="game-card" data-category="action" data-title="Time Shooters 2" data-popularity="84">
                <div class="card-thumb">
                    <span>&#128296;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Time Shooters 2</h3>
                    <p>Time Shooter 2 is a shooting game inspired by the style of SuperHot, featuring the same thrilling action.</p>
                    <a href="Games/time.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Rooftop Snipers -->
            <div class="game-card" data-category="action" data-title="Rooftop Snipers" data-popularity="79">
                <div class="card-thumb">
                    <span>&#128299;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Rooftop Snipers</h3>
                    <p>Prepare for action-packed duels in Rooftop Snipers, a chaotic and entertaining 2-player shooting game.</p>
                    <a href="Games/roof.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Tunnel Rush -->
            <div class="game-card" data-category="action" data-title="Tunnel Rush" data-popularity="86">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128647;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Tunnel Rush</h3>
                    <p>Tunnel Rush is a vibrant and fast-paced endless runner game that challenges players to navigate a 3D tunnel.</p>
                    <a href="Games/tune.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Subway Surfers -->
            <div class="game-card" data-category="arcade" data-title="Subway Surfers" data-popularity="94">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> TOP</div>
                    <span>&#128646;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Subway Surfers</h3>
                    <p>Subway Surfers is a fast-paced endless runner -- dash through subways, dodge trains, collect coins, and outrun the Inspector and his dog.</p>
                    <a href="Games/sub.php" class="game-button" aria-label="Play Subway Surfers"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Gun Mayhem 2 -->
            <div class="game-card" data-category="action" data-title="Gun Mayhem 2" data-popularity="77">
                <div class="card-thumb">
                    <span>&#128299;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Gun Mayhem 2</h3>
                    <p>Gun Mayhem 2 is an action-packed 2D platformer shooting game.</p>
                    <a href="Games/gunm.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Bad Parenting -->
            <div class="game-card" data-category="fix" data-title="Bad Parenting" data-popularity="75">
                <div class="card-thumb">
                    <span>&#128123;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>Bad Parenting (FUNCTIONING AND WORKING CLEANLY)</h3>
                    <p>A chilling horror experience with dark themes.</p>
                    <a href="Games/badparenting.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Baseball Bros -->
            <div class="game-card" data-category="arcade" data-title="Baseball Bros" data-popularity="72">
                <div class="card-thumb">
                    <span>&#9918;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Baseball Bros</h3>
                    <p>Hit home runs in this fun baseball arcade game!</p>
                    <a href="Games/baseballbros.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Crazy Cattle 3D -->
            <div class="game-card" data-category="action" data-title="Crazy Cattle 3D" data-popularity="97">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> #1</div>
                    <span>&#128004;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Crazy Cattle 3D (POPULAR!)</h3>
                    <p>Wild cattle action in 3D!</p>
                    <a href="Games/crazycattle3d.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Eggy Car -->
            <div class="game-card" data-category="arcade" data-title="Eggy Car" data-popularity="74">
                <div class="card-thumb">
                    <span>&#129370;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Eggy Car</h3>
                    <p>Drive carefully with a fragile egg on top!</p>
                    <a href="Games/eggycar.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Paper.io -->
            <div class="game-card" data-category="fix" data-title="Paper.io" data-popularity="80">
                <div class="card-thumb">
                    <span>&#128196;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>Paper.io (WORKING!)</h3>
                    <p>Expand your territory and dominate the map!</p>
                    <a href="Games/paperio.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- FNAF 2 -->
            <div class="game-card" data-category="fnaf" data-title="FNAF 2" data-popularity="90">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128059;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fnaf">FNAF</span>
                    <h3>FNAF 2 (WORKING!)</h3>
                    <p>Five Nights at Freddy's 2 returns with new animatronics and no doors to hide behind. Survive five more terrifying nights!</p>
                    <a href="Games/fnaf2.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- FNAF 3 -->
            <div class="game-card" data-category="fnaf" data-title="FNAF 3" data-popularity="88">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128059;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fnaf">FNAF</span>
                    <h3>FNAF 3 (WORKING!)</h3>
                    <p>Five Nights at Freddy's 3 is set 30 years after the original. Face Springtrap in the horror attraction built from Freddy's legacy.</p>
                    <a href="Games/fnaf3.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- FNAF 4 -->
            <div class="game-card" data-category="fnaf" data-title="FNAF 4" data-popularity="87">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128059;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fnaf">FNAF</span>
                    <h3>FNAF 4 (WORKING!)</h3>
                    <p>Five Nights at Freddy's 4 brings the terror home. Survive nightmare animatronics in a child's bedroom with only a flashlight.</p>
                    <a href="Games/fnaf4.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- 1v1.LOL -->
            <div class="game-card" data-category="fix" data-title="1v1.LOL" data-popularity="91">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#128299;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>1v1.LOL (WORKING FINALLY!)</h3>
                    <p>Competitive online building and shooting game. Build structures, take cover, and eliminate opponents in fast-paced 1v1 battles!</p>
                    <a href="Games/1v1lol.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Funny Shooter 2 -->
            <div class="game-card" data-category="action" data-title="Funny Shooter 2" data-popularity="82">
                <div class="card-thumb">
                    <span>&#128299;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Funny Shooter 2</h3>
                    <p>Hilarious first-person shooter with wacky ragdoll enemies. Blast through waves of goofy characters with an arsenal of weapons!</p>
                    <a href="Games/funnyshooter2.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Snow Rider 3D -->
            <div class="game-card" data-category="arcade" data-title="Snow Rider 3D" data-popularity="79">
                <div class="card-thumb">
                    <span>&#127938;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Snow Rider 3D</h3>
                    <p>Endless snowboarding game! Ride down slopes, dodge obstacles, and collect gifts while performing tricks in this winter wonderland.</p>
                    <a href="Games/snowrider3d.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Super Mario Bros -->
            <div class="game-card" data-category="arcade" data-title="Super Mario Bros" data-popularity="95">
                <div class="card-thumb">
                    <div class="popularity"><i class="fa-solid fa-crown"></i> TOP</div>
                    <span>&#127812;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge arcade">Arcade</span>
                    <h3>Super Mario Bros</h3>
                    <p>The classic NES platformer! Jump, stomp Goombas, and rescue Princess Peach through 8 worlds of iconic gaming.</p>
                    <a href="Games/supermariobros.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Time Shooter 1 -->
            <div class="game-card" data-category="fix" data-title="Time Shooter 1" data-popularity="78">
                <div class="card-thumb">
                    <span>&#128336;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge fix">Fixed</span>
                    <h3>Time Shooter 1 (WORKING!)</h3>
                    <p>Unique first-person shooter where time only moves when you do. Plan your moves, dodge bullets in slow motion, and eliminate enemies!</p>
                    <a href="Games/timeshooter1.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Time Shooter 3: SWAT -->
            <div class="game-card" data-category="action" data-title="Time Shooter 3 SWAT" data-popularity="83">
                <div class="card-thumb">
                    <span>&#128336;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Time Shooter 3: SWAT</h3>
                    <p>Time-bending mechanic meets SWAT missions! Use tactical gear, breach doors, and clear rooms with precision.</p>
                    <a href="Games/timeshooter3.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Solar Smash (NEW!) -->
            <div class="game-card" data-category="action" data-title="Solar Smash" data-popularity="88" data-new="true">
                <div class="card-thumb">
                    <div class="ribbon-new">NEW!</div>
                    <div class="popularity"><i class="fa-solid fa-fire"></i> HOT</div>
                    <span>&#127757;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Solar Smash</h3>
                    <p>Solar Smash is an explosive planetary destruction simulator where you wield cosmic weapons to obliterate celestial bodies.</p>
                    <a href="Games/solar.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Idle Breakout (NEW!) -->
            <div class="game-card" data-category="action" data-title="Idle Breakout" data-popularity="76" data-new="true">
                <div class="card-thumb">
                    <div class="ribbon-new">NEW!</div>
                    <span>&#127919;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Idle Breakout</h3>
                    <p>Idle Breakout combines the classic arcade-style brick-breaking gameplay with modern idle mechanics, allowing players to break bricks manually or automatically using upgraded balls with unique abilities.</p>
                    <a href="Games/idleb.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Speed Stars (NEW!) -->
            <div class="game-card" data-category="broke" data-title="Speed Stars" data-popularity="65" data-new="true">
                <div class="card-thumb">
                    <div class="ribbon-new">NEW!</div>
                    <span>&#127939;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge broke">Broken</span>
                    <h3>Speed Stars</h3>
                    <p>Speed Stars is a unique, exciting running game inspired by real-world track competitions. Control athletes' stride by pressing keys at the right moment to master their rhythm.</p>
                    <a href="Games/speedstars.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- Poly Track (NEW!) -->
            <div class="game-card" data-category="action" data-title="Poly Track" data-popularity="82" data-new="true">
                <div class="card-thumb">
                    <div class="ribbon-new">NEW!</div>
                    <span>&#128663;</span>
                </div>
                <div class="card-body">
                    <span class="card-category-badge action">Action</span>
                    <h3>Poly Track</h3>
                    <p>PolyTrack is a low-poly racing game where you build your own tracks, defy physics with loops and jumps, and shave milliseconds off your best time.</p>
                    <a href="Games/poly.php" class="game-button"><i class="fa-solid fa-play"></i> Play Now</a>
                </div>
            </div>

            <!-- More Games Coming -->
            <div class="game-card" data-category="all" data-title="More Games Coming" data-popularity="0">
                <div class="card-thumb" style="background: linear-gradient(135deg, rgba(167,139,250,0.15), rgba(45,212,191,0.15));">
                    <span>&#128640;</span>
                </div>
                <div class="card-body">
                    <h3>More Games Coming</h3>
                    <p>We're constantly adding new games to our collection. Check back regularly for new additions and exciting updates!</p>
                    <button class="game-button" onclick="comingSoon()"><i class="fa-solid fa-clock"></i> Stay Tuned</button>
                </div>
            </div>

        </div><!-- /games-grid -->

        <!-- Footer Back Button -->
        <div class="footer-back">
            <a href="main.php" class="back-button"><i class="fa-solid fa-house"></i> Back to Home</a>
        </div>

    </main><!-- /mp-root -->

    <script>
        // ========================================
        // Hero Carousel
        // ========================================
        (function() {
            const cards = document.querySelectorAll('.hero-card');
            const dots  = document.querySelectorAll('.carousel-dot');
            let current = 0;
            let interval;

            function goTo(idx) {
                cards[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = idx;
                cards[current].classList.add('active');
                dots[current].classList.add('active');
            }

            function next() { goTo((current + 1) % cards.length); }

            function startAuto() { interval = setInterval(next, 5000); }
            function stopAuto()  { clearInterval(interval); }

            dots.forEach(dot => {
                dot.addEventListener('click', function() {
                    stopAuto();
                    goTo(parseInt(this.dataset.dot));
                    startAuto();
                });
            });

            startAuto();
        })();

        // ========================================
        // Search
        // ========================================
        (function() {
            const input   = document.getElementById('gameSearch');
            const clear   = document.getElementById('searchClear');
            const grid    = document.getElementById('gamesGrid');
            const noRes   = document.getElementById('noResults');

            function filterBySearch() {
        /**
         * Game Center Modern Logic v3.0
         * Handles: Search, Filtering, Category Tabs, Hero Carousel, 3D Tilt, and Background Persistence
         */
        document.addEventListener('DOMContentLoaded', () => {
            const state = {
                activeHero: 0,
                activeCategory: 'all',
                searchQuery: '',
                isFiltering: false
            };

            // DOM Elements
            const gamesGrid = document.getElementById('gamesGrid');
            const gameCards = Array.from(document.querySelectorAll('.game-card'));
            const heroCards = document.querySelectorAll('.hero-card');
            const carouselDots = document.querySelectorAll('.carousel-dot');
            const searchInput = document.getElementById('gameSearch');
            const searchClear = document.getElementById('searchClear');
            const categoryTabs = document.querySelectorAll('.category-tab');
            const noResults = document.getElementById('noResults');
            const bgThemeOverride = document.getElementById('bgThemeOverride');
            const backgroundModal = document.getElementById('backgroundModal');
            const backgroundsGrid = document.getElementById('backgroundsGrid');

            // --- 1. Category Count Initialization ---
            const updateCategoryCounts = () => {
                const counts = { all: 0 };
                gameCards.forEach(card => {
                    const cat = card.dataset.category;
                    if (cat) {
                        counts[cat] = (counts[cat] || 0) + 1;
                        counts['all']++;
                    }
                });

                Object.keys(counts).forEach(cat => {
                    const badge = document.getElementById(`count-${cat}`);
                    if (badge) badge.textContent = counts[cat];
                });
            };
            updateCategoryCounts();

            // --- 2. Search & Filter Logic ---
            const filterGames = () => {
                state.isFiltering = true;
                let visibleCount = 0;
                const query = state.searchQuery.toLowerCase();

                gameCards.forEach(card => {
                    const title = card.dataset.title.toLowerCase();
                    const category = card.dataset.category;
                    const matchesSearch = title.includes(query);
                    const matchesCategory = state.activeCategory === 'all' || category === state.activeCategory;

                    if (matchesSearch && matchesCategory) {
                        card.style.display = '';
                        setTimeout(() => card.classList.add('visible'), 50);
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                        card.classList.remove('visible');
                    }
                });

                noResults.style.display = visibleCount === 0 ? 'flex' : 'none';
                state.isFiltering = false;
            };

            searchInput.addEventListener('input', (e) => {
                state.searchQuery = e.target.value;
                searchClear.classList.toggle('visible', state.searchQuery.length > 0);
                filterGames();
            });

            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                state.searchQuery = '';
                searchClear.classList.remove('visible');
                filterGames();
                searchInput.focus();
            });

            categoryTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    categoryTabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    state.activeCategory = tab.dataset.category;
                    filterGames();
                });
            });

            // --- 3. Hero Carousel ---
            let heroInterval;
            const setHero = (index) => {
                heroCards.forEach(c => c.classList.remove('active'));
                carouselDots.forEach(d => d.classList.remove('active'));
                
                if (heroCards[index]) heroCards[index].classList.add('active');
                if (carouselDots[index]) carouselDots[index].classList.add('active');
                state.activeHero = index;
            };

            carouselDots.forEach((dot, idx) => {
                dot.addEventListener('click', () => {
                    stopHeroRotation();
                    setHero(idx);
                    startHeroRotation();
                });
            });

            const startHeroRotation = () => {
                heroInterval = setInterval(() => {
                    let next = (state.activeHero + 1) % heroCards.length;
                    setHero(next);
                }, 8000);
            };

            const stopHeroRotation = () => clearInterval(heroInterval);

            if (heroCards.length > 0) startHeroRotation();

            // --- 4. 3D Tilt Effect & Staggered Reveal ---
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, idx) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('reveal', 'visible');
                        }, (idx % 8) * 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            gameCards.forEach(card => {
                observer.observe(card);

                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = ((y - centerY) / centerY) * -10;
                    const rotateY = ((x - centerX) / centerX) * 10;
                    
                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
                });

                card.addEventListener('mouseleave', () => {
                    card.style.transform = `perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)`;
                });
            });

            // --- 5. Background System & Notifications ---
            const showNotification = (msg, type = 'success') => {
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed; top: 20px; right: 20px;
                    background: ${type === 'success' ? 'linear-gradient(135deg, #2dd4bf, #06b6d4)' : 'linear-gradient(135deg, #f87171, #ef4444)'};
                    color: #0f172a; padding: 15px 25px; border-radius: 12px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 10000;
                    font-weight: 700; transform: translateX(450px);
                    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                    display: flex; align-items: center; gap: 10px;
                `;
                toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> ${msg}`;
                document.body.appendChild(toast);
                setTimeout(() => toast.style.transform = 'translateX(0)', 100);
                setTimeout(() => {
                    toast.style.transform = 'translateX(450px)';
                    setTimeout(() => toast.remove(), 400);
                }, 4000);
            };

            window.openBackgroundModal = () => {
                const backgrounds = JSON.parse(document.body.dataset.backgrounds || '[]');
                backgroundsGrid.innerHTML = backgrounds.map(bg => `
                    <div class="background-option" onclick="setAsBackground('${bg.image_url}', '${bg.title.replace(/'/g, "\\'")}')">
                        <img src="${bg.image_url}" alt="${bg.title}" loading="lazy">
                        <div class="option-overlay">
                            <span>${bg.title}</span>
                        </div>
                    </div>
                `).join('');
                backgroundModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            };

            window.closeBackgroundModal = () => {
                backgroundModal.classList.remove('active');
                document.body.style.overflow = '';
            };

            window.setAsBackground = async (url, title) => {
                try {
                    const response = await fetch('api/set_background.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ background_url: url, title: title })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        bgThemeOverride.style.backgroundImage = `url(${url})`;
                        bgThemeOverride.style.opacity = '1';
                        showNotification(`Background updated to "${title}"`);
                        closeBackgroundModal();
                    } else {
                        showNotification(data.error || 'Failed to update background', 'error');
                    }
                } catch (err) {
                    showNotification('Network connection error', 'error');
                }
            };

            window.removeCustomBackground = async () => {
                try {
                    const response = await fetch('api/set_background.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ remove: true })
                    });

                    const data = await response.json();
                    if (data.success) {
                        bgThemeOverride.style.backgroundImage = '';
                        bgThemeOverride.style.opacity = '0';
                        showNotification('Custom background removed');
                        closeBackgroundModal();
                    }
                } catch (err) {
                    showNotification('Error removing background', 'error');
                }
            };

            window.comingSoon = () => {
                showNotification('This experience is coming soon!', 'success');
            };

            // Global Esc listener
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeBackgroundModal();
            });

            // Init active background
            const initialBg = document.body.dataset.activeBackground;
            if (initialBg) {
                bgThemeOverride.style.backgroundImage = `url(${initialBg})`;
                bgThemeOverride.style.opacity = '1';
            }
        });
    </script>

    <!-- Command Palette -->
    <script src="js/command-palette.js"></script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>
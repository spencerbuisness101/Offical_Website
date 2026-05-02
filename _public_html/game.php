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
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/command-palette.css">
    <link rel="stylesheet" href="css/cinematic-bg.css?v=7.1">
    <link rel="stylesheet" href="main/css/main-page.css?v=7.1">

    <!-- v7.1 Cinematic background module -->
    <script src="js/cinematic-bg.js?v=7.1" defer></script>
<style>
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* ========================================
       Background System (preserved)
       ======================================== */
    .bg-theme-override {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: -2;
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        transition: background-image 0.5s ease-in-out;
    }

    .bg-theme-override::after {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: linear-gradient(135deg, rgba(15,23,42,0.82) 0%, rgba(15,23,42,0.92) 100%);
        z-index: -1;
    }

    .bg-theme-override.designer-bg::after {
        background: rgba(15,23,42,0.75);
    }

    /* ========================================
       Background Selection Modal
       ======================================== */
    .background-modal {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.85);
        z-index: 10000;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .background-modal-content {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%,-50%);
        background: rgba(30,41,59,0.97);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        width: 90%; max-width: 1000px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 2rem;
    }

    .background-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-glass);
    }

    .background-modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        background: var(--grad-purple);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .background-modal-close {
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 1.5rem;
        cursor: pointer;
        width: 30px; height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .background-modal-close:hover { color: white; }

    .backgrounds-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .background-item {
        background: rgba(15,23,42,0.7);
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid var(--border-glass);
        transition: var(--transition-med);
        cursor: pointer;
    }

    .background-item:hover {
        transform: translateY(-4px);
        border-color: var(--purple);
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }

    .background-item.active {
        border-color: var(--teal);
        box-shadow: 0 0 0 3px rgba(45,212,191,0.3);
    }

    .background-preview {
        width: 100%; height: 150px;
        background-size: cover;
        background-position: center;
    }

    .background-info { padding: 1rem; }

    .background-title {
        font-weight: 600;
        color: white;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .background-designer {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }

    .background-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .btn-set-background {
        background: var(--grad-purple);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition-med);
        flex: 1;
    }

    .btn-set-background:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.3);
    }

    .btn-remove-background {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition-med);
    }

    .btn-remove-background:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239,68,68,0.4);
    }

    /* ========================================
       Control Buttons
       ======================================== */
    .control-buttons-container {
        position: fixed;
        top: 25px; right: 25px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 1001;
    }

    .logout-btn, .setting-btn, .backgrounds-btn, .home-btn {
        background: var(--grad-teal);
        color: #fff;
        padding: 12px 20px;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition-med);
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        white-space: nowrap;
    }

    .setting-btn { background: var(--grad-purple); }
    .backgrounds-btn { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .home-btn { background: linear-gradient(135deg, #2dd4bf, #0d9488); }
    .logout-btn { background: var(--grad-coral); }

    .logout-btn:hover, .setting-btn:hover, .backgrounds-btn:hover, .home-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.5);
        color: #fff;
    }

    /* ========================================
       Active Background Info
       ======================================== */
    .background-info-section {
        text-align: center;
        margin: 0 auto 40px;
        padding: 20px;
        background: var(--bg-card);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
        border-radius: var(--radius-md);
        max-width: 600px;
        border: 1px solid var(--border-glass);
        color: white;
    }

    .background-info-section h3 {
        color: white;
        margin-bottom: 10px;
        font-size: 1.2em;
    }

    .background-info-section p {
        margin: 5px 0;
        opacity: 0.9;
    }

    /* ========================================
       Layout Wrapper
       ======================================== */
    .page-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px 24px 60px;
    }

    /* ========================================
       Back Button
       ======================================== */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        padding: 10px 20px;
        border-radius: var(--radius-pill);
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
        transition: var(--transition-med);
        margin-bottom: 30px;
    }

    .back-link:hover {
        color: var(--teal);
        border-color: var(--teal);
        background: rgba(45,212,191,0.08);
        transform: translateX(-4px);
    }

    .back-link i { transition: transform var(--transition-fast); }
    .back-link:hover i { transform: translateX(-3px); }

    /* ========================================
       Page Header
       ======================================== */
    .page-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 50px 20px 40px;
        background: var(--bg-card);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-glass);
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--coral), var(--teal));
    }

    .page-header h1 {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--text-primary);
    }

    .page-header p {
        font-size: 1.15rem;
        color: var(--text-secondary);
        max-width: 600px;
        margin: 0 auto;
    }

    /* ========================================
       Hero / Featured Carousel
       ======================================== */
    .hero-carousel {
        position: relative;
        max-width: 900px;
        margin: 0 auto 50px;
        height: 340px;
    }

    .hero-card {
        position: absolute;
        inset: 0;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.5s ease;
    }

    .hero-card.active {
        opacity: 1;
        pointer-events: auto;
        z-index: 2;
    }

    .hero-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: var(--radius-lg);
        background: var(--bg-card);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
        border: 2px solid transparent;
        padding: 40px 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        text-align: center;
        overflow: hidden;
    }

    .hero-card-inner::before { display: none; }

    .hero-emoji {
        font-size: 3.5rem;
        margin-bottom: 12px;
        filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
    }

    .hero-card-inner h2 {
        font-size: 1.9rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 10px;
    }

    .hero-card-inner p {
        color: var(--text-secondary);
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 20px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 32px;
        margin-bottom: 24px;
    }

    .hero-stat {
        text-align: center;
    }

    .hero-stat-val {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--teal);
    }

    .hero-stat-lbl {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
    }

    .hero-play-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 36px;
        background: var(--grad-teal);
        color: var(--bg-primary);
        font-weight: 700;
        font-size: 1.05rem;
        border: none;
        border-radius: var(--radius-pill);
        text-decoration: none;
        cursor: pointer;
        transition: var(--transition-med);
        box-shadow: 0 4px 20px rgba(45,212,191,0.35);
    }

    .hero-play-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(45,212,191,0.35);
        color: var(--bg-primary);
    }

    /* Carousel Dots */
    .carousel-dots {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 16px;
        position: relative;
        z-index: 5;
    }

    .carousel-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        border: 2px solid var(--text-muted);
        background: transparent;
        cursor: pointer;
        transition: var(--transition-med);
        padding: 0;
    }

    .carousel-dot.active {
        background: var(--teal);
        border-color: var(--teal);
        box-shadow: 0 0 10px rgba(45,212,191,0.5);
        transform: scale(1.25);
    }

    /* ========================================
       Search Bar
       ======================================== */
    .search-container {
        max-width: 700px;
        margin: 0 auto 28px;
        position: relative;
    }

    .search-input {
        width: 100%;
        padding: 16px 24px 16px 52px;
        font-size: 1.05rem;
        background: var(--bg-card);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-pill);
        color: var(--text-primary);
        outline: none;
        transition: var(--transition-med);
    }

    .search-input::placeholder { color: var(--text-muted); }

    .search-input:focus {
        border-color: var(--teal);
        box-shadow: 0 0 0 3px rgba(45,212,191,0.15);
    }

    .search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1rem;
        pointer-events: none;
        transition: color var(--transition-fast);
    }

    .search-input:focus ~ .search-icon { color: var(--teal); }

    .search-clear {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 1rem;
        cursor: pointer;
        display: none;
        padding: 4px;
    }

    .search-clear.visible { display: block; }
    .search-clear:hover { color: var(--coral); }

    .search-no-results {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        font-size: 1.1rem;
        display: none;
    }

    .search-no-results i {
        font-size: 2.5rem;
        margin-bottom: 16px;
        display: block;
        color: var(--text-muted);
    }

    /* ========================================
       Category Tabs
       ======================================== */
    .category-tabs {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 36px;
        scrollbar-width: thin;
        scrollbar-color: var(--teal) transparent;
    }

    .category-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--bg-card);
        color: var(--text-secondary);
        border: 1px solid var(--border-glass);
        padding: 10px 22px;
        border-radius: var(--radius-pill);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition-med);
        backdrop-filter: var(--blur);
        -webkit-backdrop-filter: var(--blur);
    }

    .category-tab:hover {
        background: rgba(255,255,255,0.1);
        color: var(--text-primary);
        border-color: var(--border-glass-hover);
        transform: translateY(-2px);
    }

    .category-tab.active {
        background: var(--grad-teal);
        color: var(--bg-primary);
        border-color: transparent;
        box-shadow: 0 4px 18px rgba(45,212,191,0.35);
        font-weight: 700;
    }

    .category-tab.active:hover {
        color: var(--bg-primary);
    }

    .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px; height: 22px;
        padding: 0 6px;
        border-radius: 11px;
        font-size: 0.75rem;
        font-weight: 700;
        background: rgba(255,255,255,0.15);
        color: inherit;
        line-height: 1;
    }

    .category-tab.active .tab-count {
        background: rgba(15,23,42,0.25);
        color: var(--bg-primary);
    }

    /* ========================================
       Games Grid
       ======================================== */
    .games-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 50px;
    }

    /* ========================================
       Game Card
       ======================================== */
    .game-card {
        position: relative;
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(78,205,196,0.25);
        border-radius: 14px;
        padding: 24px;
        text-align: center;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
        min-height: 190px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }

    .game-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
        transition: left 0.5s;
    }

    .game-card:hover::before {
        left: 100%;
    }

    .game-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        border-color: rgba(78,205,196,0.5);
    }

    .game-card.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Emoji thumbnail background */
    .card-thumb {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        background: linear-gradient(135deg, rgba(45,212,191,0.08), rgba(167,139,250,0.08));
        position: relative;
        overflow: hidden;
    }

    .card-thumb::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 40px;
        background: linear-gradient(transparent, var(--bg-card));
        pointer-events: none;
    }

    /* "NEW!" corner ribbon */
    .ribbon-new {
        position: absolute;
        top: 14px;
        right: -30px;
        background: var(--grad-coral);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 4px 36px;
        transform: rotate(45deg);
        box-shadow: 0 2px 8px rgba(249,113,113,0.4);
        z-index: 3;
    }

    /* Popularity indicator */
    .popularity {
        position: absolute;
        top: 10px;
        left: 10px;
        display: flex;
        align-items: center;
        gap: 4px;
        background: rgba(15,23,42,0.7);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        padding: 4px 10px;
        border-radius: var(--radius-pill);
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--gold);
        z-index: 3;
    }

    .popularity i { font-size: 0.6rem; }

    .card-body {
        padding: 20px 22px 24px;
    }

    .card-body h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 8px;
        line-height: 1.3;
    }

    .card-body p {
        font-size: 0.88rem;
        color: var(--text-secondary);
        line-height: 1.55;
        margin-bottom: 18px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .card-category-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 3px 10px;
        border-radius: var(--radius-pill);
        margin-bottom: 12px;
        background: rgba(45,212,191,0.12);
        color: var(--teal);
    }

    .card-category-badge.action  { background: rgba(249,113,113,0.12); color: var(--coral);  }
    .card-category-badge.puzzle  { background: rgba(167,139,250,0.12); color: var(--purple); }
    .card-category-badge.horror  { background: rgba(239,68,68,0.12);  color: #f87171;       }
    .card-category-badge.strategy{ background: rgba(251,191,36,0.12);  color: var(--gold);   }
    .card-category-badge.fnaf    { background: rgba(239,68,68,0.12);  color: #f87171;       }
    .card-category-badge.fix     { background: rgba(16,185,129,0.12); color: #34d399;       }
    .card-category-badge.broke   { background: rgba(239,68,68,0.15); color: #f87171;       }
    .card-category-badge.arcade  { background: rgba(56,189,248,0.12); color: #38bdf8;       }

    .game-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 24px;
        background: var(--grad-teal);
        color: var(--bg-primary);
        font-weight: 700;
        font-size: 0.88rem;
        border: none;
        border-radius: var(--radius-pill);
        text-decoration: none;
        cursor: pointer;
        transition: var(--transition-med);
        box-shadow: 0 3px 14px rgba(45,212,191,0.25);
        width: 100%;
        justify-content: center;
    }

    .game-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(45,212,191,0.4);
        color: var(--bg-primary);
    }

    /* ========================================
       Footer Back Button
       ======================================== */
    .footer-back {
        text-align: center;
        margin-top: 20px;
        padding-bottom: 40px;
    }

    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: var(--grad-purple);
        color: white;
        padding: 14px 32px;
        border-radius: var(--radius-pill);
        text-decoration: none;
        font-weight: 700;
        transition: var(--transition-med);
        box-shadow: 0 4px 18px rgba(167,139,250,0.3);
    }

    .back-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(167,139,250,0.45);
        color: white;
    }

    /* ========================================
       Notification
       ======================================== */
    .notification {
        position: fixed;
        top: 20px; right: 20px;
        padding: 1rem 1.5rem;
        border-radius: var(--radius-sm);
        background: var(--teal);
        color: var(--bg-primary);
        font-weight: 600;
        box-shadow: 0 4px 16px rgba(45,212,191,0.35);
        z-index: 10001;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .notification.show { transform: translateX(0); }
    .notification.error { background: #ef4444; color: #fff; }

    /* ========================================
       Responsive
       ======================================== */

    /* Tablet breakpoint: 768px - 1024px */
    @media (max-width: 1024px) {
        .games-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .hero-carousel {
            height: 360px;
            max-width: 700px;
        }

        .search-container {
            max-width: 600px;
        }
    }

    /* Mobile breakpoint: up to 768px */
    @media (max-width: 768px) {
        .control-buttons-container {
            position: relative;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            top: 0; right: 0;
            margin-bottom: 20px;
        }

        .logout-btn, .setting-btn, .backgrounds-btn, .home-btn {
            font-size: 12px;
            padding: 10px 15px;
        }

        .backgrounds-grid { grid-template-columns: 1fr; }

        .page-header h1 { font-size: 2.2rem; }

        .hero-carousel { height: 340px; }

        .hero-card-inner {
            padding: 24px 18px;
        }

        .hero-card-inner h2 { font-size: 1.4rem; }
        .hero-card-inner p  { font-size: 0.9rem; }

        .hero-stats { gap: 18px; flex-wrap: wrap; }

        .hero-play-btn {
            padding: 12px 28px;
            font-size: 0.95rem;
        }

        .games-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        /* Category tabs: horizontal scroll on mobile */
        .category-tabs {
            flex-wrap: nowrap;
            justify-content: flex-start;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            padding-bottom: 8px;
            gap: 8px;
        }

        .category-tab {
            padding: 8px 16px;
            font-size: 0.82rem;
            flex-shrink: 0;
        }

        /* Fix badge overlapping on small screens */
        .ribbon-new {
            top: 10px;
            right: -28px;
            font-size: 0.6rem;
            padding: 3px 30px;
        }

        .popularity {
            padding: 3px 8px;
            font-size: 0.65rem;
        }

        .search-container {
            max-width: 100%;
        }

        .search-input {
            padding: 14px 20px 14px 48px;
            font-size: 0.95rem;
        }
    }

    /* Small phone breakpoint: up to 480px */
    @media (max-width: 480px) {
        .page-wrapper { padding: 16px 12px 40px; }
        .page-header { padding: 30px 16px 26px; }
        .page-header h1 { font-size: 1.8rem; }
        .page-header p { font-size: 1rem; }

        .hero-carousel { height: 360px; }

        .hero-card-inner {
            padding: 20px 14px;
        }

        .hero-card-inner h2 { font-size: 1.25rem; }
        .hero-card-inner p { font-size: 0.85rem; margin-bottom: 14px; }

        .hero-stats { gap: 14px; }
        .hero-stat-val { font-size: 1.1rem; }
        .hero-stat-lbl { font-size: 0.65rem; }

        .hero-play-btn {
            padding: 10px 24px;
            font-size: 0.9rem;
        }

        .hero-emoji { font-size: 2.5rem; margin-bottom: 8px; }

        .games-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .card-body {
            padding: 16px 16px 20px;
        }

        .card-body h3 { font-size: 1.05rem; }
        .card-body p { font-size: 0.82rem; }

        /* Fix badge overlapping on very small screens */
        .ribbon-new {
            top: 8px;
            right: -26px;
            font-size: 0.55rem;
            padding: 2px 26px;
        }

        .popularity {
            top: 8px;
            left: 8px;
            padding: 2px 6px;
            font-size: 0.6rem;
        }

        .card-thumb {
            height: 100px;
            font-size: 2.8rem;
        }

        .category-tab {
            padding: 7px 14px;
            font-size: 0.78rem;
        }

        .back-link {
            font-size: 0.85rem;
            padding: 8px 16px;
        }

        .back-button {
            padding: 12px 24px;
            font-size: 0.9rem;
        }

        .search-input {
            padding: 12px 18px 12px 44px;
            font-size: 0.9rem;
        }

        .search-icon {
            left: 14px;
            font-size: 0.9rem;
        }
    }

    /* ========================================
       Cinematic UI Overhaul
       ======================================== */
    .page-header {
        position: relative;
        z-index: 2;
    }
    .page-header::after {
        content: '';
        position: absolute;
        top: -50%; left: -10%; right: -10%; bottom: -50%;
        background: radial-gradient(circle at center, rgba(45, 212, 191, 0.12), transparent 70%);
        z-index: -1;
        pointer-events: none;
        animation: pulse-glow 6s ease-in-out infinite alternate;
    }
    @keyframes pulse-glow {
        0% { transform: scale(0.9); opacity: 0.5; }
        100% { transform: scale(1.1); opacity: 1; }
    }

    /* Scroll-in animation */
    .game-card {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                    transform 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                    box-shadow 0.3s ease, border-color 0.3s ease !important;
    }
    .game-card.cinematic-visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Enhanced glassmorphism hover */
    .game-card:hover {
        border-color: rgba(45, 212, 191, 0.35) !important;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4),
                    inset 0 1px 0 rgba(255,255,255,0.15),
                    0 0 30px rgba(45, 212, 191, 0.15) !important;
        transform: translateY(-6px) !important;
    }

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

    </div><!-- /page-wrapper -->

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
                const q = input.value.trim().toLowerCase();
                clear.classList.toggle('visible', q.length > 0);

                const cards = grid.querySelectorAll('.game-card');
                let visibleCount = 0;
                const activeTab = document.querySelector('.category-tab.active');
                const activeCat = activeTab ? activeTab.dataset.category : 'all';

                cards.forEach(card => {
                    const title = (card.dataset.title || '').toLowerCase();
                    const cat   = card.dataset.category || '';
                    const matchSearch = !q || title.includes(q);
                    const matchCat = activeCat === 'all' || cat === activeCat;

                    if (matchSearch && matchCat) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                noRes.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            input.addEventListener('input', filterBySearch);

            clear.addEventListener('click', function() {
                input.value = '';
                clear.classList.remove('visible');
                filterBySearch();
                input.focus();
            });

            // Expose for category tabs
            window._filterBySearch = filterBySearch;
        })();

        // ========================================
        // Category Tabs with Count Badges
        // ========================================
        (function() {
            const tabs  = document.querySelectorAll('.category-tab');
            const cards = document.querySelectorAll('.game-card');

            // Populate count badges
            const counts = {};
            let total = 0;
            cards.forEach(card => {
                const cat = card.dataset.category;
                if (!cat) return;
                // Don't count the "More Games Coming" placeholder
                if (card.dataset.title === 'More Games Coming') return;
                counts[cat] = (counts[cat] || 0) + 1;
                total++;
            });

            const allCount = document.getElementById('count-all');
            if (allCount) allCount.textContent = total;

            Object.keys(counts).forEach(cat => {
                const el = document.getElementById('count-' + cat);
                if (el) el.textContent = counts[cat];
            });

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Re-run search filter (which also respects category)
                    if (window._filterBySearch) window._filterBySearch();
                });
            });
        })();

        // ========================================
        // IntersectionObserver Stagger Animation
        // ========================================
        (function() {
            const cards = document.querySelectorAll('.game-card');
            let delay = 0;

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const card = entry.target;
                        const stagger = parseInt(card.dataset.stagger || '0');
                        card.style.transitionDelay = stagger + 'ms';
                        card.style.transition = 'opacity 0.5s ease ' + stagger + 'ms, transform 0.5s ease ' + stagger + 'ms';
                        card.classList.add('visible');
                        observer.unobserve(card);
                    }
                });
            }, {
                threshold: 0.08,
                rootMargin: '0px 0px -40px 0px'
            });

            cards.forEach(function(card, i) {
                card.dataset.stagger = (i % 8) * 80; // stagger in groups of 8
                observer.observe(card);
            });
        })();

        // ========================================
        // Logout
        // ========================================
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                const logoutBtn = document.querySelector('.logout-btn');
                const originalText = logoutBtn.innerHTML;
                logoutBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Logging out...';
                logoutBtn.disabled = true;

                fetch('auth/logout.php')
                    .then(response => {
                        if (response.ok) {
                            logoutBtn.innerHTML = '<i class="fa-solid fa-check"></i> Success!';
                            setTimeout(() => { window.location.href = 'index.php'; }, 1000);
                        } else {
                            throw new Error('Logout failed');
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        logoutBtn.innerHTML = originalText;
                        logoutBtn.disabled = false;
                        alert('Logout failed. Please try again.');
                    });
            }
        }

        // ========================================
        // Background Selection Functionality
        // ========================================
        function openBackgroundModal() {
            const modal = document.getElementById('backgroundModal');
            const grid = document.getElementById('backgroundsGrid');
            grid.innerHTML = '';

            const backgrounds = <?php echo json_encode($available_backgrounds); ?>;
            const currentBackground = getCurrentBackground();

            backgrounds.forEach(background => {
                const isActive = currentBackground === background.image_url;

                const backgroundItem = document.createElement('div');
                backgroundItem.className = 'background-item' + (isActive ? ' active' : '');
                backgroundItem.innerHTML =
                    '<div class="background-preview" style="background-image: url(\'' + background.image_url + '\')"></div>' +
                    '<div class="background-info">' +
                        '<div class="background-title">' + escapeHtml(background.title) + '</div>' +
                        '<div class="background-designer">By: ' + escapeHtml(background.designer_name) + '</div>' +
                        '<div class="background-actions">' +
                            '<button class="btn-set-background" onclick="setAsBackground(\'' + background.image_url + '\', \'' + escapeHtml(background.title) + '\')">' +
                                (isActive ? '<i class="fa-solid fa-check"></i> Using' : 'Use This') +
                            '</button>' +
                        '</div>' +
                    '</div>';

                grid.appendChild(backgroundItem);
            });

            modal.style.display = 'block';
        }

        function closeBackgroundModal() {
            document.getElementById('backgroundModal').style.display = 'none';
        }

        function setAsBackground(imageUrl, title) {
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            settings.customBackground = imageUrl;
            settings.customBackgroundTitle = title;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

            applyActiveBackground();
            closeBackgroundModal();
            showNotification('Background set to "' + title + '"!');
            setTimeout(openBackgroundModal, 100);
        }

        function removeCustomBackground() {
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            delete settings.customBackground;
            delete settings.customBackgroundTitle;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

            applyActiveBackground();
            closeBackgroundModal();
            showNotification('Custom background removed!');
            setTimeout(openBackgroundModal, 100);
        }

        function getCurrentBackground() {
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                return settings.customBackground || null;
            }
            return null;
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText =
                'position:fixed;top:20px;right:20px;' +
                'background:linear-gradient(135deg,#2dd4bf,#06b6d4);' +
                'color:#0f172a;padding:15px 20px;border-radius:8px;' +
                'box-shadow:0 5px 15px rgba(0,0,0,0.3);z-index:10001;' +
                'font-weight:600;transform:translateX(400px);transition:transform 0.3s ease;';
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(function() { notification.style.transform = 'translateX(0)'; }, 100);
            setTimeout(function() {
                notification.style.transform = 'translateX(400px)';
                setTimeout(function() { document.body.removeChild(notification); }, 300);
            }, 3000);
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // ========================================
        // Apply Active Background
        // ========================================
        function applyActiveBackground() {
            const bgOverride = document.getElementById('bgThemeOverride');
            if (!bgOverride) return;

            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                if (settings.customBackground && settings.customBackground.trim() !== '') {
                    bgOverride.style.backgroundImage = "url('" + settings.customBackground + "')";
                    bgOverride.classList.remove('designer-bg');
                    return;
                }
            }

            <?php if ($active_designer_background): ?>
                bgOverride.style.backgroundImage = "url('<?php echo htmlspecialchars($active_designer_background['image_url'], ENT_QUOTES); ?>')";
                bgOverride.classList.add('designer-bg');
            <?php endif; ?>
        }

        // ========================================
        // DOMContentLoaded - Settings & Init
        // ========================================
        document.addEventListener('DOMContentLoaded', function() {
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);

                if (settings.accentColor) {
                    const accentColor = '#' + settings.accentColor;

                    let styleElement = document.getElementById('accent-color-styles');
                    if (!styleElement) {
                        styleElement = document.createElement('style');
                        styleElement.id = 'accent-color-styles';
                        document.head.appendChild(styleElement);
                    }

                    styleElement.textContent =
                        '.game-button, .move-button, .info-button { background: linear-gradient(135deg, ' + accentColor + ', #06b6d4) !important; }' +
                        '.game-button:hover, .move-button:hover, .info-button:hover { background: linear-gradient(135deg, #06b6d4, ' + accentColor + ') !important; }' +
                        '.back-link { border-color: ' + accentColor + ' !important; }' +
                        '.back-link:hover { border-color: ' + accentColor + ' !important; color: ' + accentColor + ' !important; }' +
                        '.back-button { background: linear-gradient(135deg, ' + accentColor + ', #7c3aed) !important; }' +
                        '.game-card:hover { border-color: rgba(255,255,255,0.2) !important; }' +
                        '.page-header h1 { background: linear-gradient(135deg, ' + accentColor + ', #a78bfa, #f97171) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; background-clip: text !important; }' +
                        '.page-header::before { background: linear-gradient(135deg, ' + accentColor + ', #a78bfa, #f97171) !important; }' +
                        '.category-tab.active { background: linear-gradient(135deg, ' + accentColor + ', #06b6d4) !important; }' +
                        '.hero-play-btn { background: linear-gradient(135deg, ' + accentColor + ', #06b6d4) !important; }';
                }

                if (settings.fontSize) {
                    document.documentElement.style.fontSize = settings.fontSize + 'px';
                }

                if (settings.gameVolume) {
                    console.log('Game volume set to:', settings.gameVolume);
                }
            }

            applyActiveBackground();
        });

        function comingSoon() {
            alert('More exciting games are coming soon! Stay tuned for updates.');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('backgroundModal');
            if (event.target === modal) { closeBackgroundModal(); }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'l') { e.preventDefault(); logout(); }
            if (e.ctrlKey && e.key === 's') { e.preventDefault(); window.location.href = 'set.php'; }
            if (e.ctrlKey && e.key === 'b') { e.preventDefault(); openBackgroundModal(); }
        });
    </script>

    <!-- Command Palette -->
    <script src="js/command-palette.js"></script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>


    <!-- Cinematic UI Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Intersection Observer for scroll-in stagger
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('cinematic-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        var cards = document.querySelectorAll('.game-card');
        cards.forEach(function(card, index) {
            card.style.transitionDelay = ((index % 4) * 0.1) + 's';
            observer.observe(card);

            // 3D Tilt Logic
            card.addEventListener('mousemove', function(e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                var centerX = rect.width / 2;
                var centerY = rect.height / 2;
                var rotateX = ((y - centerY) / centerY) * -8;
                var rotateY = ((x - centerX) / centerX) * 8;
                card.style.transitionDelay = '0s';
                card.style.transitionDuration = '0.1s';
                card.style.transform = 'perspective(800px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) scale3d(1.02, 1.02, 1.02)';
            });

            card.addEventListener('mouseleave', function() {
                card.style.transitionDuration = '0.5s';
                card.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
            });
        });
    });
    </script>

</body>
</html>
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

// Get user info for display
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);

// Database connection for dynamic content
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch dynamic stats
$totalUsers = 0;
$totalContributors = 0;
$totalDesigners = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = :role");

    $stmt->execute([':role' => 'contributor']);
    $totalContributors = (int)$stmt->fetchColumn();

    $stmt->execute([':role' => 'designer']);
    $totalDesigners = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("HoF stats query error: " . $e->getMessage());
}

// Fetch all regular users (not admin, contributor, designer)
$allUsers = [];
try {
    $stmt = $db->prepare("SELECT id, username, role, created_at FROM users WHERE role NOT IN ('admin','contributor','designer') ORDER BY created_at ASC");
    $stmt->execute();
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("HoF users query error: " . $e->getMessage());
}

$regularUsers = $allUsers;

// Fetch background data (used by background system)
$active_designer_background = null;
$available_backgrounds = [];
try {
    $bgStmt = $db->query("
        SELECT db.image_url, db.title, u.username as designer_name
        FROM designer_backgrounds db
        LEFT JOIN users u ON db.user_id = u.id
        WHERE db.is_active = 1 AND db.status = 'approved'
        LIMIT 1
    ");
    $active_designer_background = $bgStmt->fetch(PDO::FETCH_ASSOC);

    $allBgStmt = $db->query("
        SELECT db.id, db.image_url, db.title, u.username as designer_name
        FROM designer_backgrounds db
        LEFT JOIN users u ON db.user_id = u.id
        WHERE db.status = 'approved'
        ORDER BY db.is_active DESC, db.created_at DESC
    ");
    $available_backgrounds = $allBgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("HoF background fetch error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hall of Fame - Spencer's Website">
    <title>Hall of Fame - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <style>
        /* ========== RESET & BASE ========== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            color: #e2e8f0;
            overflow-x: hidden;
        }

        /* ========== AMBIENT BACKGROUND ========== */
        .ambient-bg {
            position: fixed;
            inset: 0;
            z-index: -2;
            overflow: hidden;
            pointer-events: none;
        }

        .ambient-bg .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.25;
            animation: orbFloat 20s ease-in-out infinite alternate;
        }

        .ambient-bg .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #4ECDC4, transparent);
            top: -10%; left: -5%;
        }

        .ambient-bg .orb-2 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, #8b5cf6, transparent);
            bottom: -15%; right: -10%;
            animation-delay: -7s;
        }

        .ambient-bg .orb-3 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, #FF6B6B, transparent);
            top: 50%; left: 40%;
            animation-delay: -14s;
        }

        @keyframes orbFloat {
            0%   { transform: translate(0, 0) scale(1); }
            33%  { transform: translate(40px, -30px) scale(1.1); }
            66%  { transform: translate(-20px, 20px) scale(0.95); }
            100% { transform: translate(10px, -10px) scale(1.05); }
        }

        /* ========== PARTICLES ========== */
        .particles {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            animation: particleDrift linear infinite;
        }

        @keyframes particleDrift {
            0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 1; }
            100% { transform: translateY(-10vh) rotate(720deg); opacity: 0; }
        }

        /* ========== CONTAINER ========== */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
        }

        /* ========== BACK BUTTON ========== */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            color: #e2e8f0;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            margin-bottom: 32px;
        }

        .back-btn:hover {
            background: rgba(78, 205, 196, 0.15);
            border-color: #4ECDC4;
            color: #4ECDC4;
            transform: translateX(-4px);
        }

        .back-btn i {
            transition: transform 0.3s ease;
        }

        .back-btn:hover i {
            transform: translateX(-3px);
        }

        /* ========== PAGE HEADER ========== */
        .page-header {
            text-align: center;
            margin-bottom: 48px;
            padding: 56px 32px 48px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4ECDC4, #8b5cf6, #FF6B6B, #8b5cf6, #4ECDC4);
            background-size: 200% 100%;
            animation: gradientSlide 4s linear infinite;
        }

        @keyframes gradientSlide {
            0%   { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        .page-header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #4ECDC4, #8b5cf6, #FF6B6B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 14px;
        }

        .page-header .subtitle {
            font-size: 1.15rem;
            color: #94a3b8;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* ========== STATS BAR ========== */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 56px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.3);
            background: rgba(255, 255, 255, 0.08);
        }

        .stat-card .stat-number {
            display: block;
            font-size: 2.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4ECDC4, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== SECTION TITLES ========== */
        .section-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title .line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(139, 92, 246, 0.4), transparent);
        }

        /* ========== GLASS CARD (shared) ========== */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4ECDC4, #8b5cf6);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .glass-card:hover::before {
            opacity: 1;
        }

        .glass-card:hover {
            transform: translateY(-6px);
            border-color: rgba(139, 92, 246, 0.25);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 40px rgba(139, 92, 246, 0.08);
        }

        /* ========== FEATURED CARD (Spencer) ========== */
        .featured-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(78, 205, 196, 0.05), rgba(255, 255, 255, 0.03));
            backdrop-filter: blur(14px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 28px;
            padding: 52px 48px;
            position: relative;
            overflow: hidden;
            margin-bottom: 32px;
            transition: all 0.4s ease;
        }

        .featured-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ECDC4, #8b5cf6, #FF6B6B);
        }

        .featured-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.06), transparent 70%);
            pointer-events: none;
        }

        .featured-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35), 0 0 50px rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.35);
        }

        .featured-header {
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 28px;
        }

        .featured-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #4ECDC4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.35);
            border: 3px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }

        .featured-info h2 {
            font-size: 2.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4ECDC4, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }

        .featured-info .role-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 16px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(78, 205, 196, 0.2));
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .featured-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #cbd5e1;
            max-width: 800px;
        }

        .featured-contributions {
            margin-top: 28px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 28px;
            border-left: 3px solid #8b5cf6;
        }

        .featured-contributions h4 {
            color: #4ECDC4;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .featured-contributions ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
        }

        .featured-contributions li {
            color: #cbd5e1;
            font-size: 0.95rem;
            padding-left: 24px;
            position: relative;
        }

        .featured-contributions li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: #4ECDC4;
            font-size: 0.75rem;
            top: 3px;
        }

        /* ========== DUAL CARDS ROW (Lexi & Garrett) ========== */
        .dual-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 56px;
        }

        .member-card .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }

        .member-card .card-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.12);
        }

        .member-card .card-avatar.contributor-avatar {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.25);
        }

        .member-card .card-avatar.designer-avatar {
            background: linear-gradient(135deg, #ec4899, #db2777);
            box-shadow: 0 8px 24px rgba(236, 72, 153, 0.25);
        }

        .member-card .card-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 4px;
        }

        .member-card .card-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-contributor {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .role-designer {
            background: rgba(236, 72, 153, 0.15);
            color: #f472b6;
            border: 1px solid rgba(236, 72, 153, 0.3);
        }

        .member-card .card-description {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .member-card .card-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .member-card .skill-pill {
            padding: 5px 14px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .member-card .skill-pill:hover {
            background: rgba(139, 92, 246, 0.12);
            border-color: rgba(139, 92, 246, 0.3);
            color: #c4b5fd;
        }

        .member-card .card-contributions {
            margin-top: 24px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 14px;
            padding: 24px;
            border-left: 3px solid;
        }

        .member-card .card-contributions.contrib-border {
            border-left-color: #f59e0b;
        }

        .member-card .card-contributions.design-border {
            border-left-color: #ec4899;
        }

        .member-card .card-contributions h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .member-card .card-contributions h4.contrib-title {
            color: #fbbf24;
        }

        .member-card .card-contributions h4.design-title {
            color: #f472b6;
        }

        .member-card .card-contributions ul {
            list-style: none;
        }

        .member-card .card-contributions li {
            color: #cbd5e1;
            font-size: 0.9rem;
            margin-bottom: 8px;
            padding-left: 22px;
            position: relative;
        }

        .member-card .card-contributions li::before {
            content: '\f005';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            font-size: 0.65rem;
            top: 4px;
        }

        .member-card .card-contributions.contrib-border li::before {
            color: #f59e0b;
        }

        .member-card .card-contributions.design-border li::before {
            color: #ec4899;
        }

        /* ========== USERS GRID ========== */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 56px;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-3px);
            border-color: rgba(139, 92, 246, 0.2);
            background: rgba(255, 255, 255, 0.07);
        }

        .user-card .user-avatar-sm {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .user-card .user-meta {
            flex: 1;
            min-width: 0;
        }

        .user-card .user-meta .name {
            font-weight: 600;
            font-size: 1rem;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-card .user-meta .joined {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* Role badges */
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .badge-admin       { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .badge-contributor  { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .badge-designer     { background: rgba(236,72,153,0.15); color: #ec4899; border: 1px solid rgba(236,72,153,0.3); }
        .badge-user         { background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .badge-community    { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }

        /* ========== FOOTER ========== */
        .page-footer {
            text-align: center;
            padding: 32px;
            margin-top: 20px;
            color: #475569;
            font-size: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* ========== STAGGERED ENTRANCE ANIMATIONS ========== */
        .animate-in {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1),
                        transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .animate-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 900px) {
            .dual-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2.4rem;
            }

            .featured-card {
                padding: 32px 24px;
            }

            .featured-header {
                flex-direction: column;
                text-align: center;
            }

            .featured-info h2 {
                font-size: 2rem;
            }

            .featured-contributions ul {
                grid-template-columns: 1fr;
            }

            .glass-card {
                padding: 28px 20px;
            }

            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }

            .users-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 16px;
            }
        }

        @media (max-width: 480px) {
            .stats-bar {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds ?? []), ENT_QUOTES, 'UTF-8'); ?>'
      data-active-background='<?php echo htmlspecialchars($active_designer_background['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>

    <!-- Ambient Background -->
    <div class="ambient-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- Particles -->
    <div class="particles" id="particles"></div>

    <div class="container">

        <!-- Back Button -->
        <a href="main.php" class="back-btn animate-in">
            <i class="fa-solid fa-arrow-left"></i> Back to Main
        </a>

        <!-- Page Header -->
        <header class="page-header animate-in">
            <h1>Hall of Fame</h1>
            <p class="subtitle">Honoring the people who built, shaped, and championed this platform from day one.</p>
        </header>

        <!-- Dynamic Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card animate-in">
                <span class="stat-number"><?php echo $totalUsers; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-number"><?php echo $totalContributors; ?></span>
                <span class="stat-label">Contributors</span>
            </div>
            <div class="stat-card animate-in">
                <span class="stat-number"><?php echo $totalDesigners; ?></span>
                <span class="stat-label">Designers</span>
            </div>
        </div>

        <!-- ===================== SPENCER — Featured ===================== -->
        <div class="featured-card animate-in">
            <div class="featured-header">
                <div class="featured-avatar">&#x1F451;</div>
                <div class="featured-info">
                    <h2>Spencer</h2>
                    <span class="role-label"><i class="fa-solid fa-crown"></i> Creator &amp; King</span>
                </div>
            </div>
            <p class="featured-description">
                Spencer is the original creator and mastermind behind every aspect of this platform. From the very
                first line of code to the final design polish, he conceived the vision, built the architecture, and
                brought everything together. His relentless drive to push boundaries turned a simple idea into the
                fully realized digital experience you see today. Every feature, system, and detail traces back to his
                creative ambition and technical leadership.
            </p>
            <div class="featured-contributions">
                <h4><i class="fa-solid fa-bolt"></i> What He Built</h4>
                <ul>
                    <li>Founded the entire platform from concept to launch</li>
                    <li>Architected the full-stack codebase and database layer</li>
                    <li>Designed the authentication, roles, and permissions systems</li>
                    <li>Built the game library and content systems</li>
                    <li>Created the background system, admin dashboard, and analytics</li>
                    <li>Maintains infrastructure, security patches, and uptime</li>
                </ul>
            </div>
        </div>

        <!-- ===================== LEXI & GARRETT — Side by Side ===================== -->
        <div class="dual-row">

            <!-- Lexi -->
            <div class="glass-card member-card animate-in">
                <div class="card-header">
                    <div class="card-avatar contributor-avatar">&#x2728;</div>
                    <div>
                        <div class="card-name">Lexi</div>
                        <span class="card-role role-contributor"><i class="fa-solid fa-pen-nib"></i> Contributor</span>
                    </div>
                </div>
                <p class="card-description">
                    Lexi is the brilliant creative mind and driving force behind Spencer's Website 3.0. Her exceptional
                    design sensibility, attention to detail, and visionary approach transformed abstract ideas into the
                    stunning, user-friendly interface you see today. Beyond her technical skills, she brings warmth and
                    personality to every pixel.
                </p>
                <div class="card-skills">
                    <span class="skill-pill">UI/UX Design</span>
                    <span class="skill-pill">Creative Direction</span>
                    <span class="skill-pill">Visual Identity</span>
                    <span class="skill-pill">User Experience</span>
                    <span class="skill-pill">Brand Strategy</span>
                </div>
                <div class="card-contributions contrib-border">
                    <h4 class="contrib-title"><i class="fa-solid fa-star"></i> Contributions</h4>
                    <ul>
                        <li>Architected the user interface and navigation system</li>
                        <li>Crafted the unique visual identity and color palette</li>
                        <li>Designed responsive layouts for all devices</li>
                        <li>Curated game content library</li>
                        <li>Implemented intuitive user flows and interactions</li>
                        <li>Pioneered the dark theme with vibrant accent colors</li>
                    </ul>
                </div>
            </div>

            <!-- Garrett -->
            <div class="glass-card member-card animate-in">
                <div class="card-header">
                    <div class="card-avatar designer-avatar">&#x1F3A8;</div>
                    <div>
                        <div class="card-name">Garrett</div>
                        <span class="card-role role-designer"><i class="fa-solid fa-palette"></i> Designer</span>
                    </div>
                </div>
                <p class="card-description">
                    Garrett's strategic mind and boundless creativity laid the foundation for what Spencer's Website
                    would become. His ability to see the bigger picture while understanding user needs made him an
                    invaluable architect of the platform's visual identity and design language.
                </p>
                <div class="card-skills">
                    <span class="skill-pill">Strategic Planning</span>
                    <span class="skill-pill">Innovation</span>
                    <span class="skill-pill">Content Strategy</span>
                    <span class="skill-pill">User Research</span>
                </div>
                <div class="card-contributions design-border">
                    <h4 class="design-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Design Work</h4>
                    <ul>
                        <li>Developed the core concept and feature roadmap</li>
                        <li>Designed content categorization and organization</li>
                        <li>Provided strategic direction for platform growth</li>
                        <li>Identified key user needs and pain points</li>
                        <li>Contributed to game selection and content curation</li>
                        <li>Brainstormed innovative engagement features</li>
                    </ul>
                </div>
            </div>

        </div><!-- /dual-row -->

        <!-- ===================== ALL USERS ===================== -->
        <?php if (!empty($regularUsers)): ?>
        <div class="animate-in">
            <div class="section-title">
                <i class="fa-solid fa-users"></i> All Users
                <span class="line"></span>
            </div>
            <div class="users-grid">
                <?php foreach ($regularUsers as $u): ?>
                    <?php
                        $uName = htmlspecialchars($u['username']);
                        $uRole = htmlspecialchars($u['role']);
                        $uDate = date('M j, Y', strtotime($u['created_at']));
                        $initial = strtoupper(substr($u['username'], 0, 1));
                        // Determine badge class
                        $badgeMap = [
                            'admin'       => 'badge-admin',
                            'contributor' => 'badge-contributor',
                            'designer'    => 'badge-designer',
                            'community'   => 'badge-community',
                        ];
                        $badgeClass = $badgeMap[$u['role']] ?? 'badge-user';
                    ?>
                    <div class="user-card animate-in">
                        <div class="user-avatar-sm"><?php echo $initial; ?></div>
                        <div class="user-meta">
                            <div class="name"><?php echo $uName; ?></div>
                            <div class="joined">Joined <?php echo $uDate; ?></div>
                        </div>
                        <span class="role-badge <?php echo $badgeClass; ?>"><?php echo ucfirst(str_replace('-', ' ', $uRole)); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /container -->

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

    <script>
        // ========== PARTICLE SYSTEM ==========
        (function () {
            const container = document.getElementById('particles');
            const count = 40;
            for (let i = 0; i < count; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                const size = Math.random() * 2.5 + 1;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDuration = (Math.random() * 12 + 10) + 's';
                p.style.animationDelay = (Math.random() * 15) + 's';
                container.appendChild(p);
            }
        })();

        // ========== STAGGERED ENTRANCE VIA INTERSECTION OBSERVER ==========
        (function () {
            const elements = document.querySelectorAll('.animate-in');
            let delay = 0;

            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        // Apply incremental delay for stagger effect
                        const el = entry.target;
                        const d = parseFloat(el.dataset.delay || 0);
                        el.style.transitionDelay = d + 'ms';
                        el.classList.add('visible');
                        observer.unobserve(el);
                    }
                });
            }, {
                threshold: 0.08,
                rootMargin: '0px 0px -40px 0px'
            });

            // Assign staggered delays per group of siblings
            let currentParent = null;
            let groupIndex = 0;

            elements.forEach(function (el) {
                if (el.parentElement !== currentParent) {
                    currentParent = el.parentElement;
                    groupIndex = 0;
                }
                el.dataset.delay = groupIndex * 80;
                groupIndex++;
                observer.observe(el);
            });
        })();
    </script>

</body>
</html>

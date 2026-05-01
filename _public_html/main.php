<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/background_system.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/simple_cache.php';

// Security headers (safety net for non-Apache hosts — .htaccess is primary)
setSecurityHeaders();

// Check if user is logged in (valid session required)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    session_unset();
    session_destroy();
    header('Location: /index.php');
    exit;
}

// Mark this as the authorized main-page orchestrator so section includes open.
define('MAIN_PAGE_LOADED', true);

$user_role = $_SESSION['role'] ?? 'community';

// Fetch active announcements with improved error handling
$announcements = [];
$active_designer_background = null;
$available_backgrounds = [];
$db = null; // Initialize to avoid undefined variable if connection fails

try {
    $db = db();

    // Get active announcements - CACHE FOR 60 SECONDS (announcements rarely change)
    $cache_key = 'announcements_' . $user_role;

    $announcements = cache_get_or_set($cache_key, function() use ($db, $user_role) {
        try {
            $stmt = $db->prepare("
                SELECT a.id, a.title, a.message, a.type, a.priority, a.color, a.tags,
                       a.created_at, a.expiry_date, u.username as created_by_name
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.is_active = 1
                  AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
                  AND (a.target_audience = 'all' OR a.target_audience = ? OR a.target_audience IS NULL)
                ORDER BY FIELD(a.priority, 'critical', 'high', 'medium', 'low'), a.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $queryEx) {
            // Fallback if priority/expiry/target_audience columns don't exist yet
            error_log("Announcement query error: " . $queryEx->getMessage());
            $stmt = $db->query("
                SELECT a.id, a.title, a.message, a.type, a.priority, a.color, a.tags,
                       a.created_at, a.expiry_date, u.username as created_by_name
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.is_active = 1
                ORDER BY a.created_at DESC
                LIMIT 5
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }, 60); // Cache for 60 seconds
    
    // v7.0: Contributor/designer announcement queries removed — only admin announcements now
    
    // Get active designer background - CACHE FOR 5 MINUTES
    $active_designer_background = cache_get_or_set('active_designer_bg', function() use ($db) {
        $bgStmt = $db->query("
            SELECT db.image_url, db.title, u.username as designer_name
            FROM designer_backgrounds db
            LEFT JOIN users u ON db.user_id = u.id
            WHERE db.is_active = 1 AND db.status = 'approved'
            LIMIT 1
        ");
        return $bgStmt->fetch(PDO::FETCH_ASSOC);
    }, 300);

    // Get all approved backgrounds - CACHE FOR 5 MINUTES
    $available_backgrounds = cache_get_or_set('available_backgrounds', function() use ($db) {
        $allBgStmt = $db->query("
            SELECT db.id, db.image_url, db.title, u.username as designer_name
            FROM designer_backgrounds db
            LEFT JOIN users u ON db.user_id = u.id
            WHERE db.status = 'approved'
            ORDER BY db.is_active DESC, db.created_at DESC
        ");
        return $allBgStmt->fetchAll(PDO::FETCH_ASSOC);
    }, 300);
    
} catch (Exception $e) {
    error_log("Database fetch error: " . $e->getMessage());
}

// Get user info for display
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest');
$role = htmlspecialchars($_SESSION['role'] ?? 'community');
$user_id = $_SESSION['user_id'] ?? 0;
$is_guest = !empty($_SESSION['is_guest']);

// Get unread announcements count - CACHE FOR 30 SECONDS
$unread_announcements = 0;
if ($db) {
    $unread_cache_key = 'unread_count_' . $user_id . '_' . $user_role;
    $unread_announcements = cache_get_or_set($unread_cache_key, function() use ($db, $user_id, $user_role) {
        try {
            // OPTIMIZED: Get last_seen first to avoid correlated subquery
            $last_seen_stmt = $db->prepare("SELECT MAX(last_seen) FROM user_announcements WHERE user_id = ?");
            $last_seen_stmt->execute([$user_id]);
            $last_seen = $last_seen_stmt->fetchColumn() ?: '2000-01-01';

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM announcements a
                WHERE a.is_active = 1
                  AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
                  AND (a.target_audience = 'all' OR a.target_audience = ? OR a.target_audience IS NULL)
                  AND a.created_at > ?
            ");
            $stmt->execute([$user_role, $last_seen]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // Fallback without new columns
            try {
                $last_seen_stmt = $db->prepare("SELECT last_seen FROM user_announcements WHERE user_id = ?");
                $last_seen_stmt->execute([$user_id]);
                $last_seen = $last_seen_stmt->fetchColumn() ?: '2000-01-01';

                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM announcements a
                    WHERE a.is_active = 1
                      AND a.created_at > ?
                ");
                $stmt->execute([$last_seen]);
                return (int)$stmt->fetchColumn();
            } catch (Exception $e2) {
                error_log("Announcements count fallback failed: " . $e2->getMessage());
                return 0;
            }
        }
    }, 30); // Cache for 30 seconds
}

// Get contributor stats if user is contributor - CACHE FOR 2 MINUTES
$contributor_stats = [];
if ($role === 'contributor' && $db) {
    $contributor_stats = cache_get_or_set('contributor_stats_' . $user_id, function() use ($db, $user_id) {
        try {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_ideas,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_ideas,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_ideas
                FROM contributor_ideas
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Contributor stats error: " . $e->getMessage());
            return [];
        }
    }, 120);
}

// Get designer stats if user is designer - CACHE FOR 2 MINUTES
$designer_stats = [];
if ($role === 'designer' && $db) {
    $designer_stats = cache_get_or_set('designer_stats_' . $user_id, function() use ($db, $user_id) {
        try {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_backgrounds,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_backgrounds,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_backgrounds
                FROM designer_backgrounds
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Designer stats error: " . $e->getMessage());
            return [];
        }
    }, 120);
}

// Smail: Get unread count + latest 5 messages - CACHE FOR 15 SECONDS (messages are time-sensitive)
$smail_unread = 0;
$smail_latest = [];
$smail_available = ($role !== 'community');
if ($smail_available && $db) {
    $smail_data = cache_get_or_set('smail_preview_' . $user_id, function() use ($db, $user_id) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE receiver_id = ? AND read_status = FALSE");
            $stmt->execute([$user_id]);
            $unread = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT sm.id, sm.title, sm.urgency_level, sm.color_code, sm.read_status, sm.created_at,
                       u.username AS sender_name
                FROM smail_messages sm
                LEFT JOIN users u ON sm.sender_id = u.id
                WHERE sm.receiver_id = ?
                ORDER BY sm.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $latest = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['unread' => $unread, 'latest' => $latest];
        } catch (Exception $e) {
            error_log("Smail main.php error: " . $e->getMessage());
            return ['unread' => 0, 'latest' => []];
        }
    }, 15);
    $smail_unread = $smail_data['unread'];
    $smail_latest = $smail_data['latest'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Spencer's Website — Your personal dashboard.">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
    <meta name="theme-color" content="#04040A">
    <meta name="color-scheme" content="dark">

    <title>Dashboard · Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">

    <!-- Stylesheets (tokens first for cascade correctness) -->
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/command-palette.css">
    <link rel="stylesheet" href="css/toast.css">
    <link rel="stylesheet" href="css/skeleton.css">
    <link rel="stylesheet" href="css/cinematic-bg.css?v=7.1">
    <link rel="stylesheet" href="main/css/main-page.css?v=7.1">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">

    <!-- v7.1 Cinematic background module -->
    <script src="js/cinematic-bg.js?v=7.1" defer></script>

    <!-- Deferred scripts -->
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <script src="common.js" defer></script>
    <script src="js/toast.js" defer></script>
    <script src="js/command-palette.js" defer></script>
    <script src="js/maintenance-heartbeat.js" defer></script>
    <script src="js/announcements.js?v=7.1" defer></script>
</head>

<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>'
      data-active-background='<?php echo htmlspecialchars($active_designer_background['image_url'] ?? '', ENT_QUOTES); ?>'
      data-user-role='<?php echo htmlspecialchars($role, ENT_QUOTES); ?>'
      data-username='<?php echo htmlspecialchars($username, ENT_QUOTES); ?>'
      data-user-id='<?php echo htmlspecialchars($user_id, ENT_QUOTES); ?>'
      data-cinematic-bg='dim'>

    <?php require_once __DIR__ . '/includes/identity_bar_v2.php'; ?>

    <!-- Background theme override layer -->
    <div class="bg-theme-override" id="bgThemeOverride"></div>

    <!-- Background selection modal (partial) -->
    <?php require __DIR__ . '/main/partials/background_modal.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <main id="mainContent" class="mp-root" role="main">

        <!-- Hero / Welcome banner -->
        <?php require __DIR__ . '/main/sections/hero.php'; ?>

        <!-- Status banners (guest / subscription / upgrade flash) -->
        <?php require __DIR__ . '/main/sections/banners.php'; ?>

        <!-- v7.1: Animated stats panel (count-up tiles) -->
        <?php require __DIR__ . '/main/sections/stats_panel.php'; ?>

        <!-- Upgrade panel (community users only) -->
        <?php require __DIR__ . '/main/sections/upgrade_panel.php'; ?>

        <!-- About blurb (existing section) -->
        <?php require __DIR__ . '/main/sections/about.php'; ?>

        <!-- Announcements (existing section) -->
        <?php require __DIR__ . '/main/sections/announcements.php'; ?>

        <!-- v7.1: Recent activity feed (last 10 events for this user) -->
        <?php require __DIR__ . '/main/sections/activity_feed.php'; ?>

        <!-- v7.1: Notifications panel (system + ephemeral) -->
        <?php require __DIR__ . '/main/sections/notifications_panel.php'; ?>

        <!-- Main navigation cards -->
        <?php require __DIR__ . '/main/sections/nav_grid.php'; ?>

    </main>

    <!-- Page behaviors -->
    <script src="main/js/main-page.js" defer></script>

    <!-- Quick Actions hint -->
    <div class="cp-hint" id="cpHint" title="Press Ctrl+K for quick actions">
        <kbd>Ctrl</kbd><span>+</span><kbd>K</kbd>
    </div>

    <!-- Auto Save -->
    <script src="js/auto-save.js"></script>

    <!-- Skeleton Loader -->
    <script src="js/skeleton-loader.js"></script>

    <!-- Shared Site Footer (Privacy, Terms, AUP, Cookies, Community Standards, Refunds, DMCA, Children's Privacy, Accessibility, Support) -->
    <?php require __DIR__ . '/includes/site_footer.php'; ?>

<style>
.cp-hint {
    position: fixed;
    bottom: 22px;
    right: 22px;
    z-index: 9000;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    border-radius: 10px;
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    -webkit-backdrop-filter: var(--glass-blur);
    border: var(--border);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    color: var(--text-muted);
    font-size: 12px;
    font-family: var(--font-sans, system-ui, sans-serif);
    cursor: pointer;
    transition: opacity .3s ease, transform .2s ease;
    animation: cpHintPulse 4s ease-in-out infinite;
}
.cp-hint:hover { opacity: 1 !important; transform: translateY(-2px); }
.cp-hint kbd {
    background: var(--bg-elevated);
    border: var(--border-subtle);
    border-radius: 5px;
    padding: 2px 6px;
    font-size: 11px;
    font-family: inherit;
    color: var(--text-soft);
}
@keyframes cpHintPulse {
    0%, 100% { opacity: .55; box-shadow: 0 0 0 0 rgba(123,110,246,0); }
    50% { opacity: .9; box-shadow: 0 0 0 6px rgba(123,110,246,.12); }
}
@media (max-width: 640px) {
    .cp-hint { display: none; }
}
</style>
<script>
(function(){
    const hint = document.getElementById('cpHint');
    if (!hint) return;
    // Fade permanently after first palette open
    const onPaletteOpen = () => {
        hint.style.animation = 'none';
        hint.style.opacity = '.35';
        localStorage.setItem('cp_hint_seen', '1');
    };
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') onPaletteOpen();
    });
    hint.addEventListener('click', () => {
        if (window.commandPalette) window.commandPalette.open();
        onPaletteOpen();
    });
    // If already seen, keep subtle
    if (localStorage.getItem('cp_hint_seen')) {
        hint.style.animation = 'none';
        hint.style.opacity = '.35';
    }
})();
</script>
</body>
</html>

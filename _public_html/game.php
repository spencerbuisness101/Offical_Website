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
    <link rel="stylesheet" href="games/css/game-page.css">

    <!-- v7.1 Cinematic background module -->
    <script src="js/cinematic-bg.js?v=7.1" defer></script>
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

        <?php require __DIR__ . '/games/sections/header.php'; ?>

        <?php require __DIR__ . '/games/sections/filters.php'; ?>

        <?php require __DIR__ . '/games/sections/game_grid.php'; ?>

    </main>

        <!-- Command Palette -->
    <script src="js/command-palette.js"></script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>
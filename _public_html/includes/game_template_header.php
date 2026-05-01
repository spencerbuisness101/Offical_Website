<?php
/**
 * Game Template Header
 *
 * Common PHP initialization and HTML head for game pages.
 * Usage: Set $page_title and $page_description before including.
 *
 * Required variables:
 * - $page_title: Title of the game
 * - $page_description: Meta description (optional)
 */

// Ensure APP_RUNNING is defined
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/init.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Load background system if available
$__bgfile = __DIR__ . '/../load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }

// Fetch active designer background
$active_designer_background = null;
$available_backgrounds = [];

try {
    require_once __DIR__ . '/../config/database.php';
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

// Set defaults for optional variables
$page_title = isset($page_title) ? htmlspecialchars($page_title) : 'Game';
$page_description = isset($page_description) ? htmlspecialchars($page_description) : "Play {$page_title} - Spencer's Game Collection";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $page_description; ?>">
    <title><?php echo $page_title; ?> - Spencer's Website</title>

    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/css/tokens.css">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/css/game-common.css">

    <script src="/js/tracking.js?v=7.0" defer></script>
    <script src="/common.js"></script>
    <script src="/js/game-settings.js"></script>
    <script src="/js/game-common.js"></script>
</head>
<body>

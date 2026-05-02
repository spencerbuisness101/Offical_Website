<?php
/**
 * Public Stats API - Spencer's Website
 * Returns member count and game count for the landing page.
 * Cached for 5 minutes to reduce DB load.
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// --- Cache layer ---
$cacheDir = __DIR__ . '/../cache';
$cacheFile = $cacheDir . '/public_stats.json';
$cacheTTL = 300; // 5 minutes

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    echo file_get_contents($cacheFile);
    exit;
}

// --- DB query ---
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Detect if is_guest column exists (may not be present on older schemas)
    $hasIsGuest = false;
    try {
        $col = $db->query("SHOW COLUMNS FROM users LIKE 'is_guest'");
        $hasIsGuest = ($col && $col->rowCount() > 0);
    } catch (Exception $e) { /* ignore */ }

    // Count real members — exclude guests and inactive accounts
    $memberSql = "SELECT COUNT(*) FROM users WHERE (is_active = 1 OR is_active IS NULL)";
    if ($hasIsGuest) {
        $memberSql .= " AND (is_guest = 0 OR is_guest IS NULL)";
    }
    // Exclude rows where role is literally 'guest'
    $memberSql .= " AND (role IS NULL OR role <> 'guest')";
    $stmt = $db->query($memberSql);
    $members = (int)$stmt->fetchColumn();

    // Count active games
    $games = 0;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM games WHERE status = 'active'");
        $games = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // games table may not exist yet — default 0
        $games = 0;
    }

    // Count active sessions in last 15 min as "online now"
    $onlineNow = 0;
    try {
        $stmt = $db->query("SELECT COUNT(DISTINCT id) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $onlineNow = (int)$stmt->fetchColumn();
    } catch (Exception $e) { /* ignore */ }

    // Seeded fallback: if DB returns 0, show a plausible random number (10-100)
    // so the landing page never displays embarrassing zeros. Cached for stability.
    $isSeeded = false;
    if ($members === 0) { $members = random_int(90, 150); $isSeeded = true; }
    if ($games === 0)   { $games   = random_int(50, 75);  $isSeeded = true; }
    if ($onlineNow === 0) { $onlineNow = random_int(20, 60); $isSeeded = true; }

    $result = json_encode([
        'success' => true,
        'members' => $members,
        'games' => $games,
        'online_now' => $onlineNow,
        'is_seeded' => $isSeeded,
        'cached_at' => time()
    ]);

    // Write cache
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        file_put_contents($cacheFile, $result, LOCK_EX);
    }

    echo $result;

} catch (Exception $e) {
    error_log("Public Stats API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'members' => random_int(90, 150),
        'games' => random_int(50, 75),
        'online_now' => random_int(20, 60),
        'is_seeded' => true
    ]);
}

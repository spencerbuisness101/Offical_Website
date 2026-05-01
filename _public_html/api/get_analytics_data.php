<?php
/**
 * Analytics Data API
 * Returns analytics data - requires admin authentication
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json');

// Require admin role for analytics access
requireApiRole('admin');

try {
    $db = (new Database())->getConnection();

    // Get popular games
    $stmt = $db->query("
        SELECT game_name, COUNT(*) as plays
        FROM game_analytics
        WHERE action_type = 'start'
        GROUP BY game_name
        ORDER BY plays DESC
        LIMIT 10
    ");
    $popular_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get daily traffic
    $stmt = $db->query("
        SELECT
            DATE(timestamp) as date,
            COUNT(*) as visits,
            COUNT(DISTINCT session_id) as unique_visitors
        FROM page_views
        WHERE timestamp > NOW() - INTERVAL 30 DAY
        GROUP BY DATE(timestamp)
        ORDER BY date
    ");
    $daily_traffic = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user growth
    $stmt = $db->query("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as new_users
        FROM users
        WHERE created_at > NOW() - INTERVAL 30 DAY
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'popular_games' => $popular_games,
        'daily_traffic' => $daily_traffic,
        'user_growth' => $user_growth
    ]);

} catch (Exception $e) {
    error_log("Get analytics data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve analytics data']);
}
?>

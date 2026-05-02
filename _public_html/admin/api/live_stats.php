<?php
/**
 * Admin Live Stats API - Spencer's Website
 * Real-time dashboard statistics for admin panel
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Prevent direct access
define('APP_RUNNING', true);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../includes/init_admin.php';
require_once __DIR__ . '/../../config/database.php';

// Admin authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? 'overview';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $data = [];
    
    switch ($action) {
        case 'overview':
            // Get quick overview stats
            $data = [
                'total_users' => getUserCount($db),
                'active_now' => getActiveUsersCount($db),
                'new_today' => getNewUsersToday($db),
                'total_games' => getGameCount($db),
                'notifications_sent_today' => getNotificationsSentToday($db),
                'server_load' => getServerLoad(),
                'memory_usage' => getMemoryUsage(),
                'timestamp' => time()
            ];
            break;
            
        case 'traffic':
            // Get traffic data for charts
            $hours = intval($_GET['hours'] ?? 24);
            $data = [
                'traffic' => getTrafficData($db, $hours),
                'user_activity' => getUserActivityData($db, $hours),
                'page_views' => getPageViewData($db, $hours)
            ];
            break;
            
        case 'users':
            // Get user statistics
            $data = [
                'by_role' => getUsersByRole($db),
                'registration_trend' => getRegistrationTrend($db, 30),
                'active_sessions' => getActiveSessions($db),
                'top_countries' => getTopCountries($db),
                'device_breakdown' => getDeviceBreakdown($db)
            ];
            break;
            
        case 'content':
            // Get content statistics
            $data = [
                'games_by_category' => getGamesByCategory($db),
                'most_played_games' => getMostPlayedGames($db, 10)
            ];
            break;
            
        case 'system':
            // Get system health
            $data = [
                'cpu_usage' => getCpuUsage(),
                'memory_usage' => getMemoryUsage(),
                'disk_usage' => getDiskUsage(),
                'db_size' => getDatabaseSize($db),
                'response_time' => getAverageResponseTime(),
                'error_rate' => getErrorRate(),
                'uptime' => getUptime()
            ];
            break;
            
        case 'activity_stream':
            // Get real-time activity stream
            $since = intval($_GET['since'] ?? 0);
            $data = [
                'activities' => getRecentActivity($db, $since),
                'new_notifications' => getNewSystemNotifications($db, $since),
                'strikes_applied' => getRecentStrikes($db, $since)
            ];
            break;
            
        case 'notifications':
            // Get notification statistics
            $data = [
                'sent_today' => getNotificationsSentToday($db),
                'sent_this_week' => getNotificationsSentThisWeek($db),
                'delivery_rate' => getNotificationDeliveryRate($db),
                'by_type' => getNotificationsByType($db)
            ];
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Live Stats API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

// Helper functions for statistics

function getUserCount($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
    return (int)$stmt->fetchColumn();
}

function getActiveUsersCount($db) {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP() - 900");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getNewUsersToday($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at > DATE(NOW())");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getGameCount($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM games WHERE status = 'active'");
    return (int)$stmt->fetchColumn();
}

function getNotificationsSentToday($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getNotificationsSentThisWeek($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getTrafficData($db, $hours) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
            COUNT(*) as page_views,
            COUNT(DISTINCT user_id) as unique_users
        FROM page_views 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY hour
        ORDER BY hour ASC
    ");
    $stmt->execute([$hours]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserActivityData($db, $hours) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
            COUNT(*) as actions
        FROM user_activity
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY hour
        ORDER BY hour ASC
    ");
    $stmt->execute([$hours]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPageViewData($db, $hours) {
    $stmt = $db->prepare("
        SELECT 
            page_path,
            COUNT(*) as views
        FROM page_views
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY page_path
        ORDER BY views DESC
        LIMIT 10
    ");
    $stmt->execute([$hours]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUsersByRole($db) {
    $stmt = $db->query("
        SELECT role, COUNT(*) as count
        FROM users
        WHERE status = 'active'
        GROUP BY role
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRegistrationTrend($db, $days) {
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_users
        FROM users
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveSessions($db) {
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_sessions,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, last_activity)) as avg_session_duration
        FROM user_sessions
        WHERE last_activity > UNIX_TIMESTAMP() - 1800
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getMostPlayedGames($db, $limit) {
    $stmt = $db->prepare("
        SELECT 
            g.id, g.title, g.category,
            COUNT(ga.id) as play_count
        FROM games g
        LEFT JOIN game_analytics ga ON g.id = ga.game_id
        WHERE ga.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY g.id
        ORDER BY play_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentActivity($db, $since) {
    $stmt = $db->prepare("
        SELECT 
            'login' as type,
            u.username,
            ua.created_at,
            ua.ip_address
        FROM user_activity ua
        JOIN users u ON ua.user_id = u.id
        WHERE ua.activity_type = 'login'
        AND UNIX_TIMESTAMP(ua.created_at) > ?
        ORDER BY ua.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$since]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentStrikes($db, $since) {
    $stmt = $db->prepare("
        SELECT 
            u.username as user_name,
            s.rule_id,
            s.created_at,
            admin.username as admin_name
        FROM user_strikes s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN users admin ON s.admin_id = admin.id
        WHERE UNIX_TIMESTAMP(s.created_at) > ?
        ORDER BY s.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$since]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNewSystemNotifications($db, $since) {
    $stmt = $db->prepare("
        SELECT 
            type,
            title,
            COUNT(*) as count
        FROM notifications
        WHERE is_system = TRUE
        AND UNIX_TIMESTAMP(created_at) > ?
        GROUP BY type
    ");
    $stmt->execute([$since]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// System health functions
function getServerLoad() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return round($load[0], 2);
    }
    return 0;
}

function getMemoryUsage() {
    $mem = memory_get_usage(true);
    return round($mem / 1024 / 1024, 2); // MB
}

function getCpuUsage() {
    // Simplified CPU usage check
    return getServerLoad() * 10; // Rough estimate
}

function getDiskUsage() {
    $free = disk_free_space('/');
    $total = disk_total_space('/');
    if ($total > 0) {
        return round((($total - $free) / $total) * 100, 1);
    }
    return 0;
}

function getDatabaseSize($db) {
    try {
        $stmt = $db->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
        ");
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getAverageResponseTime() {
    // This would typically come from monitoring
    return rand(50, 300); // Simulated: 50-300ms
}

function getErrorRate() {
    // This would typically come from error logs
    return round(rand(0, 100) / 100, 2); // 0-1%
}

function getUptime() {
    if (function_exists('shell_exec')) {
        $uptime = @shell_exec('uptime -p');
        if ($uptime) {
            return trim($uptime);
        }
    }
    return 'Unknown';
}

function getTopCountries($db) {
    // Placeholder - would need geolocation data
    return [];
}

function getDeviceBreakdown($db) {
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN user_agent LIKE '%Mobile%' THEN 'Mobile'
                WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
                ELSE 'Desktop'
            END as device_type,
            COUNT(*) as count
        FROM user_sessions
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY device_type
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGamesByCategory($db) {
    $stmt = $db->query("
        SELECT category, COUNT(*) as count
        FROM games
        WHERE status = 'active'
        GROUP BY category
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNotificationDeliveryRate($db) {
    // Placeholder calculation
    return 98.5;
}

function getNotificationsByType($db) {
    $stmt = $db->query("
        SELECT type, COUNT(*) as count
        FROM notifications
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY type
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

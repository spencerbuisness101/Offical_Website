<?php
/**
 * Server-Side Page View Tracker - Spencer's Website v5.0
 *
 * Include this file on pages to automatically track page views server-side.
 * This provides tracking even when JavaScript is disabled.
 *
 * Usage: require_once __DIR__ . '/includes/track_pageview.php';
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Only track if session is active
if (session_status() !== PHP_SESSION_ACTIVE) {
    return;
}

// Avoid duplicate tracking within same request
if (defined('PAGE_VIEW_TRACKED')) {
    return;
}
define('PAGE_VIEW_TRACKED', true);

// Don't track API endpoints or assets
$skip_paths = ['/api/', '/assets/', '/cache/', '/config/', '/includes/'];
$current_path = $_SERVER['REQUEST_URI'] ?? '';
foreach ($skip_paths as $skip) {
    if (strpos($current_path, $skip) !== false) {
        return;
    }
}

// Don't track AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}

try {
    $dbConfigPath = __DIR__ . '/../config/database.php';
    if (!file_exists($dbConfigPath)) {
        return;
    }

    require_once $dbConfigPath;

    if (!class_exists('Database')) {
        return;
    }

    $__track_db = new Database();
    $__track_conn = $__track_db->getConnection();

    if (!$__track_conn) {
        return;
    }

    // Get session and user info
    $session_id = session_id();
    $user_id = $_SESSION['user_id'] ?? null;
    $page_url = $_SERVER['REQUEST_URI'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Insert page view
    $stmt = $__track_conn->prepare("
        INSERT INTO page_views (user_id, page_url, session_id)
        VALUES (:user_id, :page_url, :session_id)
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':page_url' => $page_url,
        ':session_id' => $session_id
    ]);

    // Update user session tracking
    $stmt = $__track_conn->prepare("
        INSERT INTO user_sessions
        (session_id, user_id, ip_address, user_agent, last_activity, current_page, page_views)
        VALUES (
            :session_id,
            :user_id,
            :ip_address,
            :user_agent,
            UNIX_TIMESTAMP(),
            :current_page,
            1
        )
        ON DUPLICATE KEY UPDATE
            last_activity = UNIX_TIMESTAMP(),
            current_page = :current_page,
            page_views = page_views + 1
    ");
    $stmt->execute([
        ':session_id' => $session_id,
        ':user_id' => $user_id,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent,
        ':current_page' => $page_url
    ]);

} catch (Exception $e) {
    // Silently fail - tracking shouldn't break the page
    error_log("Page view tracking error: " . $e->getMessage());
}

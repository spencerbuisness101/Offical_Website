<?php
/**
 * Admin Panel Initialization
 * Handles admin authentication, CSRF, and common setup
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/../../includes/init.php';

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /index.php');
    exit;
}

// Admin role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /main.php');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to check if request is AJAX
function is_ajax_request(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Admin Audit Log helper
// Table schema is maintained by migrations/012_consolidate_admin_audit_log.sql
function logAdminAction(PDO $db, string $action, ?int $targetId = null, string $details = ''): void {
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $_SESSION['username'] ?? 'unknown',
            $action,
            $targetId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Admin audit log error: " . $e->getMessage());
    }
}

// Get database connection
try {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Admin DB connection error: " . $e->getMessage());
    die("Database connection failed");
}

// Current admin info
$currentAdmin = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role']
];

// Get counts for sidebar badges
try {
    // Pending contributor ideas
    $stmt = $db->query("SELECT COUNT(*) FROM contributor_ideas WHERE status = 'pending'");
    $pendingIdeas = (int)$stmt->fetchColumn();
    
    // Pending designer backgrounds
    $stmt = $db->query("SELECT COUNT(*) FROM designer_backgrounds WHERE status = 'pending'");
    $pendingBackgrounds = (int)$stmt->fetchColumn();
    
    // Pending PFP approvals
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE pfp_status = 'pending'");
    $pendingPfps = (int)$stmt->fetchColumn();
    
    // Active sessions
    $stmt = $db->query("SELECT COUNT(*) FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP() - 1800");
    $activeSessions = (int)$stmt->fetchColumn();
    
} catch (Exception $e) {
    $pendingIdeas = $pendingBackgrounds = $pendingPfps = $activeSessions = 0;
}

// Determine current tab from URL
$currentTab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard', 'users', 'sessions', 'tracking', 'contributor-ideas', 'designer-backgrounds', 
                'player-adjustments', 'ai-chats', 'admin-messages', 'access-restrictions', 'logs', 
                'announcements', 'performance', 'system-health', 'payment-management'];

if (!in_array($currentTab, $allowedTabs)) {
    $currentTab = 'dashboard';
}

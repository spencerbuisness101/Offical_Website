<?php
// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';


// Security headers — centralized via security.php setSecurityHeaders()
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Community Accounts cannot access YAPS (spec: YAPS is DISABLED for Community tier)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'community') {
    header('Location: main.php');
    exit;
}

// Suspended users cannot access YAPS (view-only enforcement for Time Removal)
if (!empty($_SESSION['is_suspended_punishment'])) {
    header('Location: main.php?suspended=1');
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'] ?? null;

// Initialize variables with safe defaults
$messages = [];
$online_users = [];
$backgrounds = [];
$current_background_url = '';
$db = null;

// Database connection with comprehensive error handling
try {
    if (!file_exists('config/database.php')) {
        throw new Exception('Database configuration file not found');
    }
    
    require_once 'config/database.php';
    
    if (!class_exists('Database')) {
        throw new Exception('Database class not found');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Test connection
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Table schema is maintained by migrations/016_create_missing_tables.sql
    
    // Get backgrounds data with fallback
    try {
        $backgrounds_stmt = $db->query("SELECT id, title, designer_name, image_url FROM backgrounds WHERE is_active = TRUE");
        $backgrounds = $backgrounds_stmt ? $backgrounds_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        // If backgrounds table doesn't exist, use empty array
        $backgrounds = [];
        error_log("Backgrounds table error: " . $e->getMessage());
    }
    
    // Get user's current background with fallback
    try {
        $user_background_stmt = $db->prepare("
            SELECT b.image_url 
            FROM user_backgrounds ub 
            JOIN backgrounds b ON ub.background_id = b.id 
            WHERE ub.user_id = ? AND ub.is_active = TRUE 
            LIMIT 1
        ");
        if ($user_background_stmt) {
            $user_background_stmt->execute([$user_id]);
            $user_background = $user_background_stmt->fetch(PDO::FETCH_ASSOC);
            $current_background_url = $user_background ? $user_background['image_url'] : '';
        }
    } catch (Exception $e) {
        // If user_backgrounds table doesn't exist, use empty string
        $current_background_url = '';
        error_log("User backgrounds error: " . $e->getMessage());
    }
    
    // Get recent chat messages (include user_id for premium tag lookup)
    $stmt = $db->query("
        SELECT user_id, username, user_role, message, timestamp
        FROM yaps_chat_messages
        WHERE is_active = TRUE
        ORDER BY timestamp DESC
        LIMIT 100
    ");
    $messages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Get online users (active in last 5 minutes) with fallback
    try {
        $onlineStmt = $db->query("
            SELECT DISTINCT u.username, u.role 
            FROM users u 
            LEFT JOIN user_sessions us ON u.id = us.user_id 
            WHERE us.last_activity > UNIX_TIMESTAMP() - 300 
            OR u.last_login > NOW() - INTERVAL 5 MINUTE
            LIMIT 20
        ");
        $online_users = $onlineStmt ? $onlineStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        // If user_sessions table doesn't exist, use current user only
        $online_users = [['username' => $username, 'role' => $role]];
        error_log("Online users query error: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    // Log error but don't crash the page
    error_log("Database error in yaps.php: " . $e->getMessage());
    // Continue with empty data - the page will still load
}

// Function to get role tag HTML with custom name tag and premium chat tag support
function getRoleTag($role, $userId = null, $db = null) {
    $customTag = null;

    if ($userId && $db) {
        // First check user_settings for nameTag (available to all non-community users)
        try {
            $stmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'nameTag'");
            $stmt->execute([$userId]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($setting && $setting['setting_value']) {
                $tagValue = json_decode($setting['setting_value'], true);
                if ($tagValue && trim($tagValue) !== '') {
                    $customTag = htmlspecialchars($tagValue);
                }
            }
        } catch (Exception $e) {
            // Table might not exist - silently fail
        }

        // If no nameTag found, check for premium custom chat tag (legacy)
        if (!$customTag) {
            try {
                $stmt = $db->prepare("SELECT chat_tag, is_premium FROM user_premium WHERE user_id = ? AND is_premium = 1 AND chat_tag IS NOT NULL AND chat_tag != ''");
                $stmt->execute([$userId]);
                $premium = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($premium && $premium['chat_tag']) {
                    $customTag = htmlspecialchars($premium['chat_tag']);
                }
            } catch (Exception $e) {
                // Silently fail - use default tag
            }
        }
    }

    // If user has a custom tag, show it
    if ($customTag) {
        return '<span class="role-tag role-premium">⭐ ' . $customTag . '</span>';
    }

    // Default role tags
    switch($role) {
        case 'admin':
            return '<span class="role-tag role-admin">👑 Creator</span>';
        case 'contributor':
            return '<span class="role-tag role-contributor">💡 Contributor</span>';
        case 'designer':
            return '<span class="role-tag role-designer">✨ Designer</span>';
        case 'premium':
        case 'user':
            return '<span class="role-tag role-member">👤 Member</span>';
        case 'community':
        default:
            return '<span class="role-tag role-community">🌐 Community</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Yaps - Real-time Chat Community">
    <title>Yaps Chat - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>

    <style>
        /* Performance Optimizations */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #8b5cf6, #7c3aed);
            --secondary-gradient: linear-gradient(135deg, #4ECDC4, #2a9d8f);
            --accent-gradient: linear-gradient(135deg, #FF6B6B, #e63946);
            --success-gradient: linear-gradient(135deg, #10b981, #059669);
            --warning-gradient: linear-gradient(135deg, #f59e0b, #d97706);
            --danger-gradient: linear-gradient(135deg, #ef4444, #dc2626);
            --background-dark: rgba(0, 0, 0, 0.85);
            --background-light: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-primary: rgba(78, 205, 196, 0.5);
            --border-secondary: rgba(139, 92, 246, 0.5);
            --shadow-primary: 0 8px 25px rgba(0, 0, 0, 0.3);
            --shadow-secondary: 0 4px 15px rgba(139, 92, 246, 0.4);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Background System Integration - Fixed and Optimized */
        #bgThemeOverride {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transition: background-image 0.5s ease;
        }

        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* Header - Optimized */
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 20px;
            background: var(--background-dark);
            border-radius: 15px;
            border: 2px solid var(--border-primary);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-primary);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
            transition: all 0.3s ease;
        }

        .page-header:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
            border-color: rgba(255, 107, 107, 0.5);
        }

        .page-header h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 15px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .page-header p {
            font-size: clamp(1rem, 3vw, 1.4rem);
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 20px;
            font-weight: 600;
        }

        .user-info {
            background: var(--primary-gradient);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
            margin: 0 auto;
            display: block;
            width: fit-content;
            box-shadow: var(--shadow-secondary);
        }

        /* Control Buttons - Fixed and Optimized */
        .control-buttons-container {
            position: fixed;
            top: 25px;
            right: 25px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .control-btn {
            color: #ffffff;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 180px;
            backdrop-filter: blur(10px);
        }

        .control-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.5);
        }

        .btn-back {
            background: var(--accent-gradient);
        }

        .backgrounds-btn {
            background: var(--primary-gradient);
        }

        .setting-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .logout-btn {
            background: var(--danger-gradient);
        }

        /* Chat Layout - Optimized */
        .chat-container {
            display: flex;
            flex: 1;
            gap: 25px;
            margin-bottom: 25px;
            min-height: 600px;
        }

        .chat-sidebar {
            width: 320px;
            background: var(--background-dark);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow-primary);
            border: 2px solid var(--border-primary);
            backdrop-filter: blur(10px);
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--background-dark);
            border-radius: 15px;
            box-shadow: var(--shadow-primary);
            border: 2px solid var(--border-primary);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        /* Messages Container - Optimized */
        .messages-container {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            max-height: 60vh;
            background: rgba(15, 23, 42, 0.6);
        }

        .message {
            margin-bottom: 20px;
            padding: 18px;
            border-radius: 15px;
            background: var(--background-light);
            border-left: 4px solid #8b5cf6;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.4s ease;
        }

        .message:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .message-user {
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            font-size: 1.1em;
        }

        /* Enhanced Role Tags - Combined Premium and Member */
        .role-tag {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .role-admin { 
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
        }

        .role-contributor { 
            background: linear-gradient(135deg, #FFA500, #FF8C00);
            color: #000;
        }

        .role-designer { 
            background: linear-gradient(135deg, #FF69B4, #FF1493);
            color: white;
        }

        .role-member { 
            background: linear-gradient(135deg, #9370DB, #8A2BE2, #4ECDC4);
            color: white;
        }

        .role-community {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .role-premium {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            animation: premiumGlow 2s ease-in-out infinite alternate;
        }

        .message-time {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .message-content {
            line-height: 1.5;
            color: #e2e8f0;
            font-size: 1rem;
            word-wrap: break-word;
        }

        /* Message Input - Optimized */
        .message-input-container {
            padding: 25px;
            border-top: 2px solid var(--border-primary);
            background: rgba(0, 0, 0, 0.9);
        }

        .message-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid var(--border-primary);
            border-radius: 25px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            background: var(--background-light);
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .message-input:focus {
            border-color: var(--border-secondary);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .message-input::placeholder {
            color: var(--text-secondary);
        }

        .send-btn {
            padding: 15px 30px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-secondary);
        }

        .send-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.6);
        }

        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Online Users - Optimized */
        .online-users {
            margin-top: 25px;
        }

        .online-users h3 {
            color: #8b5cf6;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
            border-bottom: 2px solid var(--border-primary);
            padding-bottom: 10px;
        }

        .user-list {
            list-style: none;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: var(--background-light);
            transition: all 0.3s ease;
        }

        .user-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1em;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ECDC4;
            box-shadow: 0 0 10px #4ECDC4;
            animation: pulse 2s infinite;
        }

        /* Quick Actions - Optimized */
        .quick-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary-gradient);
            color: white;
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
        }

        .btn-warning {
            background: var(--warning-gradient);
            color: white;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Chat Rules & Role Guide - Updated */
        .chat-rules, .role-guide {
            background: rgba(0, 0, 0, 0.8);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid var(--border-primary);
        }

        .chat-rules h3, .role-guide h3 {
            color: #8b5cf6;
            margin-bottom: 15px;
            text-align: center;
            font-size: 1.2em;
        }

        .rules-list, .role-list {
            list-style: none;
        }

        .rules-list li, .role-list li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #bdc3c7;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rules-list li:last-child, .role-list li:last-child {
            border-bottom: none;
        }

        .rules-list li::before {
            content: '✅';
            font-size: 1.1em;
        }

        /* Optimized Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @keyframes premiumGlow {
            0% { box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3); }
            100% { box-shadow: 0 2px 15px rgba(245, 158, 11, 0.6); }
        }

        /* Scrollbar Styling - Optimized */
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }

        .messages-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .messages-container::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 4px;
        }

        /* Notification System - Optimized */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            background: var(--success-gradient);
            color: white;
            box-shadow: var(--shadow-primary);
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            max-width: 350px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: var(--danger-gradient);
        }

        .notification.warning {
            background: var(--warning-gradient);
        }

        /* Typing Indicator - Optimized */
        .typing-indicator {
            padding: 10px 15px;
            color: var(--text-secondary);
            font-style: italic;
            display: none;
            background: var(--background-light);
            border-radius: 10px;
            margin: 10px 25px;
        }

        .typing-indicator.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Message Actions - Optimized */
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .message:hover .message-actions {
            opacity: 1;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--text-secondary);
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Connection Status - Optimized */
        .connection-status {
            position: fixed;
            bottom: 20px;
            left: 20px;
            padding: 8px 15px;
            border-radius: 20px;
            background: var(--success-gradient);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-primary);
        }

        .connection-status.offline {
            background: var(--danger-gradient);
        }

        .connection-status.reconnecting {
            background: var(--warning-gradient);
        }

        /* Background Modal - Fixed and Optimized */
        .background-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }

        .background-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            width: 90%;
            max-width: 1000px;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .background-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .background-modal-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .background-modal-close:hover {
            color: white;
        }

        .backgrounds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .background-item {
            background: rgba(15, 23, 42, 0.7);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .background-item:hover {
            transform: translateY(-5px);
            border-color: #8b5cf6;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        .background-item.active {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }

        .background-preview {
            width: 100%;
            height: 150px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .background-info {
            padding: 1rem;
        }

        .background-title {
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .background-designer {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .background-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-set-background {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-set-background:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-remove-background {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove-background:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        /* Responsive Design - Optimized */
        @media (max-width: 1024px) {
            .chat-container {
                flex-direction: column;
            }
            
            .chat-sidebar {
                width: 100%;
                order: 2;
            }
            
            .chat-main {
                order: 1;
            }
            
            .quick-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 250px;
                justify-content: center;
            }
            
            .control-buttons-container {
                position: relative;
                top: 0;
                right: 0;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                margin: 10px auto;
                padding: 0 10px;
            }
        }

        @media (max-width: 768px) {
            .control-buttons-container {
                position: relative;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                margin: 10px auto;
                padding: 0 10px;
            }
            
            .control-btn {
                min-width: 140px;
                font-size: 12px;
                padding: 10px 15px;
            }
            
            .message-form {
                flex-direction: column;
                gap: 10px;
            }
            
            .send-btn {
                width: 100%;
            }
            
            .container {
                padding: 10px;
            }
            
            .backgrounds-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .control-buttons-container {
                flex-direction: column;
                align-items: center;
            }
            
            .control-btn {
                width: 200px;
                min-width: 200px;
            }
            
            .chat-sidebar, .chat-main {
                border-radius: 10px;
            }
            
            .messages-container {
                padding: 15px;
            }
            
            .message-input-container {
                padding: 15px;
            }
            
            .backgrounds-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States - Optimized */
        .loading {
            position: relative;
            color: transparent !important;
        }

        .loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error States - Optimized */
        .error-message {
            background: var(--danger-gradient);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
            font-weight: 600;
        }

        .retry-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s ease;
        }

        .retry-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($backgrounds), ENT_QUOTES, 'UTF-8'); ?>' data-user-background="<?php echo htmlspecialchars($current_background_url); ?>">
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <!-- Background System Elements -->
    <div id="bgThemeOverride"></div>
    <div class="bg-overlay"></div>

    <!-- Background Selection Modal -->
    <div id="backgroundModal" class="background-modal">
        <div class="background-modal-content">
            <div class="background-modal-header">
                <h2 class="background-modal-title">🎨 Choose Your Background</h2>
                <button class="background-modal-close" onclick="closeBackgroundModal()">&times;</button>
            </div>
            
            <div class="backgrounds-grid" id="backgroundsGrid">
                <!-- Background items will be populated by JavaScript -->
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn-remove-background" onclick="removeCustomBackground()" style="padding: 12px 24px; font-size: 1rem;">
                    🗑️ Remove Custom Background
                </button>
            </div>
        </div>
    </div>

    <!-- Connection Status -->
    <div class="connection-status" id="connectionStatus">
        <div class="status-dot"></div>
        <span>Connected</span>
    </div>


    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>💬 Yaps Live Chat</h1>
            <p>Real-time community chat - Connect with everyone instantly!</p>
            <div class="user-info">
                👤 <?php echo htmlspecialchars($username); ?> • 🎭 <?php echo htmlspecialchars(ucfirst($role)); ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <h3>🟢 Online Users</h3>
                <div class="online-users">
                    <ul class="user-list" id="onlineUsersList">
                        <?php if (empty($online_users)): ?>
                            <li class="user-item">
                                <div class="user-avatar">?</div>
                                <span class="user-name">No users online</span>
                            </li>
                        <?php else: ?>
                            <?php foreach ($online_users as $user): ?>
                            <li class="user-item">
                                <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                <div class="status-indicator"></div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="chat-rules">
                    <h3>📝 Chat Rules</h3>
                    <ul class="rules-list">
                        <li>Be respectful to everyone</li>
                        <li>No spam or flooding</li>
                        <li>Keep it SFW</li>
                        <li>May need to refresh page or wait a few seconds to see someone elses chat</li>
                        <li>Have fun! 😊</li>
                    </ul>
                </div>

                <div class="role-guide">
                    <h3>🎭 Role Guide</h3>
                    <ul class="role-list">
                        <li><span class="role-tag role-admin">👑</span> Admin - Creator & Leader</li>
                        <li><span class="role-tag role-contributor">💡</span> Contributor - Creative Minds</li>
                        <li><span class="role-tag role-designer">✨</span> Designer - Visual Artists</li>
                        <li><span class="role-tag role-member">👤</span> Member - Premium Users</li>
                        <li><span class="role-tag role-community">🌐</span> Community - Base Tier</li>
                    </ul>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="chat-main">
                <div class="messages-container" id="messagesContainer">
                    <?php if (empty($messages)): ?>
                        <div class="message">
                            <div class="message-content">
                                💬 Welcome to Yaps Live Chat! Be the first to start the conversation...
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_reverse($messages) as $msg): ?>
                            <div class="message">
                                <div class="message-header">
                                    <div class="message-user">
                                        <?php echo htmlspecialchars($msg['username']); ?>
                                        <?php echo getRoleTag($msg['user_role'], $msg['user_id'] ?? null, $db); ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo date('H:i', strtotime($msg['timestamp'])); ?>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                </div>
                                <div class="message-actions">
                                    <button class="action-btn" onclick="replyToMessage('<?php echo htmlspecialchars($msg['username'], ENT_QUOTES, 'UTF-8'); ?>')">Reply</button>
                                    <button class="action-btn" onclick="copyMessage('<?php echo htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8'); ?>')">Copy</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Typing Indicator -->
                <div class="typing-indicator" id="typingIndicator"></div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <form class="message-form" id="messageForm">
                        <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <input type="text" name="message" class="message-input"
                               placeholder="Type your message here... (Press Enter to send)"
                               maxlength="500" required id="messageInput">
                        <button type="submit" class="send-btn" id="sendBtn">Send 🚀</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="btn btn-primary" onclick="scrollToBottom()">
                📜 Scroll to Bottom
            </button>
            <button class="btn btn-secondary" onclick="refreshChat()">
                🔄 Refresh Chat
            </button>
            <button class="btn btn-success" onclick="exportChat()">
                💾 Export Chat
            </button>
            <button class="btn btn-warning" onclick="clearChat()">
                🧹 Clear Chat
            </button>
        </div>
    </div>

    <!-- Notification Element -->
    <div class="notification" id="notification"></div>

    <script>
        // Enhanced Chat System with Fixed Background and Logout
        class YapsChat {
            constructor() {
                this.refreshInterval = null;
                this.lastMessageCount = <?php echo count($messages); ?>;
                this.typingTimer = null;
                this.isTyping = false;
                this.isOnline = true;
                this.retryCount = 0;
                this.maxRetries = 5;
                this.messageCache = new Map(); // Cache for message elements
                
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.startAutoRefresh();
                this.updateConnectionStatus();
                this.scrollToBottom();
                
                // Focus on message input
                document.getElementById('messageInput').focus();
                
                // Setup background system
                this.setupBackgroundSystem();
            }

            setupEventListeners() {
                // Form submission
                document.getElementById('messageForm').addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.sendMessage();
                });

                // Typing detection
                document.getElementById('messageInput').addEventListener('input', () => {
                    this.handleTyping();
                });

                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    this.handleKeyboardShortcuts(e);
                });

                // Online/offline detection
                window.addEventListener('online', () => {
                    this.handleConnectionRestored();
                });

                window.addEventListener('offline', () => {
                    this.handleConnectionLost();
                });

                // Performance: Throttle scroll events
                let scrollTimeout;
                const container = document.getElementById('messagesContainer');
                container.addEventListener('scroll', () => {
                    if (!scrollTimeout) {
                        scrollTimeout = setTimeout(() => {
                            scrollTimeout = null;
                        }, 100);
                    }
                });

                // Secure Event Delegation for message actions
                container.addEventListener('click', (e) => {
                    const btn = e.target.closest('.action-btn');
                    if (!btn) return;
                    
                    const action = btn.dataset.action;
                    if (action === 'reply') {
                        this.replyToMessage(btn.dataset.user);
                    } else if (action === 'copy') {
                        this.copyMessage(btn.dataset.text);
                    }
                });
            }

            setupBackgroundSystem() {
                // Apply active background
                this.applyActiveBackground();
                
                // Populate background modal
                this.populateBackgroundModal();
            }

            handleKeyboardShortcuts(e) {
                // Ctrl/Cmd + Enter to send
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.sendMessage();
                }
                
                // Escape to clear input
                if (e.key === 'Escape') {
                    document.getElementById('messageInput').value = '';
                }
                
                // Ctrl/Cmd + K to focus input
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    document.getElementById('messageInput').focus();
                }
            }

            async sendMessage() {
                const messageInput = document.getElementById('messageInput');
                const sendBtn = document.getElementById('sendBtn');
                const message = messageInput.value.trim();
                
                if (!message) return;

                // Disable UI during send
                messageInput.disabled = true;
                sendBtn.disabled = true;
                sendBtn.classList.add('loading');

                try {
                    const formData = new FormData();
                    formData.append('message', message);
                    formData.append('csrf_token', document.getElementById('csrfToken').value);

                    const response = await fetch('post_yaps_message.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    // Success
                    messageInput.value = '';
                    this.showNotification('✅ Message sent!', 'success');
                    this.refreshMessages();
                    this.userStoppedTyping();

                } catch (error) {
                    console.error('Send message error:', error);
                    this.showNotification('❌ Failed to send message', 'error');
                    this.retrySend(message);
                } finally {
                    // Re-enable UI
                    messageInput.disabled = false;
                    sendBtn.disabled = false;
                    sendBtn.classList.remove('loading');
                    messageInput.focus();
                }
            }

            retrySend(message) {
                if (this.retryCount < this.maxRetries) {
                    this.retryCount++;
                    setTimeout(() => {
                        this.showNotification(`🔄 Retrying send... (${this.retryCount}/${this.maxRetries})`, 'warning');
                        document.getElementById('messageInput').value = message;
                        this.sendMessage();
                    }, 2000 * this.retryCount);
                }
            }

            handleTyping() {
                if (!this.isTyping) {
                    this.userStartedTyping();
                }
                clearTimeout(this.typingTimer);
                this.typingTimer = setTimeout(() => this.userStoppedTyping(), 1000);
            }

            userStartedTyping() {
                this.isTyping = true;
                // In a real app, you would send a signal to the server here
            }

            userStoppedTyping() {
                this.isTyping = false;
                // In a real app, you would send a signal to the server here
            }

            startAutoRefresh() {
                // Increased interval for better performance (5 seconds instead of 3)
                this.refreshInterval = setInterval(() => {
                    this.refreshMessages();
                }, 5000);
            }

            async refreshMessages() {
                if (!this.isOnline) return;

                try {
                    const response = await fetch('get_yaps_messages.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const messages = await response.json();
                    
                    if (messages.length !== this.lastMessageCount) {
                        this.updateMessages(messages);
                        this.lastMessageCount = messages.length;
                    }
                    
                    this.retryCount = 0; // Reset retry count on successful refresh
                    this.updateConnectionStatus(true);

                } catch (error) {
                    console.error('Refresh messages error:', error);
                    this.updateConnectionStatus(false);
                }
            }

            updateMessages(messages) {
                const container = document.getElementById('messagesContainer');
                const wasAtBottom = this.isAtBottom();
                
                // Only update if messages changed
                if (container.children.length === messages.length) {
                    let same = true;
                    for (let i = 0; i < messages.length; i++) {
                        if (messages[i].message !== container.children[i].querySelector('.message-content').textContent) {
                            same = false;
                            break;
                        }
                    }
                    if (same) return;
                }

                // Performance: Use DocumentFragment for batch updates
                const fragment = document.createDocumentFragment();
                
                messages.forEach(message => {
                    const messageElement = this.createMessageElement(message);
                    fragment.appendChild(messageElement);
                });
                
                // Clear and append in one operation
                container.innerHTML = '';
                container.appendChild(fragment);
                
                if (wasAtBottom) {
                    this.scrollToBottom();
                }
            }

            createMessageElement(message) {
                // Check cache first for performance
                const cacheKey = `${message.username}-${message.timestamp}-${message.message}`;
                if (this.messageCache.has(cacheKey)) {
                    return this.messageCache.get(cacheKey).cloneNode(true);
                }

                const messageDiv = document.createElement('div');
                messageDiv.className = 'message';
                messageDiv.innerHTML = `
                    <div class="message-header">
                        <div class="message-user">
                            ${this.escapeHtml(message.username)}
                            ${this.getRoleTag(message.user_role, message.custom_tag)}
                        </div>
                        <div class="message-time">
                            ${this.formatTime(message.timestamp)}
                        </div>
                    </div>
                    <div class="message-content">
                        ${this.escapeHtml(message.message)}
                    </div>
                    <div class="message-actions">
                        <button class="action-btn" data-action="reply" data-user="${this.escapeHtml(message.username)}">Reply</button>
                        <button class="action-btn" data-action="copy" data-text="${this.escapeHtml(message.message)}">Copy</button>
                    </div>
                `;
                
                // Cache the element for future use
                this.messageCache.set(cacheKey, messageDiv.cloneNode(true));
                
                return messageDiv;
            }

            getRoleTag(role, customTag = null) {
                // If user has a custom name tag, show it
                if (customTag && customTag.trim() !== '') {
                    return `<span class="role-tag role-premium">⭐ ${this.escapeHtml(customTag)}</span>`;
                }

                // Default role tags
                switch(role) {
                    case 'admin':
                        return '<span class="role-tag role-admin">👑 Creator</span>';
                    case 'contributor':
                        return '<span class="role-tag role-contributor">💡 Contributor</span>';
                    case 'designer':
                        return '<span class="role-tag role-designer">✨ Designer</span>';
                    case 'premium':
                    case 'user':
                        return '<span class="role-tag role-member">👤 Member</span>';
                    case 'community':
                    default:
                        return '<span class="role-tag role-community">🌐 Community</span>';
                }
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            formatTime(timestamp) {
                const date = new Date(timestamp);
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            scrollToBottom() {
                const container = document.getElementById('messagesContainer');
                // Use requestAnimationFrame for smoother scrolling
                requestAnimationFrame(() => {
                    container.scrollTop = container.scrollHeight;
                });
            }

            isAtBottom() {
                const container = document.getElementById('messagesContainer');
                return container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
            }

            replyToMessage(username) {
                const input = document.getElementById('messageInput');
                input.value = `@${username} `;
                input.focus();
                this.showNotification(`↩️ Replying to ${username}`);
            }

            async copyMessage(message) {
                try {
                    await navigator.clipboard.writeText(message);
                    this.showNotification('📋 Message copied to clipboard');
                } catch (err) {
                    console.error('Copy failed: ', err);
                    this.showNotification('❌ Failed to copy message', 'error');
                }
            }

            refreshChat() {
                this.showNotification('🔄 Refreshing chat...');
                this.refreshMessages();
            }

            clearChat() {
                if (confirm('Are you sure you want to clear the chat? This will only clear your view.')) {
                    const container = document.getElementById('messagesContainer');
                    container.innerHTML = '<div class="message"><div class="message-content">💬 Chat cleared. Messages will reappear when new ones are sent.</div></div>';
                    this.showNotification('🧹 Chat cleared');
                }
            }

            exportChat() {
                const messages = document.querySelectorAll('.message');
                let chatText = `Yaps Chat Export - ${new Date().toLocaleString()}\n`;
                chatText += `User: <?php echo htmlspecialchars($username); ?>\n`;
                chatText += `Generated: ${new Date().toLocaleString()}\n\n`;
                
                messages.forEach(message => {
                    const user = message.querySelector('.message-user').textContent.trim();
                    const time = message.querySelector('.message-time').textContent.trim();
                    const content = message.querySelector('.message-content').textContent.trim();
                    
                    chatText += `[${time}] ${user}: ${content}\n`;
                });
                
                const blob = new Blob([chatText], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `yaps-chat-${new Date().toISOString().split('T')[0]}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                this.showNotification('💾 Chat exported successfully!');
            }

            updateConnectionStatus(connected = navigator.onLine) {
                const status = document.getElementById('connectionStatus');
                this.isOnline = connected;
                
                if (connected) {
                    status.className = 'connection-status';
                    status.innerHTML = '<div class="status-dot"></div><span>Connected</span>';
                } else {
                    status.className = 'connection-status offline';
                    status.innerHTML = '<div class="status-dot"></div><span>Offline - Reconnecting...</span>';
                }
            }

            handleConnectionLost() {
                this.updateConnectionStatus(false);
                this.showNotification('⚠️ Connection lost - reconnecting...', 'warning');
            }

            handleConnectionRestored() {
                this.updateConnectionStatus(true);
                this.showNotification('✅ Connection restored!', 'success');
                this.refreshMessages();
            }

            showNotification(message, type = 'success') {
                const notification = document.getElementById('notification');
                notification.textContent = message;
                notification.className = `notification ${type} show`;
                
                setTimeout(() => {
                    notification.classList.remove('show');
                }, type === 'error' ? 5000 : 3000);
            }

            escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Background System Functions - Fixed and working
            openBackgroundModal() {
                const modal = document.getElementById('backgroundModal');
                modal.classList.add('show');
                modal.style.display = 'flex';
                this.populateBackgroundModal();
            }

            closeBackgroundModal() {
                const modal = document.getElementById('backgroundModal');
                modal.classList.remove('show');
                modal.style.display = 'none';
            }

            populateBackgroundModal() {
                const grid = document.getElementById('backgroundsGrid');
                const backgrounds = <?php echo json_encode($backgrounds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                const currentBackground = this.getCurrentBackground();
                
                // Clear existing content
                grid.innerHTML = '';
                
                // Add background items
                backgrounds.forEach(background => {
                    const isActive = currentBackground === background.image_url;
                    
                    const backgroundItem = document.createElement('div');
                    backgroundItem.className = `background-item ${isActive ? 'active' : ''}`;
                    backgroundItem.innerHTML = `
                        <div class="background-preview" style="background-image: url('${background.image_url}')"></div>
                        <div class="background-info">
                            <div class="background-title">${this.escapeHtml(background.title)}</div>
                            <div class="background-designer">By: ${this.escapeHtml(background.designer_name)}</div>
                            <div class="background-actions">
                                <button class="btn-set-background" onclick="yapsChat.setAsBackground('${background.image_url}', '${this.escapeHtml(background.title)}')">
                                    ${isActive ? '✅ Using' : 'Use This'}
                                </button>
                            </div>
                        </div>
                    `;
                    
                    grid.appendChild(backgroundItem);
                });
            }

            setAsBackground(imageUrl, title) {
                // Get current settings or initialize empty object
                let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
                settings.customBackground = imageUrl;
                settings.customBackgroundTitle = title;
                localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));
                
                // Apply the new background
                this.applyActiveBackground();
                
                // Close modal and show confirmation
                this.closeBackgroundModal();
                this.showNotification(`🎨 Background set to "${title}"!`);
            }

            removeCustomBackground() {
                let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
                delete settings.customBackground;
                delete settings.customBackgroundTitle;
                localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));
                
                // Revert to default background
                this.applyActiveBackground();
                
                // Close modal and show confirmation
                this.closeBackgroundModal();
                this.showNotification('🗑️ Custom background removed!');
            }

            getCurrentBackground() {
                const savedSettings = localStorage.getItem('spencerWebsiteSettings');
                if (savedSettings) {
                    const settings = JSON.parse(savedSettings);
                    return settings.customBackground || null;
                }
                return null;
            }

            applyActiveBackground() {
                const bgOverride = document.getElementById('bgThemeOverride');
                if (!bgOverride) return;

                // Check for user's custom background first
                const currentBackground = this.getCurrentBackground();
                if (currentBackground && currentBackground.trim() !== '') {
                    bgOverride.style.backgroundImage = `url('${currentBackground}')`;
                } else {
                    // Use default background
                    bgOverride.style.backgroundImage = '';
                }
            }

            // Cleanup
            destroy() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                if (this.typingTimer) {
                    clearTimeout(this.typingTimer);
                }
                this.messageCache.clear(); // Clear cache
            }
        }

        // Utility functions
        function logout() {
            if (confirm('Are you sure you want to log out?')) {
                const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                window.location.href = 'auth/logout.php?csrf_token=' + encodeURIComponent(_csrfToken);
            }
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, type === 'error' ? 5000 : 3000);
        }

        // Initialize when page loads
        let yapsChat;
        document.addEventListener('DOMContentLoaded', function() {
            yapsChat = new YapsChat();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (yapsChat) {
                yapsChat.destroy();
            }
        });

        // Global functions for HTML onclick handlers
        window.scrollToBottom = () => yapsChat.scrollToBottom();
        window.refreshChat = () => yapsChat.refreshChat();
        window.clearChat = () => yapsChat.clearChat();
        window.exportChat = () => yapsChat.exportChat();
        window.replyToMessage = (username) => yapsChat.replyToMessage(username);
        window.copyMessage = (message) => yapsChat.copyMessage(message);
        window.openBackgroundModal = () => yapsChat.openBackgroundModal();
        window.closeBackgroundModal = () => yapsChat.closeBackgroundModal();
        window.setAsBackground = (imageUrl, title) => yapsChat.setAsBackground(imageUrl, title);
        window.removeCustomBackground = () => yapsChat.removeCustomBackground();

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('backgroundModal');
            if (event.target === modal) {
                yapsChat.closeBackgroundModal();
            }
        });

        // Keyboard shortcuts for background system
        document.addEventListener('keydown', function(e) {
            // Ctrl + B for backgrounds
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                yapsChat.openBackgroundModal();
            }
            // Escape to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('backgroundModal');
                if (modal.classList.contains('show')) {
                    yapsChat.closeBackgroundModal();
                }
            }
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
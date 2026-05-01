<?php
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

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <script src="common.js"></script>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="control_buttons.css">
    
    <meta charset="UTF-8">
    <link rel="icon" href="/assets/images/favicon.webp">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans & Updates - Spencer's Website</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Background System - Match other pages */
        .bg-theme-override {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            transition: background-image 0.5s ease-in-out;
        }

        .bg-theme-override.designer-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }

        /* Background Selection Modal - Match other pages */
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

        /* Control Buttons - Match other pages */
        .control-buttons-container {
            position: fixed;
            top: 25px;
            right: 25px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1001;
        }

        .logout-btn, .setting-btn, .backgrounds-btn {
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
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
            white-space: nowrap;
        }

        .setting-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .backgrounds-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .logout-btn:hover, .setting-btn:hover, .backgrounds-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.5);
            color: #ffffff;
        }

        /* Active Background Info - Match other pages */
        .background-info-section {
            text-align: center;
            margin: 20px auto;
            padding: 15px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            max-width: 600px;
            border: 1px solid rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(10px);
        }

        .background-info-section h3 {
            color: #8b5cf6;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .background-info-section p {
            color: #94a3b8;
            margin: 5px 0;
            font-size: 0.9em;
        }

        /* Page Header - Match other pages */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 20px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 15px;
            border: 2px solid transparent;
            background-clip: padding-box;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            transition: all 0.3s ease;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            transition: all 0.3s ease;
        }

        .page-header:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            border-color: rgba(255, 107, 107, 0.3);
        }

        .page-header:hover::before {
            background: linear-gradient(45deg, #4ECDC4, #FF6B6B);
            height: 6px;
        }

        .page-header h1 {
            font-size: 3em;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }

        .page-header:hover h1 {
            background: linear-gradient(45deg, #4ECDC4, #FF6B6B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.6);
        }

        .page-header p {
            font-size: 1.4em;
            color: #bdc3c7;
            max-width: 600px;
            margin: 0 auto 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .page-header:hover p {
            color: #ffffff;
            transform: scale(1.05);
        }

        /* Update Cards - Enhanced to match other pages */
        .update-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .update-card {
            background: rgba(0, 0, 0, 0.85);
            border: 2px solid #4ECDC4;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.4s ease;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .update-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.6s;
        }
        
        .update-card:hover::before {
            left: 100%;
        }
        
        .update-card:hover {
            transform: translateY(-8px) scale(1.02);
            background: rgba(0, 0, 0, 0.9);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            border-color: #FF6B6B;
        }
        
        .update-card h3 {
            color: white;
            margin-bottom: 15px;
            font-size: 22px;
            font-weight: 700;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .update-card p {
            color: #bdc3c7;
            margin-bottom: 15px;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .update-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .status-planned {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .status-inprogress {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-fixed {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }
        
        .update-date {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .section-title {
            text-align: center;
            margin: 40px 0 20px;
            color: white;
            font-size: 2.5em;
            font-weight: 800;
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        /* Navigation Buttons - Match other pages */
        .centered-box {
            display: inline-block;
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid #4ECDC4;
            border-radius: 10px;
            padding: 12px 24px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
            backdrop-filter: blur(10px);
        }

        .centered-box:hover {
            background: rgba(0, 0, 0, 0.9);
            border-color: #FF6B6B;
            transform: translateY(-2px);
            color: #ffffff;
        }

        .back-button {
            display: inline-block;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            margin: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .back-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
            color: white;
        }

        /* Container - Match other pages */
        .container {
            text-align: center;
            margin: 20px 0;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
            padding: 0 20px;
            box-sizing: border-box;
        }

        /* Notification - Match other pages */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: #10b981;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #ef4444;
        }

        /* Animation for update cards */
        .update-card {
            opacity: 1;
            transform: translateY(0px);
            transition: opacity 0.6s, transform 0.6s;
        }

        /* Version Hero Banner */
        .version-hero {
            max-width: 1200px;
            margin: 0 auto 30px;
            padding: 40px 30px;
            text-align: center;
            background: rgba(0, 0, 0, 0.85);
            border-radius: 20px;
            border: 2px solid rgba(78, 205, 196, 0.3);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        .version-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4, #FF6B6B);
            background-size: 200% 100%;
            animation: heroGradientShift 3s ease infinite;
        }

        @keyframes heroGradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .version-hero .version-label {
            display: inline-block;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .version-hero .version-number {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .version-hero .version-tagline {
            font-size: 1.15rem;
            color: #94a3b8;
            font-weight: 500;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Version Timeline Navigation */
        .version-timeline-nav {
            max-width: 1200px;
            margin: 0 auto 25px;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .version-timeline-nav-inner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(0, 0, 0, 0.9);
            border-radius: 12px;
            border: 1px solid rgba(78, 205, 196, 0.2);
            backdrop-filter: blur(15px);
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #4ECDC4 transparent;
        }

        .version-timeline-nav-inner::-webkit-scrollbar {
            height: 4px;
        }

        .version-timeline-nav-inner::-webkit-scrollbar-track {
            background: transparent;
        }

        .version-timeline-nav-inner::-webkit-scrollbar-thumb {
            background: #4ECDC4;
            border-radius: 4px;
        }

        .version-nav-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
            padding-right: 10px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .version-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #cbd5e1;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .version-nav-link:hover {
            background: rgba(78, 205, 196, 0.15);
            border-color: #4ECDC4;
            color: #4ECDC4;
            transform: translateY(-1px);
        }

        .version-nav-link.active {
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.2), rgba(255, 107, 107, 0.2));
            border-color: #4ECDC4;
            color: white;
        }

        .version-nav-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .control-buttons-container {
                position: relative;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                top: 0;
                right: 0;
                margin-bottom: 20px;
            }

            .logout-btn, .setting-btn, .backgrounds-btn {
                font-size: 12px;
                padding: 10px 15px;
            }

            .backgrounds-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 2em;
            }

            .update-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 0 10px;
            }

            .update-card {
                padding: 20px;
            }

            .section-title {
                font-size: 2em;
            }

            .version-hero .version-number {
                font-size: 2.8rem;
            }

            .version-hero {
                padding: 30px 20px;
                margin-left: 10px;
                margin-right: 10px;
            }

            .version-timeline-nav {
                padding: 0 10px;
            }

            .version-timeline-nav-inner {
                padding: 10px 12px;
                gap: 8px;
            }

            .version-nav-link {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5em;
            }

            .page-header p {
                font-size: 1rem;
            }

            .page-header {
                padding: 20px 15px;
                margin-bottom: 25px;
            }

            .version-hero .version-number {
                font-size: 2.2rem;
            }

            .version-hero .version-tagline {
                font-size: 0.95rem;
            }

            .version-hero {
                padding: 25px 15px;
                margin-left: 8px;
                margin-right: 8px;
                border-radius: 14px;
            }

            .section-title {
                font-size: 1.6em;
            }

            .update-card {
                padding: 15px;
            }

            .update-card h3 {
                font-size: 18px;
            }

            .update-card p {
                font-size: 13px;
            }

            .update-status {
                padding: 6px 12px;
                font-size: 12px;
            }

            .update-grid {
                padding: 0 8px;
                gap: 15px;
            }

            .control-buttons-container {
                gap: 6px;
            }

            .logout-btn, .setting-btn, .backgrounds-btn {
                font-size: 11px;
                padding: 8px 12px;
            }

            .centered-box {
                padding: 10px 18px;
                font-size: 13px;
            }

            .back-button {
                padding: 12px 20px;
                font-size: 13px;
            }

            .filter-btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            .version-timeline-nav-inner {
                padding: 8px 10px;
                gap: 6px;
            }

            .version-nav-label {
                font-size: 0.7rem;
                padding-right: 8px;
            }

            .version-nav-link {
                padding: 5px 10px;
                font-size: 0.75rem;
            }

            #updateSearch {
                font-size: 13px !important;
                padding: 10px 12px !important;
            }
        }
    </style>
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>'
      data-active-background='<?php echo htmlspecialchars($active_designer_background['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <!-- Background Theme Override Element -->
    <div class="bg-theme-override" id="bgThemeOverride"></div>

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

    <div class="container">
        <a href="main.php" class="centered-box">← Back to Main Site</a>
    </div>
    
    <header class="page-header">
        <h1>🔄 Plans & Updates</h1>
        <p>Stay informed about the latest developments, fixes, and upcoming features</p>
        <div style="margin-top: 15px; padding: 10px 25px; background: linear-gradient(45deg, #8b5cf6, #7c3aed); border-radius: 25px; display: inline-block; font-size: 1em; font-weight: 700; color: white; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);">
            <span style="font-size: 1.2em;">🚀</span> Version 7.0 - Complete Overhaul!
        </div>
    </header>

    <!-- Version Hero Banner -->
    <div class="version-hero">
        <div class="version-label">Current Version</div>
        <div class="version-number">7.0</div>
        <div class="version-tagline">Complete Platform Overhaul -- Multi-layer tracking, redesigned pages, AI upgrades, and more</div>
    </div>

    <!-- Version Timeline Navigation -->
    <nav class="version-timeline-nav" id="versionNav">
        <div class="version-timeline-nav-inner">
            <span class="version-nav-label">Jump to</span>
            <a class="version-nav-link" href="#planned-section" onclick="scrollToSection(event, 'planned-section')">
                <span class="version-nav-dot" style="background: #3b82f6;"></span> Planned
            </a>
            <a class="version-nav-link" href="#fixes-section" onclick="scrollToSection(event, 'fixes-section')">
                <span class="version-nav-dot" style="background: #06b6d4;"></span> Fixes
            </a>
            <a class="version-nav-link" href="#scratched-section" onclick="scrollToSection(event, 'scratched-section')">
                <span class="version-nav-dot" style="background: #64748b;"></span> Scratched
            </a>
            <a class="version-nav-link" href="#inprogress-section" onclick="scrollToSection(event, 'inprogress-section')">
                <span class="version-nav-dot" style="background: #f59e0b;"></span> In Progress
            </a>
            <a class="version-nav-link" href="#completed-section" onclick="scrollToSection(event, 'completed-section')">
                <span class="version-nav-dot" style="background: #10b981;"></span> Completed
            </a>
            <a class="version-nav-link" href="#v70-anchor" onclick="scrollToSection(event, 'v70-anchor')">
                <span class="version-nav-dot" style="background: #8b5cf6;"></span> v7.0
            </a>
        </div>
    </nav>

    <!-- Quick Stats Summary -->
    <div style="max-width: 1000px; margin: 30px auto; padding: 0 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;">2+</div>
                <div style="color: #94a3b8; font-size: 0.85rem;">Planned</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.1)); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;">1+</div>
                <div style="color: #94a3b8; font-size: 0.85rem;">In Progress</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #8b5cf6;">4+</div>
                <div style="color: #94a3b8; font-size: 0.85rem;">Fixed</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1)); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #10b981;">22+</div>
                <div style="color: #94a3b8; font-size: 0.85rem;">Completed</div>
            </div>
            <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1)); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #ef4444;">1+</div>
                <div style="color: #94a3b8; font-size: 0.85rem;">Scratched</div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div style="max-width: 1000px; margin: 20px auto; padding: 0 20px;">
        <div style="background: rgba(0, 0, 0, 0.7); border-radius: 12px; padding: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" id="updateSearch" placeholder="🔍 Search updates..." onkeyup="filterUpdates()" style="width: 100%; padding: 12px 15px; background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="filter-btn active" data-filter="all" onclick="setFilter('all')">All</button>
                <button class="filter-btn" data-filter="planned" onclick="setFilter('planned')">📋 Planned</button>
                <button class="filter-btn" data-filter="inprogress" onclick="setFilter('inprogress')">🚧 In Progress</button>
                <button class="filter-btn" data-filter="fixed" onclick="setFilter('fixed')">🔧 Fixed</button>
                <button class="filter-btn" data-filter="completed" onclick="setFilter('completed')">✅ Completed</button>
                <button class="filter-btn" data-filter="scratched" onclick="setFilter('scratched')">🗑️ Scratched</button>
            </div>
        </div>
    </div>

    <style>
        .filter-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            color: #94a3b8;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-color: #8b5cf6;
            color: white;
        }
        .section-collapsible {
            cursor: pointer;
            user-select: none;
        }
        .section-collapsible::after {
            content: ' ▼';
            font-size: 0.7em;
            transition: transform 0.3s ease;
        }
        .section-collapsible.collapsed::after {
            content: ' ▶';
        }
        .update-grid.collapsed {
            display: none;
        }
        .update-card.hidden {
            display: none;
        }
    </style>

    <?php include_once 'includes/announcements.php'; ?>

    <!-- Active Background Info -->
    <?php if ($active_designer_background): ?>
    <div class="background-info-section">
        <h3>🎨 Active Community Background</h3>
        <p><strong>"<?php echo htmlspecialchars($active_designer_background['title']); ?>"</strong></p>
        <p>Designed by: <?php echo htmlspecialchars($active_designer_background['designer_name']); ?></p>
        <button class="btn-set-background" onclick="setAsBackground('<?php echo $active_designer_background['image_url']; ?>', '<?php echo htmlspecialchars($active_designer_background['title']); ?>')" style="margin-top: 10px;">
            Use This Background
        </button>
    </div>
    <?php endif; ?>

    <h2 class="section-title section-collapsible" onclick="toggleSection(this)">📋 Planned Features</h2>
    <div class="update-grid" id="planned-section">


    </div>
    
    
<h2 class="section-title section-collapsible" onclick="toggleSection(this)">🗑️ Scratched Ideas</h2>

<div class="update-grid" id="scratched-section">

<div class="update-card" data-status="scratched" data-search="renovations community panel redesign">
    <span class="update-status" style="background: linear-gradient(135deg, #64748b, #475569);">Scrapped</span>
    <h3>PLanning to delete beta-tester</h3>
    <p>I dont see a point in it :D</p>
    <div class="update-date">Discarded: Plan around end of march</div>
</div>


</div>

    
    
    
    <h2 class="section-title section-collapsible" onclick="toggleSection(this)">🚧 In Progress</h2>
    <div class="update-grid" id="inprogress-section">



    </div>

    <!-- Combined Updates Section -->
    <h2 class="section-title section-collapsible" onclick="toggleSection(this)">✅ Recent Updates & Completed Features</h2>
    <div class="update-grid" id="completed-section">

<!-- CTSO v7.0 EVOLUTION -->
<div class="update-card" data-status="completed" data-search="CTSO evolution omni-admin threat monitor game report smail dispatch AI context memory chat folders security hardening games storage WebGL" style="border-left: 4px solid #ef4444; background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(15,23,42,0.8));">
    <span class="update-status" style="background: linear-gradient(135deg, #ef4444, #8b5cf6); color: white;">CTSO EVOLUTION</span>
    <h3>v7.0 CTSO Evolution — Omni-Admin, Security & Performance</h3>
    <p><strong>Omni-Admin:</strong> Threat Monitor (failed logins + IPs), Game Report system (users flag broken games), Admin Smail Dispatch (message users inline from directory), Admin Audit Log (tracks all admin actions).</p>
    <p><strong>AI Evolution:</strong> Context Memory (AI remembers your role, join date, nickname), Chat Folders (organize saved conversations).</p>
    <p><strong>Games:</strong> Game folders unified under /Games/. WebGL Gzip compression + aggressive asset caching. Report Issue button on all game pages.</p>
    <p><strong>Security:</strong> SameSite=Strict on all cookies, CSP frame-src expanded for 6 domains, CAPTCHA fallback (file_get_contents), timing-safe CSRF in admin, Stripe webhook idempotency guard, DB connection SSL check, XSS fixes on 59 files.</p>
    <p><strong>UX:</strong> Branded "7.0 Evolution in Progress" maintenance page, Smail + User Directory nav cards on main dashboard, community users visible to admins only.</p>
    <div class="update-date">Completed: March 2026</div>
</div>

<!-- MAJOR FEATURE ROLLOUT -->
<div class="update-card" data-status="completed" data-search="profiles smail nicknames games directory restructuring session" style="border-left: 4px solid #f59e0b; background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(15,23,42,0.8));">
    <span class="update-status" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: white;">FEATURE DROP</span>
    <h3>Major Feature Rollout — Profiles, Smail, Directory & More</h3>
    <p>User profiles with nicknames and PFP, Smail internal messaging, user directory, game file restructuring (/Games/), 8-hour session timeout, dynamic greetings, and policy updates.</p>
    <div class="update-date">Completed: March 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="user profiles description about picture nickname" style="border-left: 4px solid #f59e0b;">
    <span class="update-status status-completed">v7.0</span>
    <h3>User Profiles & Directory</h3>
    <p>New user profile pages with description, about section, and profile picture uploads (admin-approved). User directory listing all non-community members with role badges and search. Community role excluded from both.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="smail internal mail messaging inbox outbox" style="border-left: 4px solid #f59e0b;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Smail — Internal Messaging</h3>
    <p>Spencer's internal mail system. Send messages with custom colors and urgency levels. Inbox/outbox with expandable cards. Standard users: 25 sends/day. Elevated roles: unlimited. Community excluded.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="games folder restructure directory move files" style="border-left: 4px solid #f59e0b;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Game File Restructuring</h3>
    <p>All 59 game files moved into /Games/ directory for cleaner organization. All internal links, asset paths, and require statements updated. Backward-compatible with redirect support.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="session timeout 8 hours idle graceful redirect" style="border-left: 4px solid #f59e0b;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Session & Nickname System</h3>
    <p>8-hour session timeout with graceful redirect (no more 403 errors). Global nickname system with getDisplayName() function. Dynamic contextual greetings on all major pages.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<!-- VERSION 7.0 CHANGELOG -->
<div id="v81-anchor" class="update-card" data-status="completed" data-search="VERSION 7.0 compliance policies privacy terms security admin redesign" style="border-left: 4px solid #4ECDC4; background: linear-gradient(135deg, rgba(78,205,196,0.15), rgba(15,23,42,0.8));">
    <span class="update-status" style="background: linear-gradient(135deg, #4ECDC4, #06b6d4); color: white;">VERSION 7.0</span>
    <h3>VERSION 7.0 - Compliance & Feature Overhaul</h3>
    <p>Full legal compliance (Privacy Policy, Terms of Service, Refund Policy), consent gatekeeping, UI redesigns, admin AI chat viewer, payment security hardening, and documentation overhaul.</p>
    <div class="update-date">Completed: March 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="privacy policy terms service refund consent legal compliance" style="border-left: 4px solid #4ECDC4;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Legal Compliance (Phase 1)</h3>
    <p>Created Privacy Policy, Terms of Service, and Refund Policy pages. Added consent gatekeeping: new users must agree during registration, existing users see acceptance modal on login. Cookie/tracking consent banner added. All data collection now fully disclosed.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="supporthelp role ranking redesign ui tickets" style="border-left: 4px solid #4ECDC4;">
    <span class="update-status status-completed">v7.0</span>
    <h3>UI Redesign (Phase 2)</h3>
    <p>Support page redesigned with two-column layout, role-based daily ticket limits (Community 3/day, others 10/day), expandable ticket threads, and status filters. Role Ranking page overhauled with glassmorphism pyramid, refined comparison table, and slide-in detail panels.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="admin dashboard ai chats tracking analytics clear data" style="border-left: 4px solid #4ECDC4;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Admin Enhancements (Phase 3)</h3>
    <p>New AI Chat Viewer tab for monitoring conversations. Login history and top-visited-pages tables in tracking tab. Clear Analytics Data button with double confirmation. Quick-stats dashboard row showing key metrics at a glance.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="payment stripe security pci version header token" style="border-left: 4px solid #4ECDC4;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Payment Security (Phase 4)</h3>
    <p>Pinned Stripe API version on all cURL calls. Removed internal payment token from frontend responses. Sanitized error logs for PCI compliance. Fixed terms acceptance gap in shop account creation flow.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<!-- VERSION 7.0 CHANGELOG -->
<div id="v80-anchor" class="update-card" data-status="completed" data-search="VERSION 7.0 major update overhaul payment stripe i18n support" style="border-left: 4px solid #3b82f6; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(15,23,42,0.8));">
    <span class="update-status" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">VERSION 7.0</span>
    <h3>VERSION 7.0 - Payment Overhaul & Global Expansion</h3>
    <p>Stripe Payment Intents, new shop page, donation system, i18n (5 languages), support tickets, AI customization, PHP 8.4 modernization, and panel cleanup.</p>
    <div class="update-date">Completed: March 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="payment stripe elements shop products donation" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Payment System Rework</h3>
    <p>Replaced Stripe Checkout with Payment Intents API and Stripe Elements embedded form. New shop page with 3 products ($2/mo, $20/yr, $100 lifetime) and freeform donation ($1-$100) with feedback.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="internationalization i18n languages translation" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Multi-Language Support (i18n)</h3>
    <p>Added internationalization system supporting English, Mandarin Chinese, Hindi, Spanish, and French. Language detection via URL, session, or browser preference.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="support tickets help center" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Support Ticket System</h3>
    <p>New support center where users can submit tickets with categories and priorities. Track ticket status and receive admin responses.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="ai customization persona settings prompt" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>AI Custom Personas</h3>
    <p>Users can now create custom AI personas in Settings with their own name and system prompt. Safety filters prevent prompt injection. Admins have unrestricted AI access.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="panel cleanup designer removed announcements admin only" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Panel Cleanup</h3>
    <p>Removed designer panel. Contributor/designer announcements removed — only admins can post announcements now. Contributor ideas feature preserved.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="php 8.4 modernization upgrade readonly match" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>PHP 8.4 Modernization</h3>
    <p>Upgraded codebase for PHP 8.4: removed polyfills, added readonly properties, match expressions, str_contains/str_starts_with, typed returns, and named arguments.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="refund 48 hour policy beta tester email css fix" style="border-left: 4px solid #3b82f6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Quality & Policy Updates</h3>
    <p>48-hour refund policy enforced. Beta-tester mentions removed. Email updated to spencerbuisness101@gmail.com. Settings button CSS fixed on 30+ game pages.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<!-- VERSION 7.0 CHANGELOG -->
<div id="v70-anchor" class="update-card" data-status="completed" data-search="version 7.0 major update overhaul" style="border-left: 4px solid #8b5cf6; background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(15,23,42,0.8));">
    <span class="update-status" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">VERSION 7.0</span>
    <h3>Version 7.0 - Complete Platform Overhaul</h3>
    <p>Multi-layer device tracking, admin panel overhaul, feedback system, page redesigns, AI image analysis, payment management, security hardening, and performance optimization.</p>
    <div class="update-date">Completed: February 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="device tracking fingerprinting multi-layer security" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Multi-Layer Device Tracking</h3>
    <p>Three-layer fingerprinting system: browser storage UUID, hardware DNA (GPU, canvas, fonts), and server beacon tracking for fraud detection.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="admin panel tracking unified user status fix" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Admin Panel Overhaul</h3>
    <p>Merged analytics and tracking into unified tab with device fingerprints, linked accounts, and fraud alerts. Fixed user status display (was always showing "Active").</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="feedback panel submit review respond" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Feedback Panel</h3>
    <p>New feedback system where users can submit feedback (5 limit, 400 words max). Admin can review and respond. Visible to admin, submitter, contributors, and designers.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="game center redesign hero search categories" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Game Center Redesign</h3>
    <p>Rotating featured game hero, real-time search, pill-shaped category tabs with count badges, enhanced game cards with emoji backgrounds and ribbons.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="yaps chat redesign modern bubbles sidebar" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Yaps Chat Redesign</h3>
    <p>Modern chat bubbles (right-aligned for you, left for others), collapsible online users sidebar, pill-shaped input bar, and consistent role-colored tags.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="hall of fame redesign spencer lexi garrett users" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Hall of Fame Redesign</h3>
    <p>Spencer featured as Creator & King at top, Lexi and Garrett side-by-side below, dynamic all-users section with role badges, and database-driven stats.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="ai assistant image analysis vision persona cards" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>AI Assistant Upgrade</h3>
    <p>Image analysis via Groq Vision API, 8 API key rotation slots, persona selector redesigned as icon cards, and improved glassmorphism UI.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="settings rework collapsible categories export import" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Settings Page Rework</h3>
    <p>Reorganized into collapsible category cards: Appearance, Chat, Audio, Privacy, Content, and Data. Added settings export/import functionality.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="payment management subscription user panel stripe paypal" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Payment Management</h3>
    <p>Payment section in user panel with subscription status, upgrade button, cancel subscription, and payment history. Fraud detection with velocity and IP checks.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="announcements role-based creation contributor designer" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Role-Based Announcements</h3>
    <p>Contributors and designers can now create announcements with role badges displayed. Each announcement shows the creator's role tag.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="security csp permissions-policy php8.3 opcache performance" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Security & Performance</h3>
    <p>Content Security Policy headers, Permissions-Policy, PHP 8.3 cleanup, OPcache optimization, lazy loading, IntersectionObserver animations, and query optimization.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="cleanup version bump files deleted documentation" style="border-left: 4px solid #8b5cf6;">
    <span class="update-status status-completed">v7.0</span>
    <h3>Cleanup & Version Bump</h3>
    <p>Deleted 7 summary/documentation files, bumped version strings from 6.0 to 7.0 across all files, added new Groq API key slots.</p>
    <div class="update-date">Completed: v7.0</div>
</div>

<div class="update-card" data-status="completed" data-search="game redesign brand style">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>Game Page Redesign</h3>
    <p>Updated game.php with Spencer's brand colors and distinctive nav-card style. Added hover animations, gradient accents, and category badges for better visual appeal.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="announcements contributor designer panels">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>Announcement Panels</h3>
    <p>Added separate contributor and designer announcement panels to main.php. Each panel has role-specific accent colors (amber for contributors, pink for designers) and displays only relevant announcements.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="admin organization pagination">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>Admin Panel Improvements</h3>
    <p>Enhanced admin.php with server-side pagination for user management (50 users per page). Already had excellent organization with sidebar navigation, quick actions, and system health indicators.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="settings redesign profile quick settings">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>Settings Page Redesign</h3>
    <p>Redesigned set.php with personalized welcome header, user profile summary card, Spencer's brand gradients for category cards, Quick Settings bar for most-used toggles, and improved animations with micro-interactions.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="ai panel custom persona admin bypass">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>AI Panel Enhancements</h3>
    <p>Added custom persona functionality allowing users to create personalized AI assistants with 500-character prompts. Admin bypass removes rate limits and increases token/character limits (4000 tokens, 10000 chars). Content filtering enforced for safety.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>

<div class="update-card" data-status="completed" data-search="performance security database indexes">
    <span class="update-status status-completed">COMPLETED</span>
    <h3>Performance & Security Improvements</h3>
    <p>Added database indexes for frequently queried columns (announcements, user_sessions, ai_chat_history). Enhanced OPcache configuration with 256MB memory and optimization level 0x7FFB. Improved query performance across the platform.</p>
    <div class="update-date">Completed: Feb 21, 2026</div>
</div>



    </div>

    <div class="container" style="margin-top: 40px;">
        <a href="main.php" class="back-button">🏠 Back to Main Site</a>
    </div>

    <script>
        // Enhanced logout function - Match other pages
        function logout() {
            console.log('🚪 User logging out');
            
            if (confirm('Are you sure you want to logout?')) {
                const logoutBtn = document.querySelector('.logout-btn');
                const originalText = logoutBtn.innerHTML;
                logoutBtn.innerHTML = '🔄 Logging out...';
                logoutBtn.disabled = true;
                
                fetch('auth/logout.php')
                    .then(response => {
                        if (response.ok) {
                            logoutBtn.innerHTML = '✅ Success!';
                            setTimeout(() => {
                                window.location.href = 'index.php';
                            }, 1000);
                        } else {
                            throw new Error('Logout failed');
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        logoutBtn.innerHTML = originalText;
                        logoutBtn.disabled = false;
                        alert('Logout failed. Please try again.');
                    });
            }
        }

        // Background selection functionality - Match other pages
        function openBackgroundModal() {
            const modal = document.getElementById('backgroundModal');
            const grid = document.getElementById('backgroundsGrid');
            
            // Clear existing content
            grid.innerHTML = '';
            
            // Get available backgrounds from PHP
            const backgrounds = <?php echo json_encode($available_backgrounds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const currentBackground = getCurrentBackground();
            
            // Add background items
            backgrounds.forEach(background => {
                const isActive = currentBackground === background.image_url;
                
                const backgroundItem = document.createElement('div');
                backgroundItem.className = `background-item ${isActive ? 'active' : ''}`;
                backgroundItem.innerHTML = `
                    <div class="background-preview" style="background-image: url('${background.image_url}')"></div>
                    <div class="background-info">
                        <div class="background-title">${escapeHtml(background.title)}</div>
                        <div class="background-designer">By: ${escapeHtml(background.designer_name)}</div>
                        <div class="background-actions">
                            <button class="btn-set-background" onclick="setAsBackground('${background.image_url}', '${escapeHtml(background.title)}')">
                                ${isActive ? '✅ Using' : 'Use This'}
                            </button>
                        </div>
                    </div>
                `;
                
                grid.appendChild(backgroundItem);
            });
            
            modal.style.display = 'block';
        }

        function closeBackgroundModal() {
            document.getElementById('backgroundModal').style.display = 'none';
        }

        function setAsBackground(imageUrl, title) {
            // Get current settings or initialize empty object
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            settings.customBackground = imageUrl;
            settings.customBackgroundTitle = title;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));
            
            // Apply the new background
            applyActiveBackground();
            
            // Close modal and show confirmation
            closeBackgroundModal();
            showNotification(`🎨 Background set to "${title}"!`);
            
            // Refresh the modal to update active states
            setTimeout(openBackgroundModal, 100);
        }

        function removeCustomBackground() {
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            delete settings.customBackground;
            delete settings.customBackgroundTitle;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));
            
            // Revert to the active community background
            applyActiveBackground();
            
            // Close modal and show confirmation
            closeBackgroundModal();
            showNotification('🗑️ Custom background removed!');
            
            // Refresh the modal to update active states
            setTimeout(openBackgroundModal, 100);
        }

        function getCurrentBackground() {
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                return settings.customBackground || null;
            }
            return null;
        }

        function showNotification(message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                z-index: 10000;
                font-weight: 600;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Animate out and remove
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Apply active designer background - Match other pages
        function applyActiveBackground() {
            const bgOverride = document.getElementById('bgThemeOverride');
            if (!bgOverride) return;

            // Check for user's custom background first
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                if (settings.customBackground && settings.customBackground.trim() !== '') {
                    bgOverride.style.backgroundImage = `url('${settings.customBackground}')`;
                    bgOverride.classList.remove('designer-bg');
                    return;
                }
            }

            // Otherwise use the active designer background
            <?php if ($active_designer_background): ?>
                bgOverride.style.backgroundImage = `url('<?php echo $active_designer_background['image_url']; ?>')`;
                bgOverride.classList.add('designer-bg');
            <?php endif; ?>
        }

        // Apply settings when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Load settings from localStorage
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                
                // Apply accent color
                if (settings.accentColor) {
                    const accentColor = `#${settings.accentColor}`;
                    
                    let styleElement = document.getElementById('accent-color-styles');
                    if (!styleElement) {
                        styleElement = document.createElement('style');
                        styleElement.id = 'accent-color-styles';
                        document.head.appendChild(styleElement);
                    }
                    
                    styleElement.textContent = `
                        .game-button, .move-button, .info-button {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .game-button:hover, .move-button:hover, .info-button:hover {
                            background: linear-gradient(45deg, ${accentColor}, #FF6B6B) !important;
                        }
                        .centered-box {
                            border-color: ${accentColor} !important;
                        }
                        .centered-box:hover {
                            border-color: #FF6B6B !important;
                        }
                        .back-button {
                            background: linear-gradient(45deg, #667eea, #764ba2) !important;
                        }
                        .update-card {
                            border-color: ${accentColor} !important;
                        }
                        .update-card:hover {
                            border-color: #FF6B6B !important;
                        }
                        .page-header h1 {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                            -webkit-background-clip: text !important;
                            -webkit-text-fill-color: transparent !important;
                            background-clip: text !important;
                        }
                        .page-header::before {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .section-title {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                            -webkit-background-clip: text !important;
                            -webkit-text-fill-color: transparent !important;
                            background-clip: text !important;
                        }
                    `;
                }
                
                // Apply font size
                if (settings.fontSize) {
                    document.documentElement.style.fontSize = `${settings.fontSize}px`;
                }
                
                // Apply game volume
                if (settings.gameVolume) {
                    console.log('Game volume set to:', settings.gameVolume);
                }
            }
            
            // Apply the background
            applyActiveBackground();
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('backgroundModal');
            if (event.target === modal) {
                closeBackgroundModal();
            }
        });

        // Keyboard shortcuts - Match other pages
        document.addEventListener('keydown', function(e) {
            // Ctrl + L for logout
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                logout();
            }
            // Ctrl + S for settings
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'set.php';
            }
            // Ctrl + B for backgrounds
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                openBackgroundModal();
            }
        });

        // Smooth scroll to section via version timeline nav
        function scrollToSection(event, sectionId) {
            event.preventDefault();
            const target = document.getElementById(sectionId);
            if (!target) return;

            // For section grids, scroll to the heading above them
            const heading = target.previousElementSibling;
            const scrollTarget = (heading && heading.classList.contains('section-title')) ? heading : target;

            const navHeight = document.querySelector('.version-timeline-nav') ? document.querySelector('.version-timeline-nav').offsetHeight + 10 : 0;
            const targetTop = scrollTarget.getBoundingClientRect().top + window.pageYOffset - navHeight;

            window.scrollTo({ top: targetTop, behavior: 'smooth' });

            // Update active state on nav links
            document.querySelectorAll('.version-nav-link').forEach(link => link.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }

        // Highlight active nav link on scroll
        window.addEventListener('scroll', function() {
            const navHeight = document.querySelector('.version-timeline-nav') ? document.querySelector('.version-timeline-nav').offsetHeight + 20 : 0;
            const sections = ['planned-section', 'fixes-section', 'scratched-section', 'inprogress-section', 'completed-section', 'v70-anchor'];
            let currentSection = '';

            sections.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    const rect = el.getBoundingClientRect();
                    if (rect.top <= navHeight + 100) {
                        currentSection = id;
                    }
                }
            });

            document.querySelectorAll('.version-nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + currentSection) {
                    link.classList.add('active');
                }
            });
        });

        // Filter and search functionality
        let currentFilter = 'all';

        function setFilter(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                }
            });
            filterUpdates();
        }

        function filterUpdates() {
            const searchTerm = document.getElementById('updateSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.update-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const status = card.dataset.status || '';
                const searchData = (card.dataset.search || '').toLowerCase();
                const cardText = card.textContent.toLowerCase();
                const searchText = searchData + ' ' + cardText;

                let showByFilter = currentFilter === 'all' || status === currentFilter;
                let showBySearch = true;

                if (searchTerm) {
                    // Support multi-word search: all terms must match
                    const terms = searchTerm.split(/\s+/).filter(t => t.length > 0);
                    showBySearch = terms.every(term => searchText.includes(term));
                }

                if (showByFilter && showBySearch) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Show/hide section headings based on whether they have visible cards
            document.querySelectorAll('.update-grid').forEach(grid => {
                const visibleCards = grid.querySelectorAll('.update-card:not(.hidden)');
                const heading = grid.previousElementSibling;
                if (heading && heading.classList.contains('section-title')) {
                    heading.style.display = visibleCards.length === 0 ? 'none' : '';
                }
                grid.style.display = visibleCards.length === 0 ? 'none' : '';
            });
        }

        function toggleSection(element) {
            element.classList.toggle('collapsed');
            const grid = element.nextElementSibling;
            if (grid && grid.classList.contains('update-grid')) {
                grid.classList.toggle('collapsed');
            }
        }
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    
</body>
</html>
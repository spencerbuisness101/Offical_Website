<?php
require_once __DIR__ . '/includes/init.php';

// Note: No login check for error page since users might encounter errors before logging in

// Try to get user info if available, but don't require login
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';
$role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'visitor';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Set 403 HTTP status code
http_response_code(403);

// 403-specific message
$error_message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'You do not have permission to access this resource.';
$error_code = '403';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="common.js"></script>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="control_buttons.css">

    <link rel="icon" href="/assets/images/favicon.webp">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="403 Forbidden - Spencer's Website">
    <title>Access Denied! Error 403 - Spencer's Website</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }

        /* Background System */
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

        .bg-theme-override::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
            z-index: -1;
        }

        /* Control Buttons */
        .control-buttons-container {
            position: fixed;
            top: 25px;
            right: 25px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1001;
        }

        .logout-btn, .setting-btn, .backgrounds-btn, .home-btn {
            background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
            color: #ffffff;
            padding: 12px 20px;
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

        .home-btn {
            background: linear-gradient(135deg, #4ECDC4, #44A08D);
        }

        .logout-btn:hover, .setting-btn:hover, .backgrounds-btn:hover, .home-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.5);
            color: #ffffff;
        }

        /* Error Content */
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            text-align: center;
        }

        .error-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 50px 40px;
            max-width: 700px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .error-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FF6B6B, #FFD166, #FF6B6B);
            animation: gradientShift 3s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .error-icon {
            font-size: 6rem;
            margin-bottom: 20px;
            animation: shake 2s infinite;
        }

        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }

        .error-title {
            font-size: 3.5rem;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #FF6B6B, #FFD166);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .error-code {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
            font-weight: 600;
        }

        .error-message {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 35px;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn-primary, .btn-secondary {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(45deg, #FF6B6B, #FFD166);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
            background: linear-gradient(45deg, #FFD166, #FF6B6B);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid #FFD166;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #FF6B6B;
            transform: translateY(-2px);
            color: #ffffff;
        }

        .error-details {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            text-align: left;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .error-details summary {
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .error-details ul {
            padding-left: 20px;
            margin-top: 10px;
        }

        .error-details li {
            margin-bottom: 8px;
        }

        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 40px;
            max-width: 600px;
            width: 100%;
        }

        .quick-link-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quick-link-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.1);
            border-color: #FFD166;
        }

        .quick-link-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .quick-link-text {
            font-weight: 600;
            color: white;
        }

        /* User Info */
        .user-info {
            position: fixed;
            top: 25px;
            left: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1001;
        }

        /* Permission Status */
        .permission-status {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 25px 0;
        }

        .permission-badge {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .permission-badge.restricted {
            border-left: 4px solid #FF6B6B;
        }

        .permission-badge.allowed {
            border-left: 4px solid #4ECDC4;
        }

        /* Responsive Design */
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
            
            .logout-btn, .setting-btn, .backgrounds-btn, .home-btn {
                font-size: 12px;
                padding: 10px 15px;
            }
            
            .error-content {
                padding: 30px 20px;
            }
            
            .error-title {
                font-size: 2.5rem;
            }
            
            .error-message {
                font-size: 1.1rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                max-width: 250px;
            }
            
            .quick-links {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                position: relative;
                top: 0;
                left: 0;
                margin-bottom: 20px;
            }
            
            .permission-status {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <!-- Background Theme Override Element -->
    <div class="bg-theme-override" id="bgThemeOverride"></div>


    <!-- User Info -->
    <div class="user-info">
        👤 <?php echo $username; ?> (<?php echo ucfirst($role); ?>)
    </div>

    <!-- Error Content -->
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                🚫
            </div>
            
            <h1 class="error-title">Website Closed!</h1>
            
            <div class="error-code">
                Error 403 - Forbidden
            </div>
            
            <p class="error-message">
                <?php echo $error_message; ?>
            </p>
            
            <!-- Permission Status -->
            <div class="permission-status">
                <div class="permission-badge restricted">
                    <span>🔒</span>
                    <span>Current Role: <?php echo ucfirst($role); ?></span>
                </div>
                <div class="permission-badge allowed">
                    <span>✅</span>
                    <span>Required: Administrator</span>
                </div>
            </div>
            
            <div class="error-actions">
                <a href="main.php" class="btn-primary">🏠 Return to Home</a>
                <button class="btn-secondary" onclick="history.back()">↩️ Go Back</button>
                <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
                    <a href="index.php" class="btn-primary">🔐 Login with Higher Privileges</a>
                <?php endif; ?>
            </div>
            
            <div class="error-details">
                <summary>📋 Why am I seeing this page?</summary>
                <ul>
                    <li>You may not have the required permissions to access this resource</li>
                    <li>Your account role (<?php echo ucfirst($role); ?>) doesn't have sufficient privileges</li>
                    <li>If you see this, either the website is in work, or it is not functioning right</li>
                    <li>Contact an administrator if you believe this is an error</li>
                </ul>
            </div>
            
            <div class="quick-links">
                <a href="game.php" class="quick-link-card">
                    <div class="quick-link-icon">🎮</div>
                    <div class="quick-link-text">Game Center</div>
                </a>
                
                <a href="info.php" class="quick-link-card">
                    <div class="quick-link-icon">ℹ️</div>
                    <div class="quick-link-text">Information Hub</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Enhanced logout function
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
                        .btn-primary, .game-button, .move-button, .info-button {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .btn-primary:hover, .game-button:hover, .move-button:hover, .info-button:hover {
                            background: linear-gradient(45deg, ${accentColor}, #FF6B6B) !important;
                        }
                        .btn-secondary {
                            border-color: ${accentColor} !important;
                        }
                        .btn-secondary:hover {
                            border-color: #FF6B6B !important;
                        }
                        .quick-link-card:hover {
                            border-color: ${accentColor} !important;
                        }
                        .error-content::before {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .error-title {
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
            }

            // Apply background if available
            applyActiveBackground();
        });

        // Apply active background
        function applyActiveBackground() {
            const bgOverride = document.getElementById('bgThemeOverride');
            if (!bgOverride) return;

            // Check for user's custom background first
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                if (settings.customBackground && settings.customBackground.trim() !== '') {
                    bgOverride.style.backgroundImage = `url('${settings.customBackground}')`;
                    return;
                }
            }

            // Otherwise use a default error-themed background or none
            bgOverride.style.backgroundImage = `none`;
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter to go home
            if (e.key === 'Enter') {
                window.location.href = 'main.php';
            }
            // Escape to go back
            if (e.key === 'Escape') {
                history.back();
            }
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
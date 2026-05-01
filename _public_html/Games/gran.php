<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}
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
?>

<!doctype html>
<html lang="en">
<head>
    <script src="../js/tracking.js?v=7.0" defer></script>
    <link rel="stylesheet" href="../control_buttons.css">
    
    <script src="../common.js"></script>
    <script src="../js/game-settings.js"></script>
    <link rel="icon" href="/assets/images/favicon.webp">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play Granny - Spencer's Game Collection">
    <title>Granny - Spencer's Website</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/game-common.css">
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>' 
      data-active-background='<?php echo $active_designer_background ? htmlspecialchars($active_designer_background['image_url'], ENT_QUOTES, 'UTF-8') : ''; ?>'>
    <?php require_once __DIR__ . "/../includes/identity_bar.php"; ?>
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
        <a href="game.php" class="centered-box">← Back to Games</a>
        <a href="../main.php" class="centered-box" style="margin-left: 10px;">🏠 Main Site</a>
    </div>
    
    <header class="page-header">
        <h1>🎮 Granny <span class="game-badge">Horror Escape</span></h1>
        <p>Escape from Granny's house in this terrifying horror survival game!</p>
    </header>

    <!-- Granny Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <div id="unity-container">
                <canvas id="unity-canvas"></canvas>
                <div id="unity-loading-bar">
                    <div id="unity-progress-bar-empty">
                        <div id="unity-progress-bar-full"></div>
                    </div>
                </div>
                <div id="unity-warning"></div>
            </div>
        </div>
        
        <div class="game-details">
            <h2>🎯 About Granny</h2>
            <p>Granny is a terrifying horror game where you've been trapped in Granny's house. You have five days to escape, but Granny hears everything. Be careful - one mistake and you'll be her next victim!</p>
            
            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🏚️ Horror Atmosphere</strong>
                    <p>Experience intense horror with creepy sounds and jump scares</p>
                </div>
                <div class="feature-item">
                    <strong>🧠 Puzzle Solving</strong>
                    <p>Solve challenging puzzles to find keys and escape items</p>
                </div>
                <div class="feature-item">
                    <strong>👵 Stealth Gameplay</strong>
                    <p>Avoid Granny who hears everything and hunts you down</p>
                </div>
                <div class="feature-item">
                    <strong>⏱️ Time Pressure</strong>
                    <p>You have only 5 in-game days to escape the house</p>
                </div>
            </div>

            <h3>🎮 How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,107,107,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #4ECDC4; margin: 20px 0;">
                <p><strong>Movement Controls:</strong></p>
                <ul>
                    <li><strong>W, A, S, D</strong> - Move around the house</li>
                    <li><strong>Mouse</strong> - Look around and interact with objects</li>
                    <li><strong>E</strong> - Pick up items and interact with objects</li>
                    <li><strong>Shift</strong> - Run (but be careful - Granny hears running!)</li>
                    <li><strong>Ctrl</strong> - Crouch to move quietly</li>
                </ul>
                
                <p><strong>Gameplay Tips:</strong></p>
                <ul>
                    <li>Listen carefully for Granny's footsteps</li>
                    <li>Hide under beds or in lockers when Granny is nearby</li>
                    <li>Collect items to solve puzzles and unlock doors</li>
                    <li>Be aware that some items make noise when dropped</li>
                </ul>
            </div>

            <h3>💡 Gameplay Tips</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Always <strong>close doors behind you</strong> to slow Granny down</li>
                <li>Use <strong>bedrooms and hiding spots</strong> strategically</li>
                <li>Pay attention to <strong>sound cues</strong> - they tell you where Granny is</li>
                <li>Some items can be used as <strong>distractions</strong> to lure Granny away</li>
                <li>Plan your <strong>escape route</strong> before making noise</li>
            </ul>

            <h3>🏆 Objectives</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Find the hidden keys to unlock doors</li>
                <li>Locate the escape vehicle and its necessary parts</li>
                <li>Avoid Granny for 5 in-game days</li>
                <li>Solve puzzles to progress through the house</li>
                <li>Escape the house before time runs out</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #FF6B6B; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FF6B6B; margin-bottom: 10px; font-weight: 700;">🎯 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">The key to surviving Granny is patience and observation. Take your time to learn the house layout and Granny's patterns. Rushing will only get you caught faster!</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="game.php" class="back-button">← Back to All Games</a>
        <a href="../main.php" class="back-button">🏠 Back to Main Site</a>
    </div>

    <!-- Granny Game Scripts -->
    <script src="https://cdn.jsdelivr.net/gh/bubbls/granny@212c543a85b243c4a0b92211f557e760d83d2292/sdk.js"></script>
    <!-- GameMonetize SDK Integration -->
    <script type="text/javascript">
        window.SDK_OPTIONS = {
            gameId: "jp112o3o4hzgrnc7zaewjkrfk282pul8",
            onEvent: function (a) {
                switch (a.name) {
                    case "SDK_GAME_PAUSE":
                        console.log("Game paused, muting audio...");
                        if (typeof myGameInstance !== 'undefined' && myGameInstance) {
                            myGameInstance.SendMessage('AudioManager', 'MuteAudio');
                        }
                        break;
                    case "SDK_GAME_START":
                        console.log("Ad finished, game continuing...");
                        if (typeof myGameInstance !== 'undefined' && myGameInstance) {
                            myGameInstance.SendMessage('AudioManager', 'UnmuteAudio');
                        }
                        break;
                    case "SDK_READY":
                        console.log("SDK ready.");
                        break;
                }
            }
        };
        (function (a, b, c) {
            var d = a.getElementsByTagName(b)[0];
            a.getElementById(c) || (a = a.createElement(b), a.id = c, a.src = "https://cdn.jsdelivr.net/gh/testamalame/sef@main/sedk.js", d.parentNode.insertBefore(a, d));
        })(document, "script", "gamemonetize-sdk");
    </script>

    <script>
        // Game loading functionality from original Granny file
        async function mergeUnityWebFiles(baseUrl, filePrefix, totalParts, extension) {
            const partUrls = [];
            for (let i = 1; i <= totalParts; i++) {
                partUrls.push(`${baseUrl}/${filePrefix}_part${i}.${extension}`);
            }

            const buffers = [];
            for (let i = 0; i < totalParts; i++) {
                const response = await fetch(partUrls[i]);
                if (!response.ok) {
                    throw new Error(`Failed to load part: ${partUrls[i]}`);
                }
                const buffer = await response.arrayBuffer();
                buffers.push(buffer);

                const progress = ((i + 1) / totalParts) * 100;
                document.querySelector("#unity-progress-bar-full").style.width = `${progress}%`;
            }

            const totalLength = buffers.reduce((acc, buffer) => acc + buffer.byteLength, 0);
            const combinedBuffer = new Uint8Array(totalLength);
            let offset = 0;

            buffers.forEach((buffer) => {
                combinedBuffer.set(new Uint8Array(buffer), offset);
                offset += buffer.byteLength;
            });

            return combinedBuffer;
        }

        var container = document.querySelector("#unity-container");
        var canvas = document.querySelector("#unity-canvas");
        var loadingBar = document.querySelector("#unity-loading-bar");
        var progressBarFull = document.querySelector("#unity-progress-bar-full");
        var warningBanner = document.querySelector("#unity-warning");

        let myGameInstance = null;
        let isAdShown = false;

        function unityShowBanner(msg, type) {
            function updateBannerVisibility() {
                warningBanner.style.display = warningBanner.children.length ? 'block' : 'none';
            }
            var div = document.createElement('div');
            div.innerHTML = msg;
            warningBanner.appendChild(div);
            if (type == 'error') div.style = 'background: red; padding: 10px;';
            else {
                if (type == 'warning') div.style = 'background: yellow; padding: 10px;';
                setTimeout(function() {
                    warningBanner.removeChild(div);
                    updateBannerVisibility();
                }, 5000);
            }
            updateBannerVisibility();
        }

        function showAdOnClick() {
            if (!isAdShown && typeof sdk !== 'undefined' && typeof sdk.showBanner !== 'undefined') {
                sdk.showBanner();
                isAdShown = true;
            }
        }

        var buildUrl = "https://cdn.jsdelivr.net/gh/gru6nny/ohd@main/Build";
        var loaderUrl = buildUrl + "/Granny.loader.js";

        async function initializeGame() {
            try {
                const dataBuffer = await mergeUnityWebFiles(buildUrl, "Granny", 2, "data");
                const wasmBuffer = await mergeUnityWebFiles(buildUrl, "Granny", 2, "wasm");

                const dataBlobUrl = URL.createObjectURL(new Blob([dataBuffer], { type: "application/octet-stream" }));
                const wasmBlobUrl = URL.createObjectURL(new Blob([wasmBuffer], { type: "application/octet-stream" }));

                var config = {
                    dataUrl: dataBlobUrl,
                    frameworkUrl: buildUrl + "/Granny.framework.js",
                    codeUrl: wasmBlobUrl,
                    streamingAssetsUrl: "https://cdn.jsdelivr.net/gh/gru6nny/ohd@main/StreamingAssets",
                    companyName: "Anastasia Kazantseva",
                    productName: "Granny",
                    productVersion: "1.0",
                    showBanner: unityShowBanner,
                };

                var script = document.createElement("script");
                script.src = loaderUrl;
                script.onload = () => {
                    createUnityInstance(canvas, config, (progress) => {
                        progressBarFull.style.width = 100 * progress + "%";
                    }).then((unityInstance) => {
                        myGameInstance = unityInstance;
                        loadingBar.style.display = "none";
                        // Add click event when game loads
                        canvas.addEventListener('pointerdown', showAdOnClick);
                        canvas.addEventListener('touchstart', showAdOnClick);
                    }).catch((message) => {
                        alert(message);
                    });
                };

                document.body.appendChild(script);
            } catch (error) {
                console.error("Game initialization failed:", error);
            }
        }

        // Initialize the game
        initializeGame();

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

        // Background selection functionality
        function openBackgroundModal() {
            const modal = document.getElementById('backgroundModal');
            const grid = document.getElementById('backgroundsGrid');
            
            // Clear existing content
            grid.innerHTML = '';
            
            // Get available backgrounds from PHP
            const backgrounds = <?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>;
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

        // Apply active designer background
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
                        .game-details {
                            border-color: ${accentColor} !important;
                        }
                        .game-details h2 {
                            color: ${accentColor} !important;
                            border-bottom-color: ${accentColor} !important;
                        }
                        .page-header h1 {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                            -webkit-background-clip: text !important;
                            -webkit-text-fill-color: transparent !important;
                            background-clip: text !important;
                        }
                        .game-badge {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
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

        // Keyboard shortcuts
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

        // Fullscreen functionality
        function toggleFullscreen() {
            const gameContainer = document.getElementById('gameContainer');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            
            if (!document.fullscreenElement) {
                // Enter fullscreen
                if (gameContainer.requestFullscreen) {
                    gameContainer.requestFullscreen();
                } else if (gameContainer.webkitRequestFullscreen) {
                    gameContainer.webkitRequestFullscreen();
                } else if (gameContainer.mozRequestFullScreen) {
                    gameContainer.mozRequestFullScreen();
                } else if (gameContainer.msRequestFullscreen) {
                    gameContainer.msRequestfullscreen();
                }
                gameContainer.classList.add('fullscreen');
                fullscreenBtn.textContent = '❐ Exit Fullscreen';
                
                // Prevent the canvas from taking over keyboard controls
                const canvas = document.getElementById('unity-canvas');
                canvas.blur();
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                gameContainer.classList.remove('fullscreen');
                fullscreenBtn.textContent = '⛶ Fullscreen';
            }
        }

        // Listen for fullscreen change events to update button text
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('mozfullscreenchange', handleFullscreenChange);
        document.addEventListener('MSFullscreenChange', handleFullscreenChange);

        function handleFullscreenChange() {
            const gameContainer = document.getElementById('gameContainer');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            
            if (!document.fullscreenElement && !document.webkitFullscreenElement && 
                !document.mozFullScreenElement && !document.msFullscreenElement) {
                gameContainer.classList.remove('fullscreen');
                fullscreenBtn.textContent = '⛶ Fullscreen';
            }
        }

        // Add keyboard shortcut (F key for fullscreen)
        document.addEventListener('keydown', function(event) {
            // Only trigger if we're not in the canvas
            if (event.target.tagName !== 'CANVAS') {
                if (event.key === 'f' || event.key === 'F') {
                    event.preventDefault();
                    toggleFullscreen();
                }
                
                // Escape key to exit fullscreen
                if (event.key === 'Escape') {
                    const gameContainer = document.getElementById('gameContainer');
                    if (gameContainer.classList.contains('fullscreen')) {
                        toggleFullscreen();
                    }
                }
            }
        });

        // Focus management to prevent canvas from capturing keys
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('fullscreen-btn')) {
                // Give focus to the button instead of canvas
                event.target.focus();
            }
        });
    </script>
    


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>gran</strong></p>
            <select id="reportType" style="width:100%;padding:8px;border-radius:8px;background:#16213e;color:#e2e8f0;border:1px solid #2d3a5c;margin-bottom:10px;">
                <option value="broken">Broken / Won't Load</option>
                <option value="slow">Slow Performance</option>
                <option value="audio">Audio Issues</option>
                <option value="visual">Visual Glitches</option>
                <option value="crash">Crashes</option>
                <option value="other">Other</option>
            </select>
            <textarea id="reportDesc" maxlength="1000" placeholder="Describe the issue..." style="width:100%;height:80px;padding:8px;border-radius:8px;background:#16213e;color:#e2e8f0;border:1px solid #2d3a5c;resize:vertical;margin-bottom:12px;"></textarea>
            <div style="display:flex;gap:8px;">
                <button onclick="submitGameReport('gran')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
                <button onclick="document.getElementById('reportGameModal').style.display='none'" style="flex:1;padding:10px;border:none;border-radius:8px;background:#334155;color:#e2e8f0;cursor:pointer;">Cancel</button>
            </div>
            <div id="reportMsg" style="margin-top:8px;font-size:0.85rem;display:none;"></div>
        </div>
    </div>
    <script>
    function submitGameReport(gn){var b=document.querySelector('#reportGameModal button');b.disabled=true;b.textContent='Sending...';var m=document.getElementById('reportMsg');m.style.display='none';fetch('../api/report_game.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'game_name='+encodeURIComponent(gn)+'&issue_type='+document.getElementById('reportType').value+'&description='+encodeURIComponent(document.getElementById('reportDesc').value)+'&csrf_token='+encodeURIComponent(document.querySelector('[name=csrf_token]')?.value||'')}).then(r=>r.json()).then(d=>{m.style.display='block';m.style.color=d.success?'#22c55e':'#ef4444';m.textContent=d.success?d.message:(d.error||'Failed');b.disabled=false;b.textContent='Submit Report';if(d.success)setTimeout(()=>document.getElementById('reportGameModal').style.display='none',2000)}).catch(()=>{m.style.display='block';m.style.color='#ef4444';m.textContent='Connection error';b.disabled=false;b.textContent='Submit Report'});}
    </script>
</body>
</html>
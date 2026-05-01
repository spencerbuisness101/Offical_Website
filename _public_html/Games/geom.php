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
<?php $page_title = isset($page_title) ? $page_title : '';?>
<?php require_once __DIR__ . '/../includes/games_head.php'; ?>


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
    <meta name="description" content="Play Geometry Dash - Spencer's Game Collection">
    <title>Geometry Dash - Spencer's Website</title>
    <link rel="stylesheet" href="../styles.css">
    
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
        <h1>🎮 Geometry Dash <span class="game-badge">Rhythm Platformer</span></h1>
        <p>Jump and fly your way through danger in this rhythm-based action platformer!</p>
    </header>

    <!-- Geometry Dash Game Player -->
    <div class="game-player">
        <div class="game-container" id="geometryDashContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <div id="gameContainer"></div>
            <div id="loader"><img class="logo" src="/assets/images/loading.png?v=1">
                <div class="spinner"></div>
                <div class="pos_progress">
                    <div class="progress">
                        <div class="posfull"><img class="full" src="/assets/images/process_bar_back.png"></div>
                        <div class="posbox"><img class="box_process" src="/assets/images/process_bar_front.png"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="game-details">
            <h2>🎯 About Geometry Dash</h2>
            <p>Geometry Dash is a rhythm-based platformer game where you control a geometric shape and navigate through challenging levels filled with obstacles. Jump, fly, and flip your way through dangerous passages and spiky obstacles in sync with the music!</p>
            
            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🎵 Rhythm-Based Gameplay</strong>
                    <p>All movements and obstacles sync with the energetic soundtrack</p>
                </div>
                <div class="feature-item">
                    <strong>🌈 Vibrant Visuals</strong>
                    <p>Colorful geometric shapes and pulsating backgrounds</p>
                </div>
                <div class="feature-item">
                    <strong>🎮 Simple Controls</strong>
                    <p>One-touch controls that are easy to learn but hard to master</p>
                </div>
                <div class="feature-item">
                    <strong>🏆 Challenging Levels</strong>
                    <p>Multiple levels with increasing difficulty and unique mechanics</p>
                </div>
            </div>

            <h3>🎮 How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,107,107,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #4ECDC4; margin: 20px 0;">
                <p><strong>Controls:</strong></p>
                <ul>
                    <li><strong>Spacebar / Mouse Click / Touch Screen</strong> - Jump/Fly</li>
                    <li>Time your jumps to avoid obstacles and spikes</li>
                    <li>Hold to fly higher, release to descend</li>
                    <li>Navigate through <strong>gaps, platforms, and portals</strong></li>
                </ul>
                
                <p><strong>Game Modes:</strong></p>
                <ul>
                    <li><strong>Cube</strong> - Standard jumping mode</li>
                    <li><strong>Ship</strong> - Flying mode with gravity control</li>
                    <li><strong>Ball</strong> - Gravity-flipping mode</li>
                    <li><strong>UFO</strong> - Mini-jump flying mode</li>
                    <li><strong>Wave</strong> - Zigzag flying mode</li>
                    <li><strong>Robot</strong> - Variable jump height mode</li>
                    <li><strong>Spider</strong> - Teleportation mode</li>
                </ul>
            </div>

            <h3>💡 Gameplay Tips</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Listen carefully to the <strong>music rhythm</strong> - it's your timing guide</li>
                <li>Practice each level multiple times to <strong>memorize patterns</strong></li>
                <li>Stay calm and don't spam the jump button</li>
                <li>Watch for <strong>color changes</strong> that indicate gameplay mode switches</li>
                <li>Use practice mode to <strong>master difficult sections</strong></li>
            </ul>

            <h3>🏆 Objectives</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Complete levels without crashing into obstacles</li>
                <li>Collect coins to unlock new icons and colors</li>
                <li>Achieve 3 stars on each level for maximum completion</li>
                <li>Master all game modes and their unique mechanics</li>
                <li>Challenge yourself with user-created levels</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #FF6B6B; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FF6B6B; margin-bottom: 10px; font-weight: 700;">🎯 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">The key to success in Geometry Dash is developing muscle memory through repetition. Don't get frustrated by failures - each attempt brings you closer to mastering the level's rhythm and patterns!</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="game.php" class="back-button">← Back to All Games</a>
        <a href="../main.php" class="back-button">🏠 Back to Main Site</a>
    </div>

    <script src="https://rawcdn.githack.com/genizy/google-class/main/gdlite/Build/UnityLoader.js"></script>
    <script>
        var gameInstance = UnityLoader.instantiate("gameContainer", "https://rawcdn.githack.com/genizy/google-class/main/gdlite/Build/GeometryDashLite.json", {
            onProgress: UnityProgress
        });

        function UnityProgress(gameInstance, progress) {
            if (!gameInstance.Module) {
                return;
            }
            const loader = document.querySelector("#loader");
            if (!gameInstance.progress) {
                const progress = document.querySelector("#loader .progress");
                progress.style.display = "block";
                gameInstance.progress = progress.querySelector(".full");
                loader.querySelector(".spinner").style.display = "none";
            }
            gameInstance.progress.style.transform = `scaleX(${progress})`;
            if (progress === 1 && !gameInstance.removeTimeout) {
                gameInstance.removeTimeout = setTimeout(function() {
                    loader.style.display = "none";
                }, 2000);
            }
        }

        // Auto-click OK buttons that might appear
        document.addEventListener('DOMContentLoaded', function() {
            let buttondiv = document.getElementsByTagName('button');
            for (let i = 0; i < buttondiv.length; i++) {
                if (buttondiv[i].innerHTML == "OK") {
                    buttondiv[i].click();
                }
            }
            setTimeout(function() {
                let buttonok = document.getElementsByTagName('button');
                for (let i = 0; i < buttondiv.length; i++) {
                    if (buttonok[i].innerHTML == "OK") {
                        buttonok[i].click();
                    }
                }
            }, 1000);
        });

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
            const gameContainer = document.getElementById('geometryDashContainer');
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
                
                // Prevent the game from taking over keyboard controls
                const gameIframe = document.getElementById('gameContainer');
                gameIframe.blur();
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
            const gameContainer = document.getElementById('geometryDashContainer');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            
            if (!document.fullscreenElement && !document.webkitFullscreenElement && 
                !document.mozFullScreenElement && !document.msFullscreenElement) {
                gameContainer.classList.remove('fullscreen');
                fullscreenBtn.textContent = '⛶ Fullscreen';
            }
        }

        // Add keyboard shortcut (F key for fullscreen)
        document.addEventListener('keydown', function(event) {
            // Only trigger if we're not in the game
            if (event.target.tagName !== 'CANVAS') {
                if (event.key === 'f' || event.key === 'F') {
                    event.preventDefault();
                    toggleFullscreen();
                }
                
                // Escape key to exit fullscreen
                if (event.key === 'Escape') {
                    const gameContainer = document.getElementById('geometryDashContainer');
                    if (gameContainer.classList.contains('fullscreen')) {
                        toggleFullscreen();
                    }
                }
            }
        });

        // Focus management to prevent game from capturing keys
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('fullscreen-btn')) {
                // Give focus to the button instead of game
                event.target.focus();
            }
        });
    </script>
    
<?php require_once __DIR__ . '/../includes/games_footer.php'; ?>


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>geom</strong></p>
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
                <button onclick="submitGameReport('geom')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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
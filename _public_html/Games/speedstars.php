<?php
/**
 * Speed Stars - Game Wrapper
 * v5.0 - Full game page with all features
 */
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
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../control_buttons.css">
    <script src="../js/tracking.js?v=7.0" defer></script>
    <script src="../common.js"></script>
    <script src="../js/game-settings.js"></script>
    <meta charset="utf-8">
    <link rel="icon" href="/assets/images/favicon.webp">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play Speed Stars - Spencer's Game Collection">
    <title>Speed Stars - Spencer's Website</title>
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

    <!-- Control Buttons -->
    <button class="logout-btn" onclick="logout()" aria-label="Logout from website">
        🚪 Logout (<?php echo $username; ?>)
    </button>

    <button class="setting-btn" onclick="window.location.href='../set.php'">⚙️ Settings</button>

    <button class="setting backgrounds-btn" onclick="openBackgroundModal()">🎨 Backgrounds</button>

    <div class="container">
        <a href="game.php" class="centered-box">← Back to Games</a>
        <a href="../main.php" class="centered-box" style="margin-left: 10px;">🏠 Main Site</a>
    </div>

    <header class="page-header">
        <h1>🏃‍♂️💨 Speed Stars <span class="game-badge">Sprint Runner</span></h1>
        <p>Master your rhythm, perfect your stride, and race to the finish line!</p>
    </header>

    <!-- Speed Stars Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <iframe
                class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy"
                src="https://c.voidnetwork.space.cdn.cloudflare.net/~/service/hvtrs8%2F-sregdqtcrqfpeg.ko-gcmg%2Fqpgef-qtcrq%2F"
                allow="autoplay; encrypted-media; fullscreen; gamepad"
                allowfullscreen
                webkitallowfullscreen
                mozallowfullscreen
                frameborder="0"
                id="gameIframe"
                loading="lazy"
            ></iframe>
        </div>

        <div class="game-details">
            <h2>🏃‍♂️💨 About Speed Stars</h2>
            <p>Speed Stars is a physics-based competitive sprinting game developed by Luke Doukakis. Take on the role of an elite speedrunner and compete in high-speed track and field races! The gameplay is all about rhythm — alternate your key presses with perfect timing to simulate sprinting, clear hurdles, and pass batons. With realistic physics, hilarious ragdoll moments, customizable athletes, and global leaderboards, Speed Stars delivers the thrill of Olympic-style competition right in your browser!</p>

            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🏅 Multiple Race Modes</strong>
                    <p>Sprints, hurdles, relay races, and free run practice mode</p>
                </div>
                <div class="feature-item">
                    <strong>⚙️ Physics-Based Running</strong>
                    <p>Realistic athlete movements with hilarious ragdoll physics</p>
                </div>
                <div class="feature-item">
                    <strong>🎨 Athlete Customization</strong>
                    <p>Personalize your runner's name, appearance, and national flag</p>
                </div>
                <div class="feature-item">
                    <strong>🌍 Global Leaderboards</strong>
                    <p>Compete against players worldwide and climb the rankings</p>
                </div>
            </div>

            <h3>🎮 How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(0,191,255,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #00BFFF; margin: 20px 0;">
                <p><strong>Desktop Controls:</strong></p>
                <ul>
                    <li>Press <strong>Left Arrow</strong> and <strong>Right Arrow</strong> keys alternately to sprint</li>
                    <li>Press <strong>A</strong> and <strong>D</strong> keys alternately as an alternative control scheme</li>
                    <li>Hold <strong>Down Arrow</strong> to get into hurdle position when approaching a barrier</li>
                    <li>Press <strong>Down Arrow</strong> during relay races to extend your arm and pass the baton</li>
                    <li>Maintain a <strong>steady rhythm</strong> — timing matters more than speed of key presses!</li>
                </ul>

                <p><strong>Mobile/Touch Controls:</strong></p>
                <ul>
                    <li><strong>Tap</strong> the right and left sides of the screen alternately to run</li>
                    <li>Each tap corresponds to a step — perfect timing means maximum speed</li>
                    <li><strong>Swipe down</strong> or use on-screen prompts for hurdles and baton passes</li>
                </ul>
            </div>

            <h3>🏁 Race Modes</h3>
            <div style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 10px; margin: 20px 0;">
                <p style="margin-bottom: 15px;">Speed Stars features four exciting game modes:</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span class="difficulty-badge difficulty-easy">Sprints</span>
                    <span class="difficulty-badge difficulty-medium">Hurdles</span>
                    <span class="difficulty-badge difficulty-hard">4x100m Relay</span>
                    <span class="difficulty-badge difficulty-insane">Free Run</span>
                </div>
            </div>

            <h3>💡 Tips &amp; Strategies</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Focus on rhythm over raw speed — pressing keys too fast will cause your runner to stumble!</li>
                <li>Holding keys too long makes your athlete lean forward and slow down</li>
                <li>Nail the launch timing by releasing at the right moment for an explosive start off the blocks</li>
                <li>Watch your stamina bar at the top of the screen — manage your energy for a strong finish</li>
                <li>For long-distance races, conserve energy early and push hard in the final stretch</li>
                <li>Use Free Run mode to practice your rhythm and get comfortable with the controls</li>
            </ul>

            <h3>🏁 Race Modes Explained</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>Sprints:</strong> Choose from various distances — 60m dash, 100m dash, 150m straight, 200m dash, 300m dash, 400m run, and 800m run</li>
                <li><strong>Hurdles:</strong> Race the 110m or 400m hurdles — time your jumps perfectly to clear barriers without losing speed</li>
                <li><strong>4x100m Relay:</strong> Control each runner in sequence and time your baton handoffs for maximum speed</li>
                <li><strong>Free Run:</strong> An endless practice mode where you can run freely, place or remove hurdles, and perfect your technique</li>
            </ul>

            <h3>🏅 Sprint Distances</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>60m Dash:</strong> A quick explosive burst — all about your reaction time and start</li>
                <li><strong>100m Dash:</strong> The classic sprint event — pure speed from start to finish</li>
                <li><strong>150m Straight:</strong> A slightly longer sprint that tests sustained acceleration</li>
                <li><strong>200m Dash:</strong> Includes a curve — requires rhythm adjustment through the bend</li>
                <li><strong>300m Dash:</strong> A challenging middle-distance sprint that demands both speed and endurance</li>
                <li><strong>400m Run:</strong> A full lap — pace management becomes critical for a strong finish</li>
                <li><strong>800m Run:</strong> The longest event — conserve stamina early and unleash your kick at the end</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(0,191,255,0.2), rgba(255,215,0,0.2)); border: 2px solid #FFD700; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FFD700; margin-bottom: 10px; font-weight: 700;">🏆 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">Don't mash the keys as fast as possible! Speed Stars is a rhythm game at heart. Find a steady, consistent tempo and your runner will hit top speed. Practice in Free Run mode to build muscle memory before taking on competitive races!</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="game.php" class="back-button">← Back to All Games</a>
        <a href="../main.php" class="back-button">🏠 Back to Main Site</a>
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

        // Background selection functionality
        function openBackgroundModal() {
            const modal = document.getElementById('backgroundModal');
            const grid = document.getElementById('backgroundsGrid');

            grid.innerHTML = '';

            const backgrounds = <?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>;
            const currentBackground = getCurrentBackground();

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
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            settings.customBackground = imageUrl;
            settings.customBackgroundTitle = title;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

            applyActiveBackground();
            closeBackgroundModal();
            showNotification(`🎨 Background set to "${title}"!`);
            setTimeout(openBackgroundModal, 100);
        }

        function removeCustomBackground() {
            let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}');
            delete settings.customBackground;
            delete settings.customBackgroundTitle;
            localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings));

            applyActiveBackground();
            closeBackgroundModal();
            showNotification('🗑️ Custom background removed!');
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

            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

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

        function applyActiveBackground() {
            const bgOverride = document.getElementById('bgThemeOverride');
            if (!bgOverride) return;

            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                if (settings.customBackground && settings.customBackground.trim() !== '') {
                    bgOverride.style.backgroundImage = `url('${settings.customBackground}')`;
                    bgOverride.classList.remove('designer-bg');
                    return;
                }
            }

            <?php if ($active_designer_background): ?>
                bgOverride.style.backgroundImage = `url('<?php echo $active_designer_background['image_url']; ?>')`;
                bgOverride.classList.add('designer-bg');
            <?php endif; ?>
        }

        document.addEventListener('DOMContentLoaded', function() {
            const savedSettings = localStorage.getItem('spencerWebsiteSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);

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
                        .centered-box { border-color: ${accentColor} !important; }
                        .centered-box:hover { border-color: #FF6B6B !important; }
                        .back-button { background: linear-gradient(45deg, #667eea, #764ba2) !important; }
                        .page-header h1 {
                            background: linear-gradient(45deg, #00BFFF, ${accentColor}) !important;
                            -webkit-background-clip: text !important;
                            -webkit-text-fill-color: transparent !important;
                            background-clip: text !important;
                        }
                    `;
                }

                if (settings.fontSize) {
                    document.documentElement.style.fontSize = `${settings.fontSize}px`;
                }
            }

            applyActiveBackground();
        });

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('backgroundModal');
            if (event.target === modal) {
                closeBackgroundModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                logout();
            }
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'set.php';
            }
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                openBackgroundModal();
            }
        });

        function toggleFullscreen() {
            const gameContainer = document.getElementById('gameContainer');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');

            if (!document.fullscreenElement) {
                if (gameContainer.requestFullscreen) {
                    gameContainer.requestFullscreen();
                } else if (gameContainer.webkitRequestFullscreen) {
                    gameContainer.webkitRequestFullscreen();
                } else if (gameContainer.mozRequestFullScreen) {
                    gameContainer.mozRequestFullScreen();
                } else if (gameContainer.msRequestFullscreen) {
                    gameContainer.msRequestFullscreen();
                }
                gameContainer.classList.add('fullscreen');
                fullscreenBtn.textContent = '❐ Exit Fullscreen';
            } else {
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

        document.addEventListener('keydown', function(event) {
            if (event.key === 'f' || event.key === 'F') {
                event.preventDefault();
                toggleFullscreen();
            }

            if (event.key === 'Escape') {
                const gameContainer = document.getElementById('gameContainer');
                if (gameContainer.classList.contains('fullscreen')) {
                    toggleFullscreen();
                }
            }
        });
    </script>


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>speedstars</strong></p>
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
                <button onclick="submitGameReport('speedstars')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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

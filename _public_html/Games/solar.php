<?php
/**
 * Solar Smash - Game Wrapper
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
    <meta name="description" content="Play Solar Smash - Spencer's Game Collection">
    <title>Solar Smash - Spencer's Website</title>
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
        <h1>🌍💥 Solar Smash <span class="game-badge">Destruction Simulator</span></h1>
        <p>Unleash cosmic destruction and obliterate planets with powerful weapons!</p>
    </header>

    <!-- Solar Smash Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <iframe
                class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy"
                src="https://c.voidnetwork.space.cdn.cloudflare.net/onlylocal/html/118.html"
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
            <h2>🌍💥 About Solar Smash</h2>
            <p>Solar Smash is a physics-based sandbox simulation game developed by Paradyme Games where you wield the power to destroy planets and celestial bodies. Use an arsenal of over 50 devastating weapons — from nuclear missiles and laser beams to black holes, alien invasions, and giant monsters — to obliterate worlds in spectacular fashion. Watch as realistic physics simulations show planets breaking apart, burning, and disintegrating with stunning visual effects!</p>

            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🪐 Planet Smash Mode</strong>
                    <p>Target individual planets and destroy them with creative weapons</p>
                </div>
                <div class="feature-item">
                    <strong>☀️ System Smash Mode</strong>
                    <p>Manipulate entire solar systems and cause massive celestial collisions</p>
                </div>
                <div class="feature-item">
                    <strong>💣 50+ Weapons</strong>
                    <p>Lasers, nukes, meteors, black holes, UFOs, monsters, and more!</p>
                </div>
                <div class="feature-item">
                    <strong>🔬 Realistic Physics</strong>
                    <p>Stunning destruction with authentic fragment patterns and gravity effects</p>
                </div>
            </div>

            <h3>🎮 How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,69,0,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #FF4500; margin: 20px 0;">
                <p><strong>Desktop Controls:</strong></p>
                <ul>
                    <li><strong>Left Click</strong> to select and deploy weapons from the weapon menu</li>
                    <li><strong>Click on planet</strong> to target where you want to strike</li>
                    <li><strong>Drag</strong> to aim and adjust trajectory for some weapons</li>
                    <li><strong>Scroll Wheel</strong> to zoom in and out on the planet</li>
                    <li><strong>Right Click + Drag</strong> to rotate and orbit around the planet</li>
                </ul>

                <p><strong>Mobile/Touch Controls:</strong></p>
                <ul>
                    <li><strong>Tap</strong> the weapon menu to select your destructive tool</li>
                    <li><strong>Tap</strong> on the planet to launch your attack</li>
                    <li><strong>Pinch</strong> to zoom in and out</li>
                    <li><strong>Swipe</strong> to rotate the planet view</li>
                </ul>
            </div>

            <h3>💣 Weapon Categories</h3>
            <div style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 10px; margin: 20px 0;">
                <p style="margin-bottom: 15px;">Solar Smash features a massive arsenal of destruction tools:</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span class="difficulty-badge difficulty-easy">Missiles</span>
                    <span class="difficulty-badge difficulty-easy">Lasers</span>
                    <span class="difficulty-badge difficulty-medium">Meteors</span>
                    <span class="difficulty-badge difficulty-medium">Nukes</span>
                    <span class="difficulty-badge difficulty-hard">Black Holes</span>
                    <span class="difficulty-badge difficulty-insane">Alien Invasions</span>
                </div>
            </div>

            <h3>💡 Tips &amp; Strategies</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Experiment with different weapons — each one creates unique destruction effects!</li>
                <li>Try combining multiple weapons for devastating chain reactions</li>
                <li>Zoom in to appreciate the detailed damage on the planet's surface</li>
                <li>Use black holes to consume everything in their path for maximum destruction</li>
                <li>Each planet reacts differently — explore them all for new challenges</li>
                <li>Try to discover secret planets like Donut Earth, Cube Earth, and Flat Earth</li>
            </ul>

            <h3>🪐 Game Modes Explained</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>Planet Smash:</strong> Choose a planet and unleash your full arsenal to completely destroy it</li>
                <li><strong>System Smash:</strong> Move celestial bodies around, alter orbits, and crash planets into each other</li>
                <li><strong>Custom Planets:</strong> Create your own planet, populate it, then obliterate your creation</li>
                <li><strong>Secret Planets:</strong> Discover hidden worlds like Snowman, Pumpkin, Ghost World, and more</li>
            </ul>

            <h3>🔫 Weapon Types Guide</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>Nuclear Missiles:</strong> Classic explosive warheads that scorch the surface</li>
                <li><strong>Laser Beams:</strong> Powerful concentrated energy that slices through planets</li>
                <li><strong>Asteroids:</strong> Hurl massive space rocks for devastating impacts</li>
                <li><strong>Black Holes:</strong> Create a gravitational singularity that devours everything</li>
                <li><strong>UFO Fleet:</strong> Summon alien ships to bombard the planet</li>
                <li><strong>Giant Monsters:</strong> Unleash colossal creatures that tear planets apart</li>
                <li><strong>Ion Cannons:</strong> Orbital weapons that fire concentrated energy beams</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,69,0,0.2), rgba(255,215,0,0.2)); border: 2px solid #FFD700; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FFD700; margin-bottom: 10px; font-weight: 700;">🌌 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">Try firing 66 lasers at Earth without stopping to unlock a secret achievement! Also, destroying 5 planets in one session can unlock the hidden Galaxy Smash mode!</p>
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
                            background: linear-gradient(45deg, #FF4500, ${accentColor}) !important;
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
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>solar</strong></p>
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
                <button onclick="submitGameReport('solar')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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

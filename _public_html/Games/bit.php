<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}
$__bgfile = __DIR__ . '/../load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }


// Fetch active designer background (same as in game.php)
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
    <link rel="icon" href="/assets/images/favicon.webp">
    
    <link rel="stylesheet" href="../control_buttons.css">
    <script src="../js/tracking.js?v=7.0" defer></script>
    <script src="../common.js"></script>
    <script src="../js/game-settings.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play BitLife - Spencer's Game Collection">
    <title>BitLife - Spencer's Website</title>
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
        <h1>🎮 BitLife <span class="game-badge">Life Simulator</span></h1>
        <p>Live your virtual life from birth to death in this addictive text-based life simulator!</p>
    </header>

    <!-- BitLife Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <iframe 
                class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy"
                src="https://searchprox.global.ssl.fastly.net/public/assets/games/bitlife/" 
                allow="autoplay; encrypted-media; fullscreen"
                allowfullscreen
                webkitallowfullscreen
                mozallowfullscreen
                frameborder="0"
                id="gameIframe"
            ></iframe>
        </div>
        
        <div class="game-details">
            <h2>🎯 About BitLife</h2>
            <p>BitLife is a text-based life simulation game where you can simulate a person's life from birth to death. Make choices about education, career, relationships, and more as you navigate the challenges and opportunities of life!</p>
            
            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🧬 Life Simulation</strong>
                    <p>Experience a full life from birth to old age with realistic events</p>
                </div>
                <div class="feature-item">
                    <strong>🎓 Education & Career</strong>
                    <p>Choose your education path and build your dream career</p>
                </div>
                <div class="feature-item">
                    <strong>💑 Relationships</strong>
                    <p>Date, marry, have children, and build meaningful relationships</p>
                </div>
                <div class="feature-item">
                    <strong>💰 Wealth Management</strong>
                    <p>Manage your finances, buy properties, and build wealth</p>
                </div>
                <div class="feature-item">
                    <strong>🎯 Random Events</strong>
                    <p>Encounter unexpected life events that change your path</p>
                </div>
                <div class="feature-item">
                    <strong>🏆 Achievements</strong>
                    <p>Complete challenges and unlock special achievements</p>
                </div>
            </div>

            <h3>🎮 How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,107,107,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #4ECDC4; margin: 20px 0;">
                <p><strong>Desktop Controls:</strong></p>
                <ul>
                    <li><strong>Mouse Click</strong> - Navigate menus and make choices</li>
                    <li><strong>Scroll</strong> - Browse through different life options</li>
                    <li><strong>Spacebar</strong> - Age up to the next year</li>
                    <li>Make <strong>strategic decisions</strong> that affect your life path</li>
                </ul>
                
                <p><strong>Mobile/Touch Controls:</strong></p>
                <ul>
                    <li><strong>Tap</strong> to select options and make decisions</li>
                    <li><strong>Swipe</strong> to scroll through menus</li>
                    <li>Intuitive touch-based interface</li>
                </ul>
            </div>

            <h3>💡 Gameplay Tips</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Focus on <strong>education early</strong> for better career opportunities</li>
                <li>Maintain good <strong>health and happiness</strong> for a longer life</li>
                <li><strong>Save money</strong> for unexpected expenses and retirement</li>
                <li>Build <strong>strong relationships</strong> for emotional support</li>
                <li>Consider <strong>multiple career paths</strong> if you're unhappy</li>
                <li>Take <strong>calculated risks</strong> for potentially big rewards</li>
            </ul>

            <h3>🏆 Objectives</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Live to 100 years old</li>
                <li>Become a millionaire or billionaire</li>
                <li>Have a successful career and family</li>
                <li>Complete all available achievements</li>
                <li>Experience different life paths and outcomes</li>
                <li>Maximize your character's happiness and fulfillment</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #FF6B6B; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FF6B6B; margin-bottom: 10px; font-weight: 700;">🎯 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">The key to a successful BitLife is balancing education, career, relationships, and health. Don't neglect any aspect of your virtual life!</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="game.php" class="back-button">← Back to All Games</a>
        <a href="../main.php" class="back-button">🏠 Back to Main Site</a>
    </div>

    
    
<?php require_once __DIR__ . '/../includes/games_footer.php'; ?>


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>bit</strong></p>
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
                <button onclick="submitGameReport('bit')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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
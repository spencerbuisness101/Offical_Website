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
    <script src="../js/tracking.js?v=7.0" defer></script>
    <link rel="stylesheet" href="../control_buttons.css">
    
    <link rel="icon" href="/assets/images/favicon.webp">
    <script src="../common.js"></script>
    <script src="../js/game-settings.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play Street Fighter EX - Spencer's Game Collection">
    <title>Street Fighter EX - Spencer's Website</title>
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
        <h1>🥊 Street Fighter EX <span class="game-badge">Classic Fighting Game</span></h1>
        <p>Experience the 3D evolution of the legendary Street Fighter series with new characters and mechanics!</p>
    </header>

    <!-- Street Fighter EX Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <iframe 
                class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy"
                src="https://archive.org/embed/arcade_sfex2" 
                allow="autoplay; encrypted-media; fullscreen"
                allowfullscreen
                webkitallowfullscreen
                mozallowfullscreen
                frameborder="0"
                id="gameIframe"
            ></iframe>
        </div>
        
        <div class="game-details">
            <h2>🥊 About Street Fighter EX</h2>
            <p>Street Fighter EX is a groundbreaking 3D fighting game that brought the classic Street Fighter gameplay into a new dimension. Developed by Arika and published by Capcom, this game introduced new characters while retaining the core mechanics that made the series legendary.</p>
            
            <h3>⭐ Game Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🎮 Classic Fighting</strong>
                    <p>Authentic Street Fighter combat with 3D graphics</p>
                </div>
                <div class="feature-item">
                    <strong>👤 New Characters</strong>
                    <p>Introduces fighters like Skullomania, Garuda, and more</p>
                </div>
                <div class="feature-item">
                    <strong>💥 Super Combos</strong>
                    <p>Devastating super moves and special techniques</p>
                </div>
                <div class="feature-item">
                    <strong>🔄 3D Movement</strong>
                    <p>Sidestep and move in 3D space while maintaining 2D gameplay</p>
                </div>
                <div class="feature-item">
                    <strong>🎯 Technical Gameplay</strong>
                    <p>Deep mechanics for competitive play</p>
                </div>
                <div class="feature-item">
                    <strong>🎵 Iconic Soundtrack</strong>
                    <p>Memorable music that enhances the fighting experience</p>
                </div>
            </div>

<h3>🎮 How to Play</h3>
<div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,107,107,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #FF6B6B; margin: 20px 0;">
    <div class="control-scheme">
        <!-- Basic Controls -->
        <div class="player-controls">
            <h4 style="color: #FF6B6B; margin-bottom: 15px;">🎮 Basic Controls</h4>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>Arrow Keys</strong> — Move your fighter</li>
                <li><strong>Left Shift</strong> — Low Kick</li>
                <li><strong>Z</strong> — Mid Kick</li>
                <li><strong>X</strong> — High Kick</li>
                <li><strong>Left Ctrl</strong> — Low Punch</li>
                <li><strong>Left Alt</strong> — Mid Punch</li>
                <li><strong>Spacebar</strong> — High Punch</li>
                <li><strong>5 or 6</strong> — Insert Coin</li>
                <li><strong>1 or 2</strong> — Select Player</li>
            </ul>
        </div>

        <!-- Special Moves -->
        <div class="player-controls">
            <h4 style="color: #4ECDC4; margin-bottom: 15px;">💥 Special Moves</h4>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li><strong>Hadouken</strong> — ↓ ↘ → + Punch</li>
                <li><strong>Shoryuken</strong> — → ↓ ↘ + Punch</li>
                <li><strong>Tatsumaki</strong> — ↓ ↙ ← + Kick</li>
                <li><strong>Super Combo</strong> — Chain multiple attacks for a powerful finisher</li>
                <li><strong>Guard</strong> — Hold ← or → + Medium Punch + Medium Kick</li>
            </ul>
        </div>
    </div>
</div>
</div>
                
                <p><strong>Game Objective:</strong> Defeat your opponent by reducing their health to zero before time runs out. Master special moves, combos, and super arts to become the ultimate fighter!</p>
            </div>

            <h3>👥 Character Roster</h3>
            <div class="character-list">
                <div class="character-item">
                    <strong>Ryu</strong>
                    <p>The wandering warrior</p>
                </div>
                <div class="character-item">
                    <strong>Ken</strong>
                    <p>Ryu's friendly rival</p>
                </div>
                <div class="character-item">
                    <strong>Chun-Li</strong>
                    <p>Strongest woman in the world</p>
                </div>
                <div class="character-item">
                    <strong>Guile</strong>
                    <p>American air force major</p>
                </div>
                <div class="character-item">
                    <strong>Skullomania</strong>
                    <p>Salaryman superhero</p>
                </div>
                <div class="character-item">
                    <strong>Garuda</strong>
                    <p>Mysterious ancient being</p>
                </div>
                <div class="character-item">
                    <strong>Kairi</strong>
                    <p>Young martial artist</p>
                </div>
                <div class="character-item">
                    <strong>Hokuto</strong>
                    <p>Kairi's sister</p>
                </div>
            </div>

            <h3>💡 Gameplay Tips</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Learn the timing for special moves - practice makes perfect!</li>
                <li>Master at least one character's complete move set</li>
                <li>Use the 3D sidestep to avoid linear attacks</li>
                <li>Don't spam special moves - they leave you vulnerable</li>
                <li>Learn to read your opponent's patterns and habits</li>
                <li>Practice combos in training mode before using them in matches</li>
                <li>Manage your super meter wisely - don't waste it</li>
            </ul>

            <h3>🏆 Progression Strategy</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>Start with Ryu or Ken to learn the basics</li>
                <li>Practice special move inputs until they become second nature</li>
                <li>Learn basic combos before attempting advanced ones</li>
                <li>Study each character's strengths and weaknesses</li>
                <li>Master blocking and counter-attacking</li>
                <li>Experiment with different characters to find your main</li>
                <li>Watch replays of your matches to identify areas for improvement</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #FF6B6B; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #FF6B6B; margin-bottom: 10px; font-weight: 700;">🎯 Pro Tip</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">The key to success in Street Fighter EX is patience and observation. Don't rush in blindly - watch your opponent's patterns, learn when to attack and when to defend, and always save your super meter for critical moments. Remember, sometimes the best offense is a well-timed defense!</p>
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
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>com</strong></p>
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
                <button onclick="submitGameReport('com')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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
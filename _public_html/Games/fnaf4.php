<?php
/**
 * Five Nights at Freddy's 4 - Game Page
 */
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}
$__bgfile = __DIR__ . '/../load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }

$active_designer_background = null;
$available_backgrounds = [];

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $bgStmt = $db->query("SELECT db.image_url, db.title, u.username as designer_name FROM designer_backgrounds db LEFT JOIN users u ON db.user_id = u.id WHERE db.is_active = 1 AND db.status = 'approved' LIMIT 1");
    $active_designer_background = $bgStmt->fetch(PDO::FETCH_ASSOC);

    $allBgStmt = $db->query("SELECT db.id, db.image_url, db.title, u.username as designer_name FROM designer_backgrounds db LEFT JOIN users u ON db.user_id = u.id WHERE db.status = 'approved' ORDER BY db.is_active DESC, db.created_at DESC");
    $available_backgrounds = $allBgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database fetch error: " . $e->getMessage());
}

$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'];
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../control_buttons.css">
    <script src="../js/tracking.js?v=7.0" defer></script>
    <script src="../common.js"></script>
    <script src="../js/game-settings.js"></script>
    <meta charset="utf-8">
    <link rel="icon" href="/assets/images/favicon.webp">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Five Nights at Freddy's 4 - Spencer's Game Collection">
    <title>FNAF 4 - Spencer's Website</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/game-common.css">
</head>
<body data-backgrounds='<?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>' data-active-background='<?php echo $active_designer_background ? htmlspecialchars($active_designer_background['image_url'], ENT_QUOTES, 'UTF-8') : ''; ?>'>
    <?php require_once __DIR__ . "/../includes/identity_bar.php"; ?>
    <div class="bg-theme-override" id="bgThemeOverride"></div>
    <div id="backgroundModal" class="background-modal">
        <div class="background-modal-content">
            <div class="background-modal-header">
                <h2 class="background-modal-title">Choose Your Background</h2>
                <button class="background-modal-close" onclick="closeBackgroundModal()">&times;</button>
            </div>
            <div class="backgrounds-grid" id="backgroundsGrid"></div>
            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn-remove-background" onclick="removeCustomBackground()" style="padding: 12px 24px; font-size: 1rem;">Remove Custom Background</button>
            </div>
        </div>
    </div>

    <button class="logout-btn" onclick="logout()">Logout (<?php echo $username; ?>)</button>
    <button class="setting-btn" onclick="window.location.href='../set.php'">Settings</button>
    <button class="setting backgrounds-btn" onclick="openBackgroundModal()">Backgrounds</button>

    <div class="container">
        <a href="game.php" class="centered-box">Back to Games</a>
        <a href="../main.php" class="centered-box" style="margin-left: 10px;">Main Site</a>
    </div>

    <header class="page-header">
        <h1>Five Nights at Freddy's 4 <span class="game-badge">FNAF</span></h1>
        <p>The nightmare animatronics haunt your bedroom!</p>
    </header>

    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">Fullscreen</button>
            <iframe class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy" src="https://thespencergamingwebsite.com/clfnaf4.html" allow="autoplay; encrypted-media; fullscreen" allowfullscreen id="gameIframe"></iframe>
        </div>

        <div class="game-details">
            <h2>About Five Nights at Freddy's 4</h2>
            <p>Five Nights at Freddy's 4 takes the horror to a whole new level. You play as a child trapped in your bedroom, haunted by terrifying nightmare versions of the animatronics. No cameras, no doors - just you, a flashlight, and your hearing.</p>

            <h3>Game Features</h3>
            <div class="features-grid">
                <div class="feature-item"><strong>Nightmare Animatronics</strong><p>Face twisted, terrifying versions of Freddy, Bonnie, Chica, and Foxy</p></div>
                <div class="feature-item"><strong>Audio-Based Gameplay</strong><p>Listen carefully for breathing sounds</p></div>
                <div class="feature-item"><strong>Closet & Bed</strong><p>Check multiple locations to survive</p></div>
                <div class="feature-item"><strong>Most Terrifying Entry</strong><p>The scariest FNAF game yet</p></div>
            </div>

            <h3>How to Play</h3>
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(78,205,196,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #FF6B6B; margin: 20px 0;">
                <p><strong>Controls:</strong></p>
                <ul>
                    <li>Move to left and right doors</li>
                    <li>Hold door to listen for breathing</li>
                    <li>Use flashlight to scare away animatronics (if you don't hear breathing)</li>
                    <li>Check the closet for Foxy</li>
                    <li>Turn around to check for Freddles on the bed</li>
                </ul>
            </div>

            <h3>Gameplay Tips</h3>
            <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
                <li>ALWAYS listen before using the flashlight at doors</li>
                <li>If you hear breathing, keep the door closed until they leave</li>
                <li>Using flashlight when they're breathing = instant death</li>
                <li>Check the closet regularly for Nightmare Foxy</li>
                <li>Don't let Freddles accumulate on your bed</li>
                <li>Use headphones - audio cues are essential!</li>
            </ul>

            <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #4ECDC4; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
                <h4 style="color: #4ECDC4; margin-bottom: 10px; font-weight: 700;">Warning</h4>
                <p style="color: #f8f9fa; font-weight: 500; margin: 0;">This is the scariest FNAF game. Contains intense jump scares and psychological horror. Headphones recommended but... be prepared!</p>
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="game.php" class="back-button">Back to All Games</a>
            <a href="../main.php" class="back-button">Back to Main Site</a>
        </div>
    </div>

    <script>
        function logout() { if (confirm('Are you sure you want to logout?')) { const logoutBtn = document.querySelector('.logout-btn'); logoutBtn.innerHTML = 'Logging out...'; logoutBtn.disabled = true; fetch('auth/logout.php').then(response => { if (response.ok) { logoutBtn.innerHTML = 'Success!'; setTimeout(() => window.location.href = 'index.php', 1000); } else { throw new Error('Logout failed'); } }).catch(error => { logoutBtn.innerHTML = 'Logout'; logoutBtn.disabled = false; alert('Logout failed. Please try again.'); }); } }
        function openBackgroundModal() { const modal = document.getElementById('backgroundModal'); const grid = document.getElementById('backgroundsGrid'); grid.innerHTML = ''; const backgrounds = <?php echo htmlspecialchars(json_encode($available_backgrounds), ENT_QUOTES, 'UTF-8'); ?>; const currentBackground = getCurrentBackground(); backgrounds.forEach(background => { const isActive = currentBackground === background.image_url; const backgroundItem = document.createElement('div'); backgroundItem.className = `background-item ${isActive ? 'active' : ''}`; backgroundItem.innerHTML = `<div class="background-preview" style="background-image: url('${background.image_url}')"></div><div class="background-info"><div class="background-title">${escapeHtml(background.title)}</div><div class="background-designer">By: ${escapeHtml(background.designer_name)}</div><div class="background-actions"><button class="btn-set-background" onclick="setAsBackground('${background.image_url}', '${escapeHtml(background.title)}')">${isActive ? 'Using' : 'Use This'}</button></div></div>`; grid.appendChild(backgroundItem); }); modal.style.display = 'block'; }
        function closeBackgroundModal() { document.getElementById('backgroundModal').style.display = 'none'; }
        function setAsBackground(imageUrl, title) { let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}'); settings.customBackground = imageUrl; settings.customBackgroundTitle = title; localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings)); applyActiveBackground(); closeBackgroundModal(); showNotification(`Background set to "${title}"!`); }
        function removeCustomBackground() { let settings = JSON.parse(localStorage.getItem('spencerWebsiteSettings') || '{}'); delete settings.customBackground; delete settings.customBackgroundTitle; localStorage.setItem('spencerWebsiteSettings', JSON.stringify(settings)); applyActiveBackground(); closeBackgroundModal(); showNotification('Custom background removed!'); }
        function getCurrentBackground() { const savedSettings = localStorage.getItem('spencerWebsiteSettings'); if (savedSettings) { const settings = JSON.parse(savedSettings); return settings.customBackground || null; } return null; }
        function showNotification(message) { const notification = document.createElement('div'); notification.style.cssText = `position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 10000; font-weight: 600; transform: translateX(400px); transition: transform 0.3s ease;`; notification.textContent = message; document.body.appendChild(notification); setTimeout(() => notification.style.transform = 'translateX(0)', 100); setTimeout(() => { notification.style.transform = 'translateX(400px)'; setTimeout(() => document.body.removeChild(notification), 300); }, 3000); }
        function escapeHtml(unsafe) { return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;"); }
        function applyActiveBackground() { const bgOverride = document.getElementById('bgThemeOverride'); if (!bgOverride) return; const savedSettings = localStorage.getItem('spencerWebsiteSettings'); if (savedSettings) { const settings = JSON.parse(savedSettings); if (settings.customBackground && settings.customBackground.trim() !== '') { bgOverride.style.backgroundImage = `url('${settings.customBackground}')`; bgOverride.classList.remove('designer-bg'); return; } } <?php if ($active_designer_background): ?> bgOverride.style.backgroundImage = `url('<?php echo $active_designer_background['image_url']; ?>')`; bgOverride.classList.add('designer-bg'); <?php endif; ?> }
        document.addEventListener('DOMContentLoaded', function() { applyActiveBackground(); });
        window.addEventListener('click', function(event) { const modal = document.getElementById('backgroundModal'); if (event.target === modal) closeBackgroundModal(); });
        function toggleFullscreen() { const gameContainer = document.getElementById('gameContainer'); const fullscreenBtn = document.querySelector('.fullscreen-btn'); if (!document.fullscreenElement) { if (gameContainer.requestFullscreen) gameContainer.requestFullscreen(); else if (gameContainer.webkitRequestFullscreen) gameContainer.webkitRequestFullscreen(); gameContainer.classList.add('fullscreen'); fullscreenBtn.textContent = 'Exit Fullscreen'; } else { if (document.exitFullscreen) document.exitFullscreen(); else if (document.webkitExitFullscreen) document.webkitExitFullscreen(); gameContainer.classList.remove('fullscreen'); fullscreenBtn.textContent = 'Fullscreen'; } }
        document.addEventListener('fullscreenchange', function() { const gameContainer = document.getElementById('gameContainer'); const fullscreenBtn = document.querySelector('.fullscreen-btn'); if (!document.fullscreenElement) { gameContainer.classList.remove('fullscreen'); fullscreenBtn.textContent = 'Fullscreen'; } });
    </script>


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>fnaf4</strong></p>
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
                <button onclick="submitGameReport('fnaf4')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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

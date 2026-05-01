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
    <meta name="description" content=">CS:GO Clicker - Spencer's Game Collection">
    <title>CS:GO Clicker - Spencer's Website</title>
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
        <h1>🎯 CS:GO Clicker <span class="game-badge">Arcade Adventure</span></h1>
        <p>Navigate through challenging mazes in this fast-paced arcade adventure! (AutoClicker Tab!)</p>
    </header>

    <!-- Tomb of the Mask Game Player -->
    <div class="game-player">
        <div class="game-container" id="gameContainer">
            <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
            <iframe 
                class="game-iframe"
                sandbox="allow-scripts allow-same-origin allow-popups"
                loading="lazy"
                src="https://thespencergamingwebsite.com/clcsgo.html" 
                allow="autoplay; encrypted-media; fullscreen"
                allowfullscreen
                webkitallowfullscreen
                mozallowfullscreen
                frameborder="0"
                id="gameIframe"
            ></iframe>
        </div>

    <!-- GOD MODE CHEAT DASHBOARD -->
    <div id="cheatPanel" class="cheat-panel">
        <div class="cheat-header" onclick="toggleCheatPanel()">
            <span>🔥 GOD MODE</span>
            <span class="cheat-toggle-arrow" id="cheatArrow">▼</span>
        </div>
        <div class="cheat-body" id="cheatBody">
            <!-- Economy Control -->
            <div class="cheat-section">
                <h4>💰 Economy Control</h4>
                <div class="cheat-row">
                    <input type="number" id="cheatMoney" placeholder="Amount ($)" min="0" step="1000" class="cheat-input">
                    <button onclick="cheatSetMoney()" class="cheat-btn gold">Set Money</button>
                </div>
                <button onclick="cheatSetMoney(999999999)" class="cheat-btn gold full">💎 Max Money ($999,999,999)</button>
            </div>

            <!-- RNG Manipulation -->
            <div class="cheat-section">
                <h4>🍀 RNG Manipulation</h4>
                <label class="cheat-switch">
                    <input type="checkbox" id="cheatLuck" onchange="cheatToggleLuck()">
                    <span class="cheat-slider"></span>
                    <span class="cheat-label">100x Luck (Rare Drops Only)</span>
                </label>
            </div>

            <!-- Inventory Purge -->
            <div class="cheat-section">
                <h4>🗑️ Inventory Filter Purge</h4>
                <div class="cheat-row">
                    <input type="number" id="cheatPurgePrice" placeholder="Min price ($)" min="0" step="1" class="cheat-input">
                    <button onclick="cheatPurgeInventory()" class="cheat-btn red">Purge Under $</button>
                </div>
            </div>

            <!-- Auto-Grinder -->
            <div class="cheat-section">
                <h4>🤖 Auto-Grinder</h4>
                <label class="cheat-switch">
                    <input type="checkbox" id="cheatAutoGrind" onchange="cheatToggleAutoGrind()">
                    <span class="cheat-slider"></span>
                    <span class="cheat-label">Auto Buy → Open → Sell Loop</span>
                </label>
                <div class="cheat-status" id="grinderStatus">Status: Idle</div>
            </div>

            <!-- Zero-Delay -->
            <div class="cheat-section">
                <h4>⚡ Zero-Delay Case Opening</h4>
                <label class="cheat-switch">
                    <input type="checkbox" id="cheatZeroDelay" onchange="cheatToggleZeroDelay()">
                    <span class="cheat-slider"></span>
                    <span class="cheat-label">Skip All Animations</span>
                </label>
            </div>

            <!-- Unlock All Cases -->
            <div class="cheat-section">
                <h4>🔓 Unlock All Cases</h4>
                <button onclick="cheatUnlockAllCases()" class="cheat-btn purple full">Unlock Every Case</button>
            </div>

            <!-- Inventory Value Multiplier -->
            <div class="cheat-section">
                <h4>📈 Inventory Value Multiplier</h4>
                <div class="cheat-row">
                    <select id="cheatMultiplier" class="cheat-input">
                        <option value="2">2x</option>
                        <option value="5">5x</option>
                        <option value="10" selected>10x</option>
                        <option value="100">100x</option>
                        <option value="1000">1000x</option>
                    </select>
                    <button onclick="cheatMultiplyValues()" class="cheat-btn green">Multiply All</button>
                </div>
            </div>

            <!-- Infinite Inventory Space -->
            <div class="cheat-section">
                <h4>📦 Infinite Inventory</h4>
                <button onclick="cheatInfiniteInventory()" class="cheat-btn cyan full">Set 99999 Slots</button>
            </div>

            <!-- GOD MODE MASTER TOGGLE -->
            <div class="cheat-section god-section">
                <button onclick="cheatGodMode()" class="cheat-btn god full">⚡ ACTIVATE GOD MODE ⚡</button>
                <p class="cheat-hint">Enables ALL cheats at maximum power</p>
            </div>

            <div class="cheat-footer">
                <button onclick="cheatResetGame()" class="cheat-btn red full" style="font-size:11px;">🔄 Reset Game Save</button>
            </div>
        </div>
    </div>
    </div><!-- /game-player -->

    <style>
        /* Cheat Panel Layout - optimized for 1280x960 */
        .game-player { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-start; }
        .game-container { flex: 1 1 60%; min-width: 500px; }
        .cheat-panel { flex: 0 0 280px; max-width: 300px; background: linear-gradient(180deg, #1a1a2e 0%, #0f0f1a 100%); border: 1px solid #ff4757; border-radius: 12px; overflow: hidden; box-shadow: 0 0 20px rgba(255,71,87,0.3); font-family: 'Segoe UI', sans-serif; }
        .cheat-header { background: linear-gradient(90deg, #ff4757, #ff6b81); padding: 12px 16px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 16px; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5); user-select: none; }
        .cheat-toggle-arrow { transition: transform 0.3s; }
        .cheat-body { padding: 10px; max-height: 600px; overflow-y: auto; transition: max-height 0.4s ease; }
        .cheat-body.collapsed { max-height: 0; padding: 0 10px; overflow: hidden; }
        .cheat-section { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 10px; margin-bottom: 8px; }
        .cheat-section h4 { color: #ffd32a; font-size: 12px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .cheat-row { display: flex; gap: 6px; align-items: center; }
        .cheat-input { flex: 1; background: #16213e; border: 1px solid #2d3a5c; color: #e2e8f0; padding: 7px 10px; border-radius: 6px; font-size: 13px; outline: none; }
        .cheat-input:focus { border-color: #ff4757; box-shadow: 0 0 6px rgba(255,71,87,0.3); }
        .cheat-btn { padding: 7px 12px; border: none; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.3px; }
        .cheat-btn:hover { transform: scale(1.03); filter: brightness(1.2); }
        .cheat-btn:active { transform: scale(0.97); }
        .cheat-btn.full { width: 100%; margin-top: 4px; }
        .cheat-btn.gold { background: linear-gradient(135deg, #f0932b, #f9ca24); color: #1a1a2e; }
        .cheat-btn.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
        .cheat-btn.green { background: linear-gradient(135deg, #2ecc71, #27ae60); color: #fff; }
        .cheat-btn.purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); color: #fff; }
        .cheat-btn.cyan { background: linear-gradient(135deg, #00cec9, #0984e3); color: #fff; }
        .cheat-btn.god { background: linear-gradient(135deg, #ff4757, #ffd32a, #ff4757); background-size: 200% 200%; animation: godPulse 2s ease infinite; color: #1a1a2e; font-size: 14px; padding: 12px; text-shadow: 0 0 10px rgba(255,255,255,0.5); }
        @keyframes godPulse { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .god-section { border-color: #ff4757; }
        .cheat-switch { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .cheat-switch input { display: none; }
        .cheat-slider { width: 40px; height: 20px; background: #2d3a5c; border-radius: 20px; position: relative; transition: background 0.3s; flex-shrink: 0; }
        .cheat-slider::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.3s; }
        .cheat-switch input:checked + .cheat-slider { background: #2ecc71; }
        .cheat-switch input:checked + .cheat-slider::after { transform: translateX(20px); }
        .cheat-label { color: #a0aec0; font-size: 12px; }
        .cheat-status { color: #a0aec0; font-size: 11px; margin-top: 6px; padding: 4px 8px; background: rgba(0,0,0,0.2); border-radius: 4px; }
        .cheat-hint { color: #718096; font-size: 10px; text-align: center; margin: 4px 0 0; }
        .cheat-footer { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.06); }
        .cheat-body::-webkit-scrollbar { width: 4px; }
        .cheat-body::-webkit-scrollbar-track { background: transparent; }
        .cheat-body::-webkit-scrollbar-thumb { background: #ff4757; border-radius: 4px; }
        @media (max-width: 900px) { .cheat-panel { flex: 1 1 100%; max-width: 100%; } }
    </style>

<div class="game-details">
    <h2>🎯 About CS:GO Clicker</h2>
    <p>CS:GO Clicker is an addictive clicking and upgrading game inspired by Counter-Strike. Click to earn credits, unlock weapon skins, upgrade your clicking power, and build your arsenal as you rise to the top!</p>
    
    <h3>⭐ Game Features</h3>
    <div class="features-grid">
        <div class="feature-item">
            <strong>🔫 Weapon Upgrades</strong>
            <p>Unlock and upgrade a variety of CS:GO-inspired weapons</p>
        </div>
        <div class="feature-item">
            <strong>⚡ Fast-Paced Clicking</strong>
            <p>Click fast to earn more credits and boost your DPS</p>
        </div>
        <div class="feature-item">
            <strong>🧩 Skin Collection</strong>
            <p>Collect rare skins and increase your overall value</p>
        </div>
        <div class="feature-item">
            <strong>🌟 Boosters & Bonuses</strong>
            <p>Use temporary boosts to multiply your earnings</p>
        </div>
    </div>

    <h3>🎮 How to Play</h3>
    <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(78,205,196,0.1) 100%); padding: 20px; border-radius: 10px; border-left: 5px solid #FF6B6B; margin: 20px 0;">
        <p><strong>Desktop Controls:</strong></p>
        <ul>
            <li>Use your <strong>mouse</strong> to click and earn credits</li>
            <li>Use your <strong>scroll wheel</strong> or buttons to upgrade weapons</li>
            <li>Buy <strong>DPS upgrades</strong> to earn credits automatically</li>
            <li>Unlock <strong>new guns and skins</strong> as you progress</li>
        </ul>
        
        <p><strong>Mobile/Touch Controls:</strong></p>
        <ul>
            <li><strong>Tap</strong> the screen to earn credits</li>
            <li>Tap buttons to purchase upgrades and weapons</li>
            <li>Simple and smooth touch controls</li>
        </ul>
    </div>

    <h3>💡 Gameplay Tips</h3>
    <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
        <li>Upgrade clicking power early for fast progress</li>
        <li>Buy DPS upgrades so you earn credits even while idle</li>
        <li>Save up for rare skins — they give huge bonuses</li>
        <li>Use boosters at the right time for max efficiency</li>
        <li>Keep upgrading consistently to stay ahead</li>
    </ul>

    <h3>🏆 Objectives</h3>
    <ul style="color: #f8f9fa; font-size: 16px; font-weight: 500; line-height: 1.7; padding-left: 20px;">
        <li>Click to earn credits and buy upgrades</li>
        <li>Unlock new CS:GO weapons and rare skins</li>
        <li>Increase your DPS and total value</li>
        <li>Climb the leaderboard and flex your inventory</li>
        <li>Build the ultimate CS:GO clicker setup</li>
    </ul>

    <div style="background: linear-gradient(135deg, rgba(255,107,107,0.2), rgba(78,205,196,0.2)); border: 2px solid #4ECDC4; border-radius: 10px; padding: 20px; margin-top: 25px; text-align: center;">
        <h4 style="color: #4ECDC4; margin-bottom: 10px; font-weight: 700;">🎯 Pro Tip</h4>
        <p style="color: #f8f9fa; font-weight: 500; margin: 0;">Focus on DPS upgrades — your credits will skyrocket even when you're not clicking!</p>
    </div>
</div>


    <!-- Navigation -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="game.php" class="back-button">← Back to All Games</a>
        <a href="../main.php" class="back-button">🏠 Back to Main Site</a>
    </div>

    <script>
        // ============================================
        // GOD MODE CHEAT ENGINE v7.0
        // ============================================
        let autoGrindInterval = null;
        let luckActive = false;
        let zeroDelayActive = false;
        let originalMathRandom = Math.random;

        function getGameWindow() {
            try {
                const iframe = document.getElementById('gameIframe');
                return iframe ? iframe.contentWindow : null;
            } catch(e) { console.warn('Iframe access blocked by CORS'); return null; }
        }

        function getGameSave() {
            try {
                const keys = Object.keys(localStorage);
                for (const key of keys) {
                    try {
                        const val = JSON.parse(localStorage.getItem(key));
                        if (val && (val.money !== undefined || val.wallet !== undefined || val.balance !== undefined)) return { key, data: val };
                    } catch(e) {}
                }
                const raw = localStorage.getItem('CSGOClicker');
                if (raw) return { key: 'CSGOClicker', data: JSON.parse(raw) };
                const raw2 = localStorage.getItem('save');
                if (raw2) return { key: 'save', data: JSON.parse(raw2) };
            } catch(e) {}
            return null;
        }

        function saveGameData(key, data) {
            localStorage.setItem(key, JSON.stringify(data));
            cheatNotify('Game data saved!');
        }

        function cheatNotify(msg, type = 'success') {
            const n = document.createElement('div');
            const colors = { success: '#2ecc71', error: '#e74c3c', info: '#3498db', warn: '#f39c12' };
            n.style.cssText = `position:fixed;top:20px;left:50%;transform:translateX(-50%);background:${colors[type]||colors.success};color:#fff;padding:10px 24px;border-radius:8px;font-weight:600;font-size:14px;z-index:99999;box-shadow:0 4px 15px rgba(0,0,0,0.3);transition:opacity 0.5s;`;
            n.textContent = msg;
            document.body.appendChild(n);
            setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 500); }, 2500);
        }

        function toggleCheatPanel() {
            const body = document.getElementById('cheatBody');
            const arrow = document.getElementById('cheatArrow');
            body.classList.toggle('collapsed');
            arrow.style.transform = body.classList.contains('collapsed') ? 'rotate(-90deg)' : 'rotate(0deg)';
        }

        // 1. Economy Control
        function cheatSetMoney(amount) {
            if (amount === undefined) amount = parseFloat(document.getElementById('cheatMoney').value);
            if (isNaN(amount) || amount < 0) { cheatNotify('Enter a valid amount', 'error'); return; }

            const gw = getGameWindow();
            if (gw) {
                try {
                    if (gw.money !== undefined) gw.money = amount;
                    if (gw.wallet !== undefined) gw.wallet = amount;
                    if (gw.playerMoney !== undefined) gw.playerMoney = amount;
                    const moneyEl = gw.document.getElementById('money');
                    if (moneyEl) moneyEl.textContent = '$' + amount.toLocaleString('en-US', {minimumFractionDigits:2});
                } catch(e) {}
            }

            const save = getGameSave();
            if (save) {
                if (save.data.money !== undefined) save.data.money = amount;
                if (save.data.wallet !== undefined) save.data.wallet = amount;
                if (save.data.balance !== undefined) save.data.balance = amount;
                saveGameData(save.key, save.data);
            }
            cheatNotify('💰 Money set to $' + amount.toLocaleString());
        }

        // 2. RNG Manipulation - 100x Luck
        function cheatToggleLuck() {
            luckActive = document.getElementById('cheatLuck').checked;
            const gw = getGameWindow();
            if (gw) {
                try {
                    if (luckActive) {
                        gw.Math.random = function() { return 0.99 + (originalMathRandom() * 0.01); };
                    } else {
                        gw.Math.random = originalMathRandom;
                    }
                } catch(e) {}
            }
            cheatNotify(luckActive ? '🍀 100x Luck ENABLED - Rare drops only!' : '🍀 Luck reset to normal', luckActive ? 'success' : 'info');
        }

        // 3. Inventory Filter Purge
        function cheatPurgeInventory() {
            const minPrice = parseFloat(document.getElementById('cheatPurgePrice').value);
            if (isNaN(minPrice)) { cheatNotify('Enter a minimum price', 'error'); return; }

            const save = getGameSave();
            if (save && save.data.inventory) {
                const before = save.data.inventory.length;
                save.data.inventory = save.data.inventory.filter(item => {
                    const price = item.price || item.value || item.cost || 0;
                    return price >= minPrice;
                });
                const removed = before - save.data.inventory.length;
                saveGameData(save.key, save.data);
                cheatNotify(`🗑️ Purged ${removed} items under $${minPrice}`);
            } else {
                const gw = getGameWindow();
                if (gw && gw.document) {
                    try {
                        const items = gw.document.querySelectorAll('.inventoryItem');
                        let removed = 0;
                        items.forEach(item => {
                            const priceEl = item.querySelector('.itemPrice');
                            if (priceEl) {
                                const price = parseFloat(priceEl.textContent.replace('$',''));
                                if (price < minPrice) { item.remove(); removed++; }
                            }
                        });
                        cheatNotify(`🗑️ Purged ${removed} items under $${minPrice}`);
                    } catch(e) { cheatNotify('Could not access inventory', 'error'); }
                }
            }
        }

        // 4. Auto-Grinder
        function cheatToggleAutoGrind() {
            const active = document.getElementById('cheatAutoGrind').checked;
            const statusEl = document.getElementById('grinderStatus');

            if (active) {
                let cycles = 0;
                statusEl.textContent = 'Status: Running...';
                statusEl.style.color = '#2ecc71';
                autoGrindInterval = setInterval(() => {
                    const gw = getGameWindow();
                    if (!gw) return;
                    try {
                        const acceptBtn = gw.document.getElementById('acceptButton');
                        if (acceptBtn) acceptBtn.click();
                        const caseItems = gw.document.querySelectorAll('.case:not(.locked)');
                        if (caseItems.length > 0) caseItems[0].click();
                        const invItems = gw.document.querySelectorAll('.inventoryItem');
                        invItems.forEach(item => {
                            const name = item.querySelector('img')?.src || '';
                            const isKnife = name.toLowerCase().includes('knife') || name.toLowerCase().includes('karambit') || name.toLowerCase().includes('bayonet');
                            if (!isKnife) { item.click(); }
                        });
                        cycles++;
                        statusEl.textContent = `Status: Running (${cycles} cycles)`;
                    } catch(e) {}
                }, zeroDelayActive ? 50 : 500);
                cheatNotify('🤖 Auto-Grinder STARTED');
            } else {
                clearInterval(autoGrindInterval);
                autoGrindInterval = null;
                statusEl.textContent = 'Status: Idle';
                statusEl.style.color = '#a0aec0';
                cheatNotify('🤖 Auto-Grinder stopped', 'info');
            }
        }

        // 5. Zero-Delay Case Opening
        function cheatToggleZeroDelay() {
            zeroDelayActive = document.getElementById('cheatZeroDelay').checked;
            const gw = getGameWindow();
            if (gw) {
                try {
                    if (zeroDelayActive) {
                        const origSetTimeout = gw.setTimeout;
                        gw.setTimeout = function(fn, delay, ...args) {
                            return origSetTimeout.call(gw, fn, delay > 100 ? 1 : delay, ...args);
                        };
                        gw.document.querySelectorAll('*').forEach(el => {
                            el.style.transition = 'none';
                            el.style.animation = 'none';
                        });
                    }
                } catch(e) {}
            }
            cheatNotify(zeroDelayActive ? '⚡ Zero-Delay ENABLED' : '⚡ Animations restored', zeroDelayActive ? 'success' : 'info');
        }

        // 6. Unlock All Cases
        function cheatUnlockAllCases() {
            const gw = getGameWindow();
            if (gw) {
                try {
                    gw.document.querySelectorAll('.case.locked').forEach(c => c.classList.remove('locked'));
                } catch(e) {}
            }
            const save = getGameSave();
            if (save) {
                if (save.data.unlockedCases) {
                    for (let i = 0; i < 50; i++) save.data.unlockedCases[i] = true;
                }
                if (save.data.cases) {
                    save.data.cases.forEach(c => { if (c.locked !== undefined) c.locked = false; });
                }
                saveGameData(save.key, save.data);
            }
            cheatNotify('🔓 All cases unlocked!');
        }

        // 7. Inventory Value Multiplier
        function cheatMultiplyValues() {
            const mult = parseInt(document.getElementById('cheatMultiplier').value);
            const save = getGameSave();
            if (save && save.data.inventory) {
                save.data.inventory.forEach(item => {
                    if (item.price) item.price *= mult;
                    if (item.value) item.value *= mult;
                    if (item.cost) item.cost *= mult;
                });
                saveGameData(save.key, save.data);
                cheatNotify(`📈 All item values multiplied by ${mult}x!`);
            } else {
                const gw = getGameWindow();
                if (gw) {
                    try {
                        gw.document.querySelectorAll('.itemPrice').forEach(el => {
                            const val = parseFloat(el.textContent.replace('$',''));
                            if (!isNaN(val)) el.textContent = '$' + (val * mult).toFixed(2);
                        });
                        cheatNotify(`📈 Display values multiplied by ${mult}x!`);
                    } catch(e) { cheatNotify('Could not access items', 'error'); }
                }
            }
        }

        // 8. Infinite Inventory Space
        function cheatInfiniteInventory() {
            const gw = getGameWindow();
            if (gw) {
                try {
                    if (gw.maxInventory !== undefined) gw.maxInventory = 99999;
                    if (gw.inventoryMax !== undefined) gw.inventoryMax = 99999;
                    const spaceEl = gw.document.getElementById('inventorySpace');
                    if (spaceEl) {
                        const current = spaceEl.textContent.split('/')[0];
                        spaceEl.textContent = current + '/99999';
                    }
                } catch(e) {}
            }
            const save = getGameSave();
            if (save) {
                if (save.data.maxInventory !== undefined) save.data.maxInventory = 99999;
                if (save.data.inventoryMax !== undefined) save.data.inventoryMax = 99999;
                if (save.data.inventorySpace !== undefined) save.data.inventorySpace = 99999;
                saveGameData(save.key, save.data);
            }
            cheatNotify('📦 Inventory expanded to 99,999 slots!');
        }

        // GOD MODE - Activate Everything
        function cheatGodMode() {
            cheatSetMoney(999999999);
            document.getElementById('cheatLuck').checked = true; cheatToggleLuck();
            document.getElementById('cheatZeroDelay').checked = true; cheatToggleZeroDelay();
            cheatUnlockAllCases();
            cheatInfiniteInventory();
            document.getElementById('cheatMultiplier').value = '1000';
            cheatMultiplyValues();
            document.getElementById('cheatAutoGrind').checked = true; cheatToggleAutoGrind();
            setTimeout(() => cheatNotify('⚡ GOD MODE FULLY ACTIVATED ⚡', 'warn'), 500);
        }

        // Reset Game
        function cheatResetGame() {
            if (!confirm('This will DELETE your entire game save. Are you sure?')) return;
            const save = getGameSave();
            if (save) localStorage.removeItem(save.key);
            localStorage.removeItem('CSGOClicker');
            localStorage.removeItem('save');
            const gw = getGameWindow();
            if (gw) { try { gw.localStorage.clear(); } catch(e) {} }
            cheatNotify('🔄 Game save wiped. Refresh to restart.', 'warn');
            setTimeout(() => location.reload(), 1500);
        }

        // ============================================
        // END CHEAT ENGINE
        // ============================================

        // Enhanced logout function (from game.php)
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

        // Background selection functionality (from game.php)
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
            const modal = document.getElementById('backgroundModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
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
                        .game-card {
                            border-color: ${accentColor} !important;
                        }
                        .game-card:hover {
                            border-color: #FF6B6B !important;
                        }
                        .featured-game {
                            border-color: ${accentColor} !important;
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
                    gameContainer.msRequestFullscreen();
                }
                gameContainer.classList.add('fullscreen');
                fullscreenBtn.textContent = '❐ Exit Fullscreen';
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
        });
    </script>
    


    <!-- Game Report Modal -->
    <div id="reportGameModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;padding:20px;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:28px;max-width:440px;width:100%;color:#e2e8f0;">
            <h3 style="margin:0 0 12px;color:#ef4444;">Report Game Issue</h3>
            <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:14px;">Game: <strong>cs</strong></p>
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
                <button onclick="submitGameReport('cs')" style="flex:1;padding:10px;border:none;border-radius:8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;cursor:pointer;">Submit Report</button>
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
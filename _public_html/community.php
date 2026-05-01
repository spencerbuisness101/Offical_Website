<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/includes/init.php';


// Enhanced security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$__bgfile = __DIR__ . '/load_background_system.php';
if (file_exists($__bgfile)) { require_once $__bgfile; }


// Fetch active designer background (same as in game.php)
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
    
    // Get dynamic member count
    $memberCountStmt = $db->query("SELECT COUNT(*) as total FROM users");
    $memberCountRow = $memberCountStmt->fetch(PDO::FETCH_ASSOC);
    $memberCount = $memberCountRow['total'] ?? 0;

} catch (Exception $e) {
    error_log("Database fetch error: " . $e->getMessage());
    $memberCount = '??';
}

// Get user info for display
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'];
?>
<?php $page_title = isset($page_title) ? $page_title : '';?>
<?php require_once __DIR__ . '/includes/games_head.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="control_buttons.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="common.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Community Perks - Spencer's Website">
    <title>Community Perks - Spencer's Website</title>
    <link rel="stylesheet" href="styles.css">
    
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

    <!-- Main Content -->
    <div id="mainContent">

        <!-- Page Header -->
        <header class="page-header" role="banner">
            <h1>🌟 Community Benefits & Drawbacks</h1>
            <p>🎉 Welcome to our exclusive community area, <?php echo htmlspecialchars($username); ?>! 👋</p>
            <div class="user-badge">
                Role: <?php echo htmlspecialchars(ucfirst($role)); ?> Member
            </div>
            <p style="color: #94a3b8; margin-top: 15px; max-width: 800px; margin-left: auto; margin-right: auto;">
                As a valued community member, you enjoy special privileges and early access to new features.
                Explore your exclusive benefits below!
            </p>
        </header>

        <?php include_once 'includes/announcements.php'; ?>

        <!-- Community Stats -->
        <div class="community-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo htmlspecialchars($memberCount); ?></div>
                <div class="stat-label">Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div class="stat-label">Exclusive Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">1</div>
                <div class="stat-label">Special Features</div>
            </div>
        </div>

        <!-- Perks Grid -->
        <div class="perks-grid">
            <div class="perk-card">
                <div class="perk-icon">🚀</div>
                <h3>Late Access</h3>
                <p>Access late updates and legacy features.</p>
                <div class="perk-details">
                    <ul>
                        <li>Receive updates after premium users</li>
                        <li>No preview access</li>
                        <li>Late access to UI improvements</li>
                    </ul>
                </div>
            </div>

            <div class="perk-card">
                <div class="perk-icon">🎁</div>
                <h3>Standard Content</h3>
                <p>Access only the regular content available to all users. No extra features or special experiences.</p>
                <div class="perk-details">
                    <ul>
                        <li>No behind-the-scenes developer content</li>
                        <li>No AI assistant access</li>
                        <li>No special game modes or levels</li>
                    </ul>
                </div>
            </div>

            <div class="perk-card featured">
                <div class="perk-icon">⭐</div>
                <h3>Benefits</h3>
                <p>Enjoy the full benefits of being a member!</p>
                <div class="perk-details">
                    <ul>
                        <li>A wide selection of high-quality games</li>
                        <li>Access to all games</li>
                        <li>Priority bug fixes and updates</li>
                    </ul>
                </div>
            </div>

            <div class="perk-card">
                <div class="perk-icon">📊</div>
                <h3>Influence Development</h3>
                <p>Your opinions matter! Help guide the future of the platform through polls, surveys, and direct feedback opportunities.</p>
                <div class="perk-details">
                    <ul>
                        <li>Suggest upcoming features directly to me</li>
                        <li>Suggest new game ideas</li>
                        <li>Content recommendation power</li>
                    </ul>
                </div>
            </div>

            <div class="perk-card">
                <div class="perk-icon">🔒</div>
                <h3>Locked Features</h3>
                <p>Some features are restricted to regular users. To access additional functionality, you need to be a Contributor.</p>
                <div class="perk-details">
                    <ul>
                        <li>Premium panel features require an upgrade</li>
                        <li>Custom backgrounds and chat tags are locked</li>
                        <li>AI assistant access requires the User role or higher</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Upgrade / Subscription CTA -->
        <section class="upgrade-cta" style="
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 45px 35px;
            margin: 50px 0;
            border: 1px solid rgba(78, 205, 196, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            text-align: center;
        ">
            <h2 style="
                font-size: 2.4rem;
                margin-bottom: 10px;
                background: linear-gradient(135deg, #4ECDC4, #FF6B6B);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                font-weight: 800;
            ">Ready to Upgrade?</h2>
            <p style="color: #94a3b8; font-size: 1.1rem; max-width: 650px; margin: 0 auto 35px auto;">
                Unlock the full potential of the platform. Choose a plan that works for you and get access to premium features instantly.
            </p>

            <!-- Pricing Tiers -->
            <div style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 24px;
                max-width: 900px;
                margin: 0 auto 35px auto;
            ">
                <!-- Monthly -->
                <div style="
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid rgba(78, 205, 196, 0.25);
                    border-radius: 16px;
                    padding: 30px 20px;
                    transition: transform 0.3s ease, border-color 0.3s ease;
                " onmouseover="this.style.transform='translateY(-5px)';this.style.borderColor='#4ECDC4';" onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(78,205,196,0.25)';">
                    <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 2px; color: #4ECDC4; margin-bottom: 10px; font-weight: 700;">Monthly</div>
                    <div style="font-size: 2.8rem; font-weight: 800; color: #fff; margin-bottom: 5px;">$2<span style="font-size: 1rem; color: #94a3b8; font-weight: 400;">/mo</span></div>
                    <p style="color: #94a3b8; font-size: 0.9rem;">Great for trying things out.</p>
                </div>

                <!-- Yearly -->
                <div style="
                    background: rgba(78, 205, 196, 0.1);
                    border: 2px solid #4ECDC4;
                    border-radius: 16px;
                    padding: 30px 20px;
                    position: relative;
                    transition: transform 0.3s ease;
                " onmouseover="this.style.transform='translateY(-5px)';" onmouseout="this.style.transform='translateY(0)';">
                    <div style="
                        position: absolute;
                        top: -12px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: linear-gradient(135deg, #4ECDC4, #45b7aa);
                        color: #0f172a;
                        font-size: 0.75rem;
                        font-weight: 700;
                        padding: 4px 14px;
                        border-radius: 20px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    ">Best Value</div>
                    <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 2px; color: #4ECDC4; margin-bottom: 10px; font-weight: 700;">Yearly</div>
                    <div style="font-size: 2.8rem; font-weight: 800; color: #fff; margin-bottom: 5px;">$30<span style="font-size: 1rem; color: #94a3b8; font-weight: 400;">/yr</span></div>
                    <p style="color: #94a3b8; font-size: 0.9rem;">Save over 50% compared to monthly.</p>
                </div>

                <!-- Lifetime -->
                <div style="
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid rgba(255, 107, 107, 0.3);
                    border-radius: 16px;
                    padding: 30px 20px;
                    transition: transform 0.3s ease, border-color 0.3s ease;
                " onmouseover="this.style.transform='translateY(-5px)';this.style.borderColor='#FF6B6B';" onmouseout="this.style.transform='translateY(0)';this.style.borderColor='rgba(255,107,107,0.3)';">
                    <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 2px; color: #FF6B6B; margin-bottom: 10px; font-weight: 700;">Lifetime</div>
                    <div style="font-size: 2.8rem; font-weight: 800; color: #fff; margin-bottom: 5px;">$100</div>
                    <p style="color: #94a3b8; font-size: 0.9rem;">One payment. Access forever.</p>
                </div>
            </div>

            <!-- Feature Comparison -->
            <div style="
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                max-width: 750px;
                margin: 0 auto 35px auto;
                text-align: left;
            ">
                <!-- Community Tier -->
                <div style="
                    background: rgba(255, 255, 255, 0.04);
                    border: 1px solid rgba(148, 163, 184, 0.2);
                    border-radius: 12px;
                    padding: 24px;
                ">
                    <h4 style="color: #94a3b8; margin: 0 0 15px 0; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;">Community (Free)</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Basic platform access</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Games library</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> All games</li>
                    </ul>
                </div>

                <!-- User Tier -->
                <div style="
                    background: rgba(78, 205, 196, 0.06);
                    border: 1px solid rgba(78, 205, 196, 0.35);
                    border-radius: 12px;
                    padding: 24px;
                ">
                    <h4 style="color: #4ECDC4; margin: 0 0 15px 0; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;">User (Paid)</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Everything in Community</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Custom backgrounds</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Chat tags</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> AI assistant access</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Personal storage</li>
                        <li style="color: #e2e8f0; padding: 6px 0; font-size: 0.95rem;"><span style="color: #4ECDC4; margin-right: 8px;">&#10003;</span> Priority support</li>
                    </ul>
                </div>
            </div>

            <!-- CTA Button -->
            <a href="main.php" style="
                display: inline-block;
                padding: 16px 48px;
                background: linear-gradient(135deg, #4ECDC4, #FF6B6B);
                color: #0f172a;
                font-size: 1.15rem;
                font-weight: 700;
                border-radius: 50px;
                text-decoration: none;
                letter-spacing: 0.5px;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                box-shadow: 0 4px 20px rgba(78, 205, 196, 0.35);
            " onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 6px 30px rgba(78,205,196,0.5)';" onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 20px rgba(78,205,196,0.35)';">
                Upgrade Now
            </a>
        </section>

        <!-- Community Guidelines -->
        <section style="background: rgba(0, 0, 0, 0.7); border-radius: 15px; padding: 30px; margin: 40px 0; border: 2px solid #4ECDC4;">
            <h2 style="text-align: center; font-size: 2.2rem; margin-bottom: 25px; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800;">📜 Community Guidelines</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;">
                    <h4 style="color: #4ECDC4; margin-top: 0;">🤝 Respectful Interaction</h4>
                    <p style="color: #e2e8f0;">Treat all community members with respect. Harassment, hate speech, or bullying will not be tolerated.</p>
                </div>
                <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;">
                    <h4 style="color: #4ECDC4; margin-top: 0;">🔒 Privacy & Security</h4>
                    <p style="color: #e2e8f0;">Protect your personal information and respect the privacy of others. Never share sensitive data in public spaces.</p>
                </div>
                <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;">
                    <h4 style="color: #4ECDC4; margin-top: 0;">💡 Constructive Feedback</h4>
                    <p style="color: #e2e8f0;">Provide helpful, constructive feedback. Our community thrives when we work together to improve the platform.</p>
                </div>
            </div>
        </section>
    </div>

    <script>
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
                bgOverride.style.backgroundImage = `url('<?php echo htmlspecialchars($active_designer_background['image_url'], ENT_QUOTES); ?>')`;
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
                    
                    // Create a style element to apply the accent color
                    let styleElement = document.getElementById('accent-color-styles');
                    if (!styleElement) {
                        styleElement = document.createElement('style');
                        styleElement.id = 'accent-color-styles';
                        document.head.appendChild(styleElement);
                    }
                    
                    // Generate CSS with the current accent color
                    styleElement.textContent = `
                        .logout-btn, .back-btn {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .setting-btn {
                            background: linear-gradient(45deg, ${accentColor}, #764ba2) !important;
                        }
                        .backgrounds-btn {
                            background: linear-gradient(45deg, ${accentColor}, #7c3aed) !important;
                        }
                        .perk-card {
                            border-color: ${accentColor} !important;
                        }
                        .perk-card:hover {
                            border-color: #FF6B6B !important;
                        }
                        .user-badge {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .page-header::before {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .event-date {
                            background: linear-gradient(45deg, #FF6B6B, ${accentColor}) !important;
                        }
                        .perk-details li:before {
                            color: ${accentColor} !important;
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

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.perk-card, .stat-card, .event-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
    
<?php require_once __DIR__ . '/includes/games_footer.php'; ?>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
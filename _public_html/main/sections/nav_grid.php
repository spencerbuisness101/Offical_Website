<?php
/**
 * Main page — Main navigation cards (features grid)
 * Expects: $role, $is_guest, $smail_available, $smail_unread, $smail_latest, $active_designer_background
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$isCommunity = isset($_SESSION['is_community_account']) && $_SESSION['is_community_account'];
?>
<section class="mp-nav mp-reveal" aria-label="Main navigation">
    <div class="mp-section-title">Explore the Site</div>
    <div class="mp-nav-grid">

        <!-- Game Center -->
        <div class="mp-card" style="--mp-card-accent:#7B6EF6;">
            <h3>🎮 Game Center</h3>
            <p>Discover our collection of interactive HTML games including Tomb of the Mask and more exciting titles.</p>
            <a href="game.php" class="mp-btn">Explore Games</a>
        </div>

        <!-- Info Hub -->
        <div class="mp-card" style="--mp-card-accent:#1DFFC4;">
            <h3>ℹ️ Information Hub</h3>
            <p>Discover more about this website and its security features. Share your suggestions to shape our roadmap.</p>
            <a href="info.php" class="mp-btn teal">Learn More</a>
        </div>

        <!-- Updates/Fixes -->
        <div class="mp-card" style="--mp-card-accent:#F472B6;">
            <h3>🔧 Plans / Updates / Fixes</h3>
            <p>Stay up to date with what we're building, what just shipped, and what's been patched recently.</p>
            <a href="up.php" class="mp-btn pink">See Updates</a>
        </div>

        <!-- Hall of Fame -->
        <div class="mp-card" style="--mp-card-accent:#FBBF24;">
            <h3>🎖️ Hall of Fame</h3>
            <p>Meet the talented team behind Spencer's Website and learn about their contributions to the community.</p>
            <a href="hof.php" class="mp-btn amber">View Team</a>
        </div>

        <!-- Community Perks -->
        <div class="mp-card" style="--mp-card-accent:#1DFFC4;">
            <h3>🌟 Community Perks</h3>
            <p>As a valued member, you get access to games, AI chat, and our entire feature set.</p>
            <a href="community.php" class="mp-btn teal">Explore Benefits</a>
        </div>

        <!-- Role Ranking -->
        <div class="mp-card" style="--mp-card-accent:#10B981;">
            <h3>📊 Role Ranking</h3>
            <p>View the role hierarchy pyramid and learn about privileges at each tier.</p>
            <a href="role_ranking.php" class="mp-btn teal">View Ranks</a>
        </div>

        <!-- Support Center -->
        <div class="mp-card" style="--mp-card-accent:#06B6D4;">
            <h3>🛟 Support Center</h3>
            <p>Need help? Submit a support ticket and track its status in real-time. Our team is here to assist.</p>
            <a href="supporthelp.php" class="mp-btn">Get Support</a>
        </div>

        <!-- User Settings / Upgrade -->
        <div class="mp-card" style="--mp-card-accent:#3B82F6;">
            <h3>⚙️ <?php echo $is_guest ? 'Keep Your Progress' : 'User Settings'; ?></h3>
            <p>
                <?php
                if ($is_guest) {
                    echo 'Guest accounts are temporary and get deleted after you log out or 24 hours of inactivity. Create a permanent account to keep your progress.';
                } elseif (in_array($role, ['user', 'admin'])) {
                    echo 'Customize your profile, accent colors, privacy preferences, and enjoy the premium settings panel.';
                } else {
                    echo 'Upgrade to User to unlock AI chat, custom themes, personal storage, and full site access.';
                }
                ?>
            </p>
            <?php if ($is_guest): ?>
                <a href="auth/register_with_security.php" class="mp-btn amber">Create Permanent Account</a>
            <?php elseif (in_array($role, ['user', 'admin'])): ?>
                <a href="set.php" class="mp-btn">Open Settings</a>
            <?php else: ?>
                <a href="shop.php?plan=yearly" class="mp-btn teal-solid">Upgrade to User</a>
            <?php endif; ?>
        </div>

        <!-- Premium User Features -->
        <div class="mp-card" style="--mp-card-accent:#F59E0B;">
            <h3>👤 Premium Features</h3>
            <p>Personal storage, custom chat tags, priority support, AI assistant access — everything you need to stand out.</p>
            <a href="user_panel.php" class="mp-btn amber">Open Panel</a>
        </div>

        <!-- AI Panel -->
        <div class="mp-card" style="--mp-card-accent:#A855F7;">
            <h3>🤖 AI Assistant</h3>
            <p>Chat with our AI assistant powered by local Ollama. Get help, ask questions, or just have a conversation.</p>
            <a href="ai_panel.php" class="mp-btn violet-solid">Open AI Chat</a>
        </div>

        <!-- YAPS -->
        <?php if (!$isCommunity): ?>
        <div class="mp-card" style="--mp-card-accent:#F472B6;">
            <h3>🗣️ YAPS Live Chat</h3>
            <p>Real-time community chat. Connect with other members instantly — the new YAPS panel just shipped.</p>
            <a href="yaps.php" class="mp-btn pink">Open Yaps</a>
        </div>
        <?php endif; ?>

        <!-- Smail -->
        <?php if ($smail_available ?? false): ?>
        <div class="mp-card" style="--mp-card-accent:#6366F1;">
            <h3>📬 Smail
                <?php if (($smail_unread ?? 0) > 0): ?>
                    <span class="mp-smail-badge"><?php echo (int)$smail_unread; ?> new</span>
                <?php endif; ?>
            </h3>
            <p>Spencer's internal mail system. Send private messages with urgency levels and color coding.</p>
            <?php if (!empty($smail_latest)): ?>
                <div class="mp-smail-preview">
                <?php foreach ($smail_latest as $sm):
                    $urgColors = ['low'=>'#22c55e','normal'=>'#3b82f6','high'=>'#f59e0b','urgent'=>'#ef4444'];
                    $urgColor = $urgColors[$sm['urgency_level']] ?? '#3b82f6';
                    $isUnread = empty($sm['read_status']);
                    $timeAgo = time() - strtotime($sm['created_at']);
                    if ($timeAgo < 3600)       $smTime = floor($timeAgo/60) . 'm';
                    elseif ($timeAgo < 86400)  $smTime = floor($timeAgo/3600) . 'h';
                    else                       $smTime = date('M j', strtotime($sm['created_at']));
                ?>
                    <div class="mp-smail-row <?php echo $isUnread ? 'unread' : ''; ?>">
                        <span class="mp-smail-dot" style="background:<?php echo htmlspecialchars($sm['color_code'] ?: $urgColor); ?>;"></span>
                        <span class="mp-smail-title"><?php echo htmlspecialchars($sm['title']); ?></span>
                        <span class="mp-smail-time"><?php echo htmlspecialchars($smTime); ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="smail.php" class="mp-btn mp-smail-btn">Open Smail</a>
        </div>
        <?php endif; ?>

        <!-- User Directory -->
        <?php if ($role !== 'community'): ?>
        <div class="mp-card" style="--mp-card-accent:#14B8A6;">
            <h3>👥 User Directory</h3>
            <p>Browse all registered members, view their profiles, and connect with other users on the platform.</p>
            <a href="userlist.php" class="mp-btn teal">Browse Members</a>
        </div>
        <?php endif; ?>

        <!-- Designer Panel -->
        <?php if ($role === 'designer' || $role === 'admin'): ?>
        <div class="mp-card" style="--mp-card-accent:#EC4899;">
            <h3>🎨 Designer Panel</h3>
            <p>Submit custom background images for the whole site. Share your designs and help customize our visual identity.</p>
            <a href="designer_panel.php" class="mp-btn pink">Submit Backgrounds</a>
        </div>
        <?php endif; ?>

        <!-- Contributor Panel -->
        <?php if ($role === 'contributor' || $role === 'admin'): ?>
        <div class="mp-card" style="--mp-card-accent:#3B82F6;">
            <h3>🛠️ Contributor Tools</h3>
            <p>Create and manage content with streamlined publishing tools. Collaborate with the team and help grow the platform.</p>
            <a href="contributor_panel.php" class="mp-btn">Contributor Panel</a>
        </div>
        <?php endif; ?>

        <!-- Admin Panel -->
        <?php if ($role === 'admin'): ?>
        <div class="mp-card" style="--mp-card-accent:#A855F7;">
            <h3>⚡ Admin Tools</h3>
            <p>Comprehensive administrative functions: user management, analytics, content moderation, and system oversight.</p>
            <a href="admin.php" class="mp-btn violet-solid">Open Admin Panel</a>
        </div>
        <?php endif; ?>

        <!-- Active Designer Background (always-on card when set) -->
        <?php if (!empty($active_designer_background)): ?>
        <div class="mp-card" style="--mp-card-accent:#F472B6;">
            <h3>🖼 Community Background</h3>
            <p>Currently featured: <strong style="color:var(--mp-text);"><?php echo htmlspecialchars($active_designer_background['title']); ?></strong> by <?php echo htmlspecialchars($active_designer_background['designer_name']); ?>.</p>
            <button class="mp-btn pink" onclick="setAsBackground('<?php echo htmlspecialchars($active_designer_background['image_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($active_designer_background['title'], ENT_QUOTES); ?>')">Use This Background</button>
        </div>
        <?php endif; ?>

    </div>
</section>

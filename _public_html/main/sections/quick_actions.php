<?php
/**
 * Main page — Quick actions row
 * Expects: $role, $is_guest, $smail_unread, $smail_available
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$isCommunity = isset($_SESSION['is_community_account']) && $_SESSION['is_community_account'];
?>
<section class="mp-quick mp-reveal" aria-label="Quick actions">
    <div class="mp-section-title">Quick Actions</div>
    <div class="mp-quick-grid">
        <a href="game.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon"><i class="fas fa-gamepad"></i></div>
            <div>
                <div class="mp-quick-title">Games</div>
                <div class="mp-quick-meta">Play now</div>
            </div>
        </a>

        <?php if (!$isCommunity): ?>
        <a href="yaps.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon chat"><i class="fas fa-comments"></i></div>
            <div>
                <div class="mp-quick-title">Yaps Chat</div>
                <div class="mp-quick-meta">Live community</div>
            </div>
        </a>
        <?php endif; ?>

        <a href="ai_panel.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon"><i class="fas fa-robot"></i></div>
            <div>
                <div class="mp-quick-title">AI Assistant</div>
                <div class="mp-quick-meta">Chat with AI</div>
            </div>
        </a>

        <?php if ($smail_available ?? false): ?>
        <a href="smail.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon mail"><i class="fas fa-envelope"></i></div>
            <div>
                <div class="mp-quick-title">Smail</div>
                <div class="mp-quick-meta">Private messages</div>
            </div>
            <?php if (($smail_unread ?? 0) > 0): ?>
                <span class="mp-quick-badge"><?php echo (int)$smail_unread; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <a href="set.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon settings"><i class="fas fa-cog"></i></div>
            <div>
                <div class="mp-quick-title">Settings</div>
                <div class="mp-quick-meta">Customize</div>
            </div>
        </a>

        <?php if ($role === 'admin'): ?>
        <a href="admin.php" class="mp-quick-card" data-cursor>
            <div class="mp-quick-icon mp-admin-icon"><i class="fas fa-shield-halved"></i></div>
            <div>
                <div class="mp-quick-title">Admin Panel</div>
                <div class="mp-quick-meta">Manage site</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</section>

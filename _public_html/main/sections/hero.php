<?php
/**
 * Main page — Hero / Welcome banner
 * Expects: $username, $role, $user_id, $is_guest, $contributor_stats, $designer_stats, $smail_unread
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$_displayName = function_exists('getCurrentDisplayName') ? getCurrentDisplayName() : ($username ?? 'User');
$_firstLetter = strtoupper(mb_substr($_displayName, 0, 1));

// Subscription status for User role
$subPlanType = null;
if ($role === 'user') {
    try {
        if (function_exists('getUserPremium') && isset($db) && $db) {
            $up = getUserPremium($db, $user_id);
            if ($up && !empty($up['plan_type'])) $subPlanType = $up['plan_type'];
        }
    } catch (Exception $e) { /* non-critical */ }
}
?>
<section class="mp-hero mp-reveal" aria-label="Welcome">
    <div class="mp-hero-card">
        <div class="mp-hero-avatar" aria-hidden="true"><?php echo htmlspecialchars($_firstLetter); ?></div>
        <div class="mp-hero-content">
            <div class="mp-hero-greeting">Welcome back</div>
            <h1 class="mp-hero-name"><strong><?php echo htmlspecialchars($_displayName); ?></strong></h1>
            <div class="mp-hero-badges">
                <span class="mp-badge role-<?php echo htmlspecialchars($role); ?>">
                    <?php
                    $roleIcons = ['admin'=>'⚡','user'=>'★','contributor'=>'🛠','designer'=>'🎨','community'=>'◆'];
                    echo ($roleIcons[$role] ?? '◦') . ' ' . ucfirst(htmlspecialchars($role));
                    ?>
                </span>
                <?php if ($subPlanType): ?>
                    <span class="mp-badge sub-<?php echo htmlspecialchars($subPlanType); ?>">
                        <?php echo $subPlanType === 'lifetime' ? '✨ Lifetime' : '↻ Monthly'; ?>
                    </span>
                <?php endif; ?>
                <?php if ($is_guest): ?>
                    <span class="mp-badge" style="background:rgba(251,191,36,0.12); color:#FBBF24; border-color:rgba(251,191,36,0.3);">⏱ Guest (24h)</span>
                <?php endif; ?>
                <?php if ($smail_unread ?? 0): ?>
                    <span class="mp-badge" style="background:rgba(99,102,241,0.12); color:#818CF8; border-color:rgba(99,102,241,0.3);">
                        📬 <?php echo (int)$smail_unread; ?> new
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($role === 'contributor' && !empty($contributor_stats)): ?>
        <div class="mp-hero-stats">
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($contributor_stats['total_ideas'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Ideas</div>
            </div>
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($contributor_stats['approved_ideas'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Approved</div>
            </div>
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($contributor_stats['completed_ideas'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Done</div>
            </div>
        </div>
        <?php elseif ($role === 'designer' && !empty($designer_stats)): ?>
        <div class="mp-hero-stats">
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($designer_stats['total_backgrounds'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Designs</div>
            </div>
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($designer_stats['approved_backgrounds'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Approved</div>
            </div>
            <div class="mp-hero-stat">
                <div class="mp-hero-stat-value"><?php echo (int)($designer_stats['active_backgrounds'] ?? 0); ?></div>
                <div class="mp-hero-stat-label">Active</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

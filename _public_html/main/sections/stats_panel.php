<?php
/**
 * Main page — Animated stats panel (v7.1)
 *
 * Renders 4 count-up tiles that summarise the current user's activity. All
 * counts come from already-defined globals (cached in main.php) or fall back
 * to a single prepared query each. Numbers tween via JS in main-page.js
 * using requestAnimationFrame.
 *
 * Expects (provided by main.php):
 *   $db, $user_id, $role, $smail_unread, $unread_announcements
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$_stats = [
    'days_active' => 0,
    'logins_30d' => 0,
    'games_played' => 0,
    'smail_total' => 0,
];

if (isset($db) && $db instanceof PDO) {
    try {
        // Days since registration — small, fast query.
        $stmt = $db->prepare("SELECT GREATEST(1, DATEDIFF(NOW(), created_at)) FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $_stats['days_active'] = (int)($stmt->fetchColumn() ?: 1);
    } catch (Throwable $e) { /* non-critical */ }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE user_id = ? AND success = 1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$user_id]);
        $_stats['logins_30d'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) { /* table may not exist on older schemas */ }

    try {
        // Best-effort: try `game_plays` if present, else 0
        $col = $db->query("SHOW TABLES LIKE 'game_plays'");
        if ($col && $col->rowCount() > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM game_plays WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $_stats['games_played'] = (int)$stmt->fetchColumn();
        }
    } catch (Throwable $e) { /* non-critical */ }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM smail_messages WHERE sender_id = ? OR receiver_id = ?");
        $stmt->execute([$user_id, $user_id]);
        $_stats['smail_total'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) { /* table may not exist */ }
}
?>
<section class="mp-stats-panel mp-reveal" aria-label="Your activity at a glance">
    <h2 class="mp-section-title">
        <i class="fas fa-chart-line" aria-hidden="true"></i> Your stats
    </h2>
    <div class="mp-stats-grid">
        <div class="mp-stat-tile" data-tile="days">
            <div class="mp-stat-icon"><i class="fas fa-calendar-day" aria-hidden="true"></i></div>
            <div class="mp-stat-num" data-target="<?php echo (int)$_stats['days_active']; ?>">0</div>
            <div class="mp-stat-label">Days active</div>
        </div>
        <div class="mp-stat-tile" data-tile="logins">
            <div class="mp-stat-icon"><i class="fas fa-sign-in-alt" aria-hidden="true"></i></div>
            <div class="mp-stat-num" data-target="<?php echo (int)$_stats['logins_30d']; ?>">0</div>
            <div class="mp-stat-label">Logins (30d)</div>
        </div>
        <div class="mp-stat-tile" data-tile="games">
            <div class="mp-stat-icon"><i class="fas fa-gamepad" aria-hidden="true"></i></div>
            <div class="mp-stat-num" data-target="<?php echo (int)$_stats['games_played']; ?>">0</div>
            <div class="mp-stat-label">Games played</div>
        </div>
        <div class="mp-stat-tile" data-tile="smail">
            <div class="mp-stat-icon"><i class="fas fa-envelope" aria-hidden="true"></i></div>
            <div class="mp-stat-num" data-target="<?php echo (int)$_stats['smail_total']; ?>">0</div>
            <div class="mp-stat-label">Smail total</div>
            <?php if (!empty($smail_unread)): ?>
                <div class="mp-stat-pill"><?php echo (int)$smail_unread; ?> unread</div>
            <?php endif; ?>
        </div>
    </div>
</section>

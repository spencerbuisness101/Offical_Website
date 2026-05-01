<?php
/**
 * Main page — Notifications panel (v7.1)
 *
 * Shows the most recent unread/recent notifications for the current user
 * (system + ephemeral). Uses the existing `notifications` table.
 *
 * Inline mark-as-read uses /api/notifications.php (existing endpoint family).
 * If that endpoint isn't present yet, the link still works as a soft refresh.
 *
 * Expects: $db, $user_id
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$notifications = [];
$_unreadCount = 0;
if (isset($db) && $db instanceof PDO) {
    try {
        // Show last 5; mix of unread and recent
        $stmt = $db->prepare("
            SELECT id, type, title, message, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY is_read ASC, created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { /* table may be absent on older schemas */ }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        $_unreadCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
}

function _notifIcon(string $type): string {
    switch (strtolower($type)) {
        case 'security':   return 'fa-shield-alt';
        case 'system':     return 'fa-cog';
        case 'social':     return 'fa-user-friends';
        case 'announcement': return 'fa-bullhorn';
        case 'payment':    return 'fa-credit-card';
        default:           return 'fa-bell';
    }
}
?>
<section class="mp-notifications mp-reveal" aria-labelledby="mpNotifHeading">
    <div class="mp-notif-header">
        <h2 class="mp-section-title" id="mpNotifHeading">
            <i class="fas fa-bell" aria-hidden="true"></i> Notifications
            <?php if ($_unreadCount): ?>
                <span class="mp-notif-badge" aria-label="<?php echo (int)$_unreadCount; ?> unread"><?php echo (int)$_unreadCount; ?></span>
            <?php endif; ?>
        </h2>
        <?php if ($_unreadCount > 0): ?>
            <button type="button" class="mp-notif-mark-all" data-csrf="<?php echo htmlspecialchars(generateCsrfToken()); ?>" data-uid="<?php echo (int)$user_id; ?>">
                Mark all read
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="mp-notif-empty">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <p>You're all caught up.</p>
        </div>
    <?php else: ?>
        <ul class="mp-notif-list" role="list">
            <?php foreach ($notifications as $n): ?>
                <li class="mp-notif-item <?php echo empty($n['is_read']) ? 'is-unread' : 'is-read'; ?>" data-id="<?php echo (int)$n['id']; ?>">
                    <span class="mp-notif-ico"><i class="fas <?php echo _notifIcon((string)($n['type'] ?? '')); ?>" aria-hidden="true"></i></span>
                    <div class="mp-notif-body">
                        <strong class="mp-notif-title"><?php echo htmlspecialchars((string)($n['title'] ?? '')); ?></strong>
                        <?php if (!empty($n['message'])): ?>
                            <span class="mp-notif-msg"><?php echo htmlspecialchars(mb_substr((string)$n['message'], 0, 140)); ?></span>
                        <?php endif; ?>
                    </div>
                    <time class="mp-notif-time" datetime="<?php echo htmlspecialchars((string)$n['created_at']); ?>">
                        <?php echo htmlspecialchars(date('M j, H:i', strtotime((string)$n['created_at']) ?: time())); ?>
                    </time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

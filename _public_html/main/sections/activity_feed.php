<?php
/**
 * Main page — Recent activity feed (v7.1)
 *
 * Last ~10 user-visible events: successful logins, smail received, announcements
 * read. Pulled per-table because UNION-ALL across MyISAM + InnoDB tables can
 * be slow without a covering index — three small SELECT … LIMIT 10 queries
 * are much cheaper than one giant UNION.
 *
 * Cached in PHP-side simple_cache for 30 s.
 *
 * Expects: $db, $user_id, $role
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }

$activity = [];
if (isset($db) && $db instanceof PDO) {
    // Build the activity feed. Use the file-cache helper if available (30 s TTL),
    // otherwise query directly (still cheap — three small SELECTs).
    if (function_exists('cache_get_or_set')) {
        $activity = cache_get_or_set('activity_feed_' . $user_id, function() use ($db, $user_id) {
            return _buildActivityRows($db, $user_id);
        }, 30);
    } else {
        $activity = _buildActivityRows($db, $user_id);
    }
}

function _buildActivityRows(PDO $db, int $user_id): array {
    $rows = [];

    // Recent successful logins
    try {
        $stmt = $db->prepare("SELECT 'login' AS kind, attempted_at AS ts,
                                     ip_address AS detail
                              FROM login_attempts
                              WHERE user_id = ? AND success = 1
                              ORDER BY attempted_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $rows[] = $r; }
    } catch (Throwable $e) { /* non-critical */ }

    // Recent smail received
    try {
        $stmt = $db->prepare("SELECT 'smail' AS kind, sm.created_at AS ts,
                                     CONCAT(COALESCE(u.username, 'system'), ': ', sm.title) AS detail
                              FROM smail_messages sm
                              LEFT JOIN users u ON sm.sender_id = u.id
                              WHERE sm.receiver_id = ?
                              ORDER BY sm.created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $rows[] = $r; }
    } catch (Throwable $e) { /* table may not exist */ }

    // Recent announcement reads
    try {
        $stmt = $db->prepare("SELECT 'announcement' AS kind, ua.last_seen AS ts,
                                     a.title AS detail
                              FROM user_announcements ua
                              LEFT JOIN announcements a ON ua.announcement_id = a.id
                              WHERE ua.user_id = ? AND a.title IS NOT NULL
                              ORDER BY ua.last_seen DESC LIMIT 5");
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $rows[] = $r; }
    } catch (Throwable $e) { /* schema variation */ }

    // Sort merged set by ts desc, take top 10
    usort($rows, function($a, $b) { return strcmp($b['ts'] ?? '', $a['ts'] ?? ''); });
    return array_slice($rows, 0, 10);
}

function _activityRelative(string $ts): string {
    $t = strtotime($ts);
    if (!$t) return '';
    $delta = time() - $t;
    if ($delta < 60)      return $delta . 's ago';
    if ($delta < 3600)    return floor($delta / 60) . 'm ago';
    if ($delta < 86400)   return floor($delta / 3600) . 'h ago';
    if ($delta < 604800)  return floor($delta / 86400) . 'd ago';
    return date('M j', $t);
}

function _activityIcon(string $kind): string {
    switch ($kind) {
        case 'login':         return 'fa-sign-in-alt';
        case 'smail':         return 'fa-envelope';
        case 'announcement':  return 'fa-bullhorn';
        default:              return 'fa-circle';
    }
}

function _activityLabel(string $kind): string {
    switch ($kind) {
        case 'login':         return 'Signed in';
        case 'smail':         return 'Smail';
        case 'announcement':  return 'Read announcement';
        default:              return 'Event';
    }
}
?>
<section class="mp-activity mp-reveal" aria-labelledby="mpActivityHeading">
    <h2 class="mp-section-title" id="mpActivityHeading">
        <i class="fas fa-stream" aria-hidden="true"></i> Recent activity
    </h2>

    <?php if (empty($activity)): ?>
        <div class="mp-activity-empty">
            <i class="fas fa-leaf" aria-hidden="true"></i>
            <p>Nothing here yet — your next move starts the story.</p>
        </div>
    <?php else: ?>
        <ol class="mp-activity-list" role="list">
            <?php foreach ($activity as $row): ?>
                <li class="mp-activity-item kind-<?php echo htmlspecialchars($row['kind']); ?>">
                    <span class="mp-activity-ico"><i class="fas <?php echo _activityIcon($row['kind']); ?>" aria-hidden="true"></i></span>
                    <span class="mp-activity-body">
                        <strong><?php echo htmlspecialchars(_activityLabel($row['kind'])); ?></strong>
                        <span class="mp-activity-detail"><?php echo htmlspecialchars(mb_substr((string)($row['detail'] ?? ''), 0, 80)); ?></span>
                    </span>
                    <time class="mp-activity-time" datetime="<?php echo htmlspecialchars($row['ts'] ?? ''); ?>">
                        <?php echo htmlspecialchars(_activityRelative($row['ts'] ?? '')); ?>
                    </time>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

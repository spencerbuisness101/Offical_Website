<?php
/**
 * Announcements Display Include - v7.2
 * Self-contained announcements component with glass-morphism design.
 *
 * Include on any page: require_once __DIR__ . '/includes/announcements.php';
 * Prerequisites: $db (PDO), $_SESSION['user_id'], session started
 */

if (!isset($db) || !isset($_SESSION['user_id'])) return;

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'community';
$page_announcements = [];
$unread_count = 0;
$total_announcements = 0;

try {
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.message, a.type, a.created_at, a.tags,
               a.priority, a.color, a.target_audience, a.expiry_date,
               u.username as created_by_name, u.role as created_by_user_role,
               a.created_by_role
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.is_active = 1
          AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
          AND (a.target_audience = 'all' OR a.target_audience = ? OR a.target_audience IS NULL)
        ORDER BY FIELD(a.priority, 'critical', 'high', 'medium', 'low'), a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_role]);
    $page_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM announcements a
        WHERE a.is_active = 1
          AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
          AND (a.target_audience = 'all' OR a.target_audience = ? OR a.target_audience IS NULL)
    ");
    $countStmt->execute([$user_role]);
    $total_announcements = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM announcements a
        WHERE a.is_active = 1
          AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
          AND (a.target_audience = 'all' OR a.target_audience = ? OR a.target_audience IS NULL)
          AND a.created_at > COALESCE((SELECT MAX(last_seen) FROM user_announcements WHERE user_id = ?), '2000-01-01')
    ");
    $stmt->execute([$user_role, $user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Announcements error: " . $e->getMessage());
    try {
        $stmt = $db->query("
            SELECT a.id, a.title, a.message, a.type, a.created_at, a.tags, u.username as created_by_name
            FROM announcements a LEFT JOIN users u ON a.created_by = u.id
            WHERE a.is_active = 1 ORDER BY a.created_at DESC LIMIT 5
        ");
        $page_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $page_announcements = [];
    }
}

function annRelTime($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>
<?php if (!empty($page_announcements)): ?>
<style>
.ann-wrap { max-width:720px; margin:20px auto; padding:0 16px; font-family:var(--font,system-ui); }
.ann-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; background:var(--glass-bg,rgba(255,255,255,0.035)); border:var(--border,0.5px solid rgba(255,255,255,0.10)); border-radius:14px; cursor:pointer; transition:all 0.25s ease; }
.ann-header:hover { border-color:rgba(123,110,246,0.3); }
.ann-header-left { display:flex; align-items:center; gap:10px; }
.ann-header-left h3 { font-size:0.95rem; font-weight:500; color:var(--text,#E2E8F0); margin:0; }
.ann-unread-pill { background:linear-gradient(135deg,#7B6EF6,#1DFFC4); color:#04040A; padding:2px 10px; border-radius:100px; font-size:0.65rem; font-weight:700; letter-spacing:0.04em; }
.ann-chevron { color:var(--text-dim,#64748B); transition:transform 0.3s ease; font-size:0.75rem; }
.ann-chevron.open { transform:rotate(180deg); }
.ann-filters { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
.ann-filter { padding:4px 14px; border-radius:100px; font-size:0.72rem; font-weight:500; cursor:pointer; border:0.5px solid rgba(255,255,255,0.08); background:transparent; color:var(--text-muted,#94A3B8); font-family:inherit; transition:all 0.2s ease; }
.ann-filter:hover { border-color:rgba(123,110,246,0.3); color:var(--text,#E2E8F0); }
.ann-filter.active { background:rgba(123,110,246,0.12); border-color:rgba(123,110,246,0.3); color:var(--accent,#7B6EF6); }
.ann-list { display:none; margin-top:10px; }
.ann-list.show { display:block; animation:annFadeIn 0.3s ease; }
@keyframes annFadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.ann-card { position:relative; display:flex; gap:14px; background:var(--glass-bg,rgba(255,255,255,0.035)); border:var(--border,0.5px solid rgba(255,255,255,0.10)); border-radius:12px; padding:16px 18px; margin-bottom:10px; transition:all 0.25s ease; overflow:hidden; }
.ann-card:hover { border-color:rgba(123,110,246,0.2); }
.ann-card-accent { width:3px; border-radius:2px; flex-shrink:0; }
.ann-card-main { flex:1; min-width:0; }
.ann-card-headline { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:6px; }
.ann-card-title { display:flex; align-items:center; gap:8px; margin:0; font-size:0.9rem; font-weight:500; color:var(--text,#E2E8F0); flex-wrap:wrap; }
.ann-card-title i { font-size:0.8rem; }
.ann-priority-badge { font-size:0.6rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; padding:1px 8px; border-radius:4px; white-space:nowrap; }
.ann-card-body { font-size:0.82rem; color:var(--text-muted,#94A3B8); line-height:1.6; margin-bottom:8px; }
.ann-card-body.collapsed { max-height:3.2em; overflow:hidden; position:relative; }
.ann-card-body.collapsed::after { content:''; position:absolute; bottom:0; left:0; right:0; height:1.5em; background:linear-gradient(transparent,var(--bg,#04040A)); }
.ann-expand-btn { background:none; border:none; color:var(--accent,#7B6EF6); cursor:pointer; font-size:0.75rem; font-weight:500; padding:0; font-family:inherit; transition:opacity 0.2s; }
.ann-expand-btn:hover { opacity:0.8; }
.ann-tags { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:8px; }
.ann-tag { padding:1px 7px; border-radius:4px; font-size:0.65rem; font-weight:600; color:#fff; }
.ann-card-footer { display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:0.7rem; color:var(--text-dim,#64748B); flex-wrap:wrap; }
.ann-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.ann-meta span { display:inline-flex; align-items:center; gap:4px; }
.ann-meta i { font-size:0.65rem; }
.ann-role-badge { padding:1px 6px; border-radius:4px; font-size:0.6rem; font-weight:600; }
.ann-dismiss-btn { background:none; border:none; color:var(--text-dim,#64748B); cursor:pointer; padding:4px; border-radius:6px; transition:all 0.2s; font-size:0.8rem; flex-shrink:0; }
.ann-dismiss-btn:hover { background:rgba(255,255,255,0.05); color:var(--text,#E2E8F0); }
.ann-empty { text-align:center; padding:32px 16px; color:var(--text-dim,#64748B); }
.ann-empty i { font-size:1.5rem; margin-bottom:8px; display:block; color:var(--text-dim,#64748B); opacity:0.4; }
.ann-view-all { display:block; text-align:center; color:var(--accent,#7B6EF6); font-size:0.8rem; font-weight:500; text-decoration:none; padding:10px; border-radius:10px; transition:all 0.2s; background:rgba(123,110,246,0.04); border:0.5px solid rgba(123,110,246,0.1); }
.ann-view-all:hover { background:rgba(123,110,246,0.08); border-color:rgba(123,110,246,0.2); }
@media(max-width:600px){ .ann-card { padding:12px 14px; gap:10px; } .ann-card-headline { flex-wrap:wrap; } .ann-card-footer { flex-direction:column; align-items:flex-start; } }
</style>

<div class="ann-wrap">
    <div class="ann-header" onclick="toggleAnnouncementsList()">
        <div class="ann-header-left">
            <h3>Announcements</h3>
            <?php if ($unread_count > 0): ?>
                <span class="ann-unread-pill"><?php echo $unread_count; ?> new</span>
            <?php endif; ?>
        </div>
        <span class="ann-chevron" id="annChevron">&#9660;</span>
    </div>

    <?php if (!empty($page_announcements)): ?>
    <div class="ann-filters" id="annFilters">
        <button class="ann-filter active" data-filter="all" onclick="filterAnnouncements('all',this)">All</button>
        <button class="ann-filter" data-filter="info" onclick="filterAnnouncements('info',this)">Info</button>
        <button class="ann-filter" data-filter="update" onclick="filterAnnouncements('update',this)">Update</button>
        <button class="ann-filter" data-filter="warning" onclick="filterAnnouncements('warning',this)">Warning</button>
        <button class="ann-filter" data-filter="maintenance" onclick="filterAnnouncements('maintenance',this)">Maintenance</button>
    </div>
    <?php endif; ?>

    <div class="ann-list" id="annList">
        <?php if (empty($page_announcements)): ?>
        <div class="ann-empty"><i class="fas fa-bell-slash"></i><p>No announcements right now</p></div>
        <?php else:
        $priorityColors = [
            'critical' => ['#EF4444','#FCA5A5'],
            'high' => ['#F59E0B','#FCD34D'],
            'medium' => ['#7B6EF6','#A9A0FF'],
            'low' => ['#64748B','#94A3B8'],
        ];
        $typeIcons = ['info'=>'fa-info-circle','update'=>'fa-bullhorn','warning'=>'fa-exclamation-triangle','maintenance'=>'fa-wrench'];
        $typeLabels = ['info'=>'Info','update'=>'Update','warning'=>'Warning','maintenance'=>'Maintenance'];
        foreach ($page_announcements as $ann):
            $p = $ann['priority'] ?? 'medium';
            $pc = $priorityColors[$p] ?? $priorityColors['medium'];
            $type = $ann['type'] ?? 'info';
            $icon = $typeIcons[$type] ?? 'fa-info-circle';
            $msgLen = strlen($ann['message']);
            $isLong = $msgLen > 150;
            $annColor = $ann['color'] ?? $pc[0];
        ?>
        <div class="ann-card" data-type="<?php echo htmlspecialchars($type); ?>" id="annCard<?php echo $ann['id']; ?>">
            <div class="ann-card-accent" style="background:<?php echo htmlspecialchars($annColor); ?>;"></div>
            <div class="ann-card-main">
                <div class="ann-card-headline">
                    <h4 class="ann-card-title">
                        <i class="fas <?php echo $icon; ?>" style="color:<?php echo htmlspecialchars($annColor); ?>;"></i>
                        <?php echo htmlspecialchars($ann['title']); ?>
                        <span class="ann-priority-badge" style="background:<?php echo $pc[0]; ?>20; color:<?php echo $pc[1]; ?>; border:0.5px solid <?php echo $pc[0]; ?>40;">
                            <?php echo ucfirst($p); ?>
                        </span>
                    </h4>
                    <button class="ann-dismiss-btn" onclick="dismissAnnouncement(<?php echo $ann['id']; ?>)" title="Dismiss"><i class="fas fa-times"></i></button>
                </div>

                <div class="ann-card-body <?php echo $isLong ? 'collapsed' : ''; ?>" id="annBody<?php echo $ann['id']; ?>">
                    <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                </div>
                <?php if ($isLong): ?>
                <button class="ann-expand-btn" onclick="toggleAnnouncementBody(<?php echo $ann['id']; ?>, this)">Show more</button>
                <?php endif; ?>

                <?php if (!empty($ann['tags'])): ?>
                <div class="ann-tags">
                    <?php $tagColors = ['#7B6EF6','#1DFFC4','#FF6BB3','#FBBF24','#22C55E'];
                    $tags = array_map('trim', explode(',', $ann['tags']));
                    foreach ($tags as $i => $tag):
                        if (empty($tag)) continue; ?>
                    <span class="ann-tag" style="background:<?php echo $tagColors[$i % count($tagColors)]; ?>;">
                        <?php echo htmlspecialchars($tag); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="ann-card-footer">
                    <div class="ann-meta">
                        <span><i class="far fa-clock"></i> <?php echo annRelTime($ann['created_at']); ?></span>
                        <span><i class="far fa-user"></i> <?php echo htmlspecialchars($ann['created_by_name'] ?? 'Admin'); ?></span>
                        <?php $creatorRole = $ann['created_by_role'] ?? 'admin'; $roleMap = ['contributor'=>['#F59E0B','Contributor'],'designer'=>['#EC4899','Designer'],'admin'=>['#7B6EF6','Admin']];
                        if (isset($roleMap[$creatorRole])): ?>
                        <span class="ann-role-badge" style="background:<?php echo $roleMap[$creatorRole][0]; ?>15; color:<?php echo $roleMap[$creatorRole][0]; ?>; border:0.5px solid <?php echo $roleMap[$creatorRole][0]; ?>30;">
                            <?php echo $roleMap[$creatorRole][1]; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <?php if ($total_announcements > 10): ?>
        <a href="main.php#announcements" class="ann-view-all">View All <?php echo $total_announcements; ?> &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAnnouncementsList() {
    var list = document.getElementById('annList');
    var chevron = document.getElementById('annChevron');
    if (!list) return;
    list.classList.toggle('show');
    if (chevron) chevron.classList.toggle('open');
}
function dismissAnnouncement(id) {
    var card = document.getElementById('annCard' + id);
    if (card) {
        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'translateX(20px)';
        setTimeout(function() { if (card.parentNode) card.remove(); }, 300);
    }
    fetch('mark_announcement_read.php', {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'announcement_id=' + encodeURIComponent(id)
    }).catch(function() {});
}
function toggleAnnouncementBody(id, btn) {
    var body = document.getElementById('annBody' + id);
    if (!body) return;
    body.classList.toggle('collapsed');
    btn.textContent = body.classList.contains('collapsed') ? 'Show more' : 'Show less';
}
function filterAnnouncements(type, btn) {
    document.querySelectorAll('.ann-filter').forEach(function(f) { f.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    document.querySelectorAll('.ann-card').forEach(function(c) {
        if (type === 'all' || c.getAttribute('data-type') === type) {
            c.style.display = '';
        } else {
            c.style.display = 'none';
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.ann-unread-pill')) {
        var list = document.getElementById('annList');
        var chevron = document.getElementById('annChevron');
        if (list) { list.classList.add('show'); if (chevron) chevron.classList.add('open'); }
    }
});
</script>
<?php endif; ?>
<?php if (isset($show_empty_announcements) && $show_empty_announcements): ?>
<div class="ann-wrap"><div class="ann-empty"><i class="fas fa-bell-slash"></i><p>No announcements right now</p></div></div>
<?php endif; ?>

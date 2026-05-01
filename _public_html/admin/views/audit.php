<?php
/**
 * Audit Log View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$filter = $_GET['filter'] ?? '';
$filterAdmin = $_GET['admin'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = "1=1";
$params = [];
if ($filter) { $where .= " AND action = ?"; $params[] = $filter; }
if ($filterAdmin) { $where .= " AND admin_username = ?"; $params[] = $filterAdmin; }

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_audit_log WHERE $where");
    $stmt->execute($params); $total = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT id, admin_id, admin_username, action, target_user_id, details, ip_address, created_at FROM admin_audit_log WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $stmt->execute($params);
    $auditLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Action types for filter
    $stmt = $db->query("SELECT DISTINCT action FROM admin_audit_log ORDER BY action");
    $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Admin names for filter
    $stmt = $db->query("SELECT DISTINCT admin_username FROM admin_audit_log ORDER BY admin_username");
    $adminNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Stats
    $stmt = $db->query("SELECT admin_username, COUNT(*) as cnt FROM admin_audit_log GROUP BY admin_username ORDER BY cnt DESC LIMIT 5");
    $topAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->query("SELECT action, COUNT(*) as cnt FROM admin_audit_log GROUP BY action ORDER BY cnt DESC LIMIT 10");
    $topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $auditLog = []; $actionTypes = []; $adminNames = []; $topAdmins = []; $topActions = []; $total = 0; $totalPages = 1; }
?>

<!-- Filters -->
<div class="card">
    <div class="card-header"><span class="card-title">Audit Log</span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        <a href="?tab=audit" class="btn btn-sm <?= !$filter && !$filterAdmin ? 'btn-primary' : 'btn-ghost' ?>">All</a>
        <?php foreach ($topActions as $ta): ?>
        <a href="?tab=audit&filter=<?= urlencode($ta['action']) ?>" class="btn btn-sm <?= $filter===$ta['action']?'btn-primary':'btn-ghost' ?>"><?= $ta['action'] ?> (<?= $ta['cnt'] ?>)</a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">Top Admins</span></div>
        <?php foreach ($topAdmins as $a): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:0.5px solid rgba(255,255,255,0.04)">
            <span style="font-size:13px"><?= htmlspecialchars($a['admin_username']) ?></span>
            <span class="tag tag-violet"><?= $a['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top Actions</span></div>
        <?php foreach ($topActions as $a): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:0.5px solid rgba(255,255,255,0.04)">
            <span style="font-size:13px"><?= htmlspecialchars($a['action']) ?></span>
            <span class="tag tag-violet"><?= $a['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Log Table -->
<div class="card">
    <table class="data-table">
        <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($auditLog as $l): ?>
        <tr>
            <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= date('M j, g:i:s A', strtotime($l['created_at'])) ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($l['admin_username']) ?></td>
            <td><span class="tag tag-violet"><?= htmlspecialchars($l['action']) ?></span></td>
            <td style="color:var(--text-muted)"><?= $l['target_user_id'] ? '#'.$l['target_user_id'] : '—' ?></td>
            <td style="font-family:monospace;font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($l['ip_address']) ?></td>
            <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;color:var(--text-muted)"><?= htmlspecialchars($l['details'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:16px">
        <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
        <a href="?tab=audit&p=<?= $i ?>&filter=<?= urlencode($filter) ?>&admin=<?= urlencode($filterAdmin) ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

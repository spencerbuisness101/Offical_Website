<?php
/**
 * System Logs View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$levelFilter = $_GET['level'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = "1=1";
$params = [];
if ($levelFilter) { $where .= " AND level = ?"; $params[] = $levelFilter; }

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM system_logs WHERE $where");
    $stmt->execute($params); $total = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $db->prepare("SELECT l.id, l.level, l.message, l.user_id, l.ip, l.created_at, u.username FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
    $params[] = $perPage; $params[] = $offset;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $logs = []; $total = 0; $totalPages = 1; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">System Logs</span>
        <div style="display:flex;gap:6px">
            <a href="?tab=logs" class="btn btn-sm <?= !$levelFilter?'btn-primary':'btn-ghost' ?>">All</a>
            <a href="?tab=logs&level=error" class="btn btn-sm <?= $levelFilter==='error'?'btn-primary':'btn-ghost' ?>">Errors</a>
            <a href="?tab=logs&level=security" class="btn btn-sm <?= $levelFilter==='security'?'btn-primary':'btn-ghost' ?>">Security</a>
            <a href="?tab=logs&level=info" class="btn btn-sm <?= $levelFilter==='info'?'btn-primary':'btn-ghost' ?>">Info</a>
        </div>
    </div>
    <?php if ($logs): ?>
    <table class="data-table">
        <thead><tr><th>Time</th><th>Level</th><th>User</th><th>Message</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= date('M j, g:i:s A', strtotime($l['created_at'])) ?></td>
            <td>
                <?php $lvl = $l['level'] ?? 'info'; ?>
                <span class="tag <?= $lvl==='error'?'tag-red':($lvl==='security'?'tag-amber':'tag-violet') ?>"><?= htmlspecialchars($lvl) ?></span>
            </td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
            <td style="font-size:12px;max-width:400px;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($l['context'] ?? '') ?>"><?= htmlspecialchars($l['message'] ?? '') ?></td>
            <td style="font-family:monospace;font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:16px">
        <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
        <a href="?tab=logs&p=<?= $i ?>&level=<?= urlencode($levelFilter) ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-ghost' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php else: ?><p style="color:var(--text-muted)">No system logs found.</p><?php endif; ?>
</div>

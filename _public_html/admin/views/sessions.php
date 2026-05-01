<?php
/**
 * Sessions View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $stmt = $db->query("SELECT us.id, us.user_id, us.session_id, us.ip_address, us.current_page, us.last_activity, u.username FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id WHERE us.last_activity > UNIX_TIMESTAMP()-1800 ORDER BY us.last_activity DESC LIMIT 100");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $sessions = []; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Active Sessions (last 30 min)</span>
        <button class="btn btn-ghost btn-sm" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
    </div>
    <?php if ($sessions): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>IP Address</th><th>Page</th><th>Last Active</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['username'] ?? 'Guest') ?></td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($s['ip_address']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars(basename($s['current_page'] ?? '')) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('g:i:s A', $s['last_activity']) ?></td>
            <td><button class="btn btn-danger btn-sm" onclick="killSession('<?= htmlspecialchars($s['session_id']) ?>')">Kill</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No active sessions.</p><?php endif; ?>
</div>

<script>
async function killSession(sid) {
    if (!confirm('Force logout this session?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'kill_session', session_id: sid, csrf_token: getCsrf() });
    toast(r.success ? 'Session killed' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

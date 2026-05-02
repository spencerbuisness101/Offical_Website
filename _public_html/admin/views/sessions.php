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

<div class="admin-card">
    <div class="card-header">
        <span class="card-title">Live Sessions</span>
        <button class="btn btn-ghost btn-action" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
    </div>
    <?php if ($sessions): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>User</th><th>IP Address</th><th>Current Page</th><th>Last Activity</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:500;color:var(--text-soft)"><?= htmlspecialchars($s['username'] ?? 'Guest Account') ?></div>
                </td>
                <td style="font-family:var(--font-mono);font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($s['ip_address']) ?></td>
                <td style="font-size:11px;color:var(--accent-soft)"><?= htmlspecialchars(basename($s['current_page'] ?? 'main.php')) ?></td>
                <td class="text-muted" style="font-size:11px"><?= date('g:i:s A', $s['last_activity']) ?></td>
                <td><button class="btn btn-danger btn-sm" onclick="killSession('<?= htmlspecialchars($s['session_id']) ?>')" style="border-radius:8px">Terminate</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No active sessions detected.</p><?php endif; ?>
</div>

<script>
async function killSession(sid) {
    if (!confirm('Force logout this session?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'kill_session', session_id: sid, csrf_token: getCsrf() });
    toast(r.success ? 'Session killed' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

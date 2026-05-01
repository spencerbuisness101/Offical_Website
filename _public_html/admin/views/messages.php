<?php
/**
 * Messages / Smail View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $stmt = $db->query("SELECT s.id, s.sender_id, s.receiver_id, s.title as subject, s.created_at as sent_at, su.username as sender_name, ru.username as receiver_name FROM smail_messages s LEFT JOIN users su ON s.sender_id = su.id LEFT JOIN users ru ON s.receiver_id = ru.id ORDER BY s.created_at DESC LIMIT 100");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $messages = []; }
?>

<div class="card">
    <div class="card-header"><span class="card-title">All Smail Messages</span></div>
    <?php if ($messages): ?>
    <table class="data-table">
        <thead><tr><th>From</th><th>To</th><th>Subject</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['sender_name'] ?? 'Unknown') ?></td>
            <td><?= htmlspecialchars($m['receiver_name'] ?? 'Unknown') ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($m['subject'] ?? 'No subject') ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($m['sent_at'])) ?></td>
            <td>
                <button class="btn btn-ghost btn-sm" onclick="viewMessage(<?= $m['id'] ?>)"><i class="fas fa-eye"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteMessage(<?= $m['id'] ?>)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No messages found.</p><?php endif; ?>
</div>

<script>
async function viewMessage(id) {
    const r = await fetch('admin/api/user_analytics.php?smail_id=' + id);
    const data = await r.json();
    if (!data.success) { toast('Failed to load message', 'error'); return; }
    const m = data.message;
    openModal('<div class="modal-title">' + (m.subject || 'No subject') + '</div><div style="font-size:12px;color:var(--text-muted);margin:8px 0">From: ' + (m.sender_name||'?') + ' → To: ' + (m.receiver_name||'?') + '</div><div style="font-size:13px;line-height:1.6;max-height:300px;overflow-y:auto;padding:12px 0;border-top:0.5px solid rgba(255,255,255,0.06)">' + (m.body || m.content || 'No content') + '</div><div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Close</button></div>');
}
async function deleteMessage(id) {
    if (!confirm('Delete this message?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'delete_smail', smail_id: id, csrf_token: getCsrf() });
    toast(r.success ? 'Deleted' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

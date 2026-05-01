<?php
/**
 * AI Chats View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$search = $_GET['q'] ?? '';
try {
    $where = "1=1";
    $params = [];
    if ($search) { $where .= " AND (c.title LIKE ? OR u.username LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $stmt = $db->prepare("SELECT c.id, c.user_id, c.title, c.created_at, c.updated_at, u.username, (SELECT COUNT(*) FROM ai_messages WHERE chat_id = c.id) as msg_count FROM ai_chats c LEFT JOIN users u ON c.user_id = u.id WHERE $where ORDER BY c.updated_at DESC LIMIT 50");
    $stmt->execute($params);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $chats = []; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">AI Conversations</span>
        <div style="display:flex;gap:8px">
            <input type="text" class="form-input" style="width:200px" placeholder="Search chats..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')window.location.href='?tab=ai-chats&q='+encodeURIComponent(this.value)">
        </div>
    </div>
    <?php if ($chats): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Title</th><th>Messages</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($chats as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['username'] ?? 'Unknown') ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($c['title'] ?? 'Untitled') ?></td>
            <td><span class="tag tag-violet"><?= $c['msg_count'] ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            <td>
                <button class="btn btn-ghost btn-sm" onclick="viewChat(<?= $c['id'] ?>)"><i class="fas fa-eye"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteChat(<?= $c['id'] ?>)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No AI chats found.</p><?php endif; ?>
</div>

<script>
async function viewChat(id) {
    const r = await fetch('admin/api/user_analytics.php?chat_id=' + id);
    const data = await r.json();
    if (!data.success) { toast('Failed to load chat', 'error'); return; }
    let html = '<div class="modal-title">' + (data.chat.title || 'Chat #' + id) + '</div>';
    html += '<div style="max-height:400px;overflow-y:auto;margin-top:12px">';
    (data.messages || []).forEach(m => {
        html += '<div style="padding:8px 0;border-bottom:0.5px solid rgba(255,255,255,0.06)"><span class="tag ' + (m.role==='user'?'tag-amber':'tag-teal') + '" style="margin-right:6px">' + m.role + '</span><span style="font-size:13px">' + htmlspecialchars(m.content?.substring(0,200)) + '</span></div>';
    });
    html += '</div><div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Close</button></div>';
    openModal(html);
}
function htmlspecialchars(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
async function deleteChat(id) {
    if (!confirm('Delete this chat permanently?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'delete_chat', chat_id: id, csrf_token: getCsrf() });
    toast(r.success ? 'Chat deleted' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

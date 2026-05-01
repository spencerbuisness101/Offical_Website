<?php
/**
 * Live Chat Monitor View — Spencer's Website v7.0
 * Real-time Yaps feed with inline moderation
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $stmt = $db->query("SELECT m.id, m.user_id, m.message, m.created_at, u.username, u.role FROM chat_messages m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC LIMIT 50");
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Try yaps_messages table name
    try {
        $stmt = $db->query("SELECT m.id, m.user_id, m.content, m.created_at, u.username, u.role FROM yaps_messages m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC LIMIT 50");
        $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $recentMessages = []; }
}
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Live Chat Monitor</span>
        <div style="display:flex;gap:8px;align-items:center">
            <span style="display:inline-block;width:8px;height:8px;background:var(--teal);border-radius:50%;animation:pulse 2s infinite"></span>
            <span style="font-size:12px;color:var(--teal)">Live</span>
            <button class="btn btn-ghost btn-sm" id="pauseBtn" onclick="togglePause()"><i class="fas fa-pause"></i> Pause</button>
            <button class="btn btn-ghost btn-sm" onclick="refreshChat()"><i class="fas fa-sync-alt"></i></button>
        </div>
    </div>
    <div id="chatFeed" style="max-height:500px;overflow-y:auto">
        <?php if ($recentMessages): ?>
        <?php foreach (array_reverse($recentMessages) as $msg): ?>
        <div class="chat-msg" data-id="<?= $msg['id'] ?>" style="padding:8px 0;border-bottom:0.5px solid rgba(255,255,255,0.04);display:flex;gap:8px;align-items:flex-start">
            <div style="flex:1">
                <span style="font-size:12px;font-weight:600;color:var(--accent)"><?= htmlspecialchars($msg['username'] ?? 'Unknown') ?></span>
                <span class="tag tag-violet" style="margin-left:4px;font-size:9px"><?= htmlspecialchars($msg['role'] ?? 'user') ?></span>
                <span style="font-size:11px;color:var(--text-dim);margin-left:8px"><?= date('g:i:s A', strtotime($msg['created_at'])) ?></span>
                <div style="font-size:13px;color:var(--text);margin-top:2px"><?= htmlspecialchars($msg['message'] ?? $msg['content'] ?? '') ?></div>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0">
                <button class="btn btn-danger btn-sm" style="padding:3px 6px" onclick="deleteChatMsg(<?= $msg['id'] ?>)" title="Delete"><i class="fas fa-trash" style="font-size:10px"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?><p style="color:var(--text-muted)">No chat messages found.</p><?php endif; ?>
    </div>
</div>

<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.5}}</style>

<script>
let chatPaused = false;
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function togglePause() {
    chatPaused = !chatPaused;
    const btn = document.getElementById('pauseBtn');
    btn.innerHTML = chatPaused ? '<i class="fas fa-play"></i> Resume' : '<i class="fas fa-pause"></i> Pause';
}

function refreshChat() {
    fetch('admin/api/chat_feed.php?limit=50')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.messages) return;
            const feed = document.getElementById('chatFeed');
            feed.innerHTML = data.messages.map(m => `
                <div class="chat-msg" style="padding:8px 0;border-bottom:0.5px solid rgba(255,255,255,0.04);display:flex;gap:8px;align-items:flex-start">
                    <div style="flex:1">
                        <span style="font-size:12px;font-weight:600;color:var(--accent)">${esc(m.username||'Unknown')}</span>
                        <span class="tag tag-violet" style="margin-left:4px;font-size:9px">${esc(m.role||'user')}</span>
                        <span style="font-size:11px;color:var(--text-dim);margin-left:8px">${esc(m.time||'')}</span>
                        <div style="font-size:13px;color:var(--text);margin-top:2px">${esc(m.message||m.content||'')}</div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0">
                        <button class="btn btn-danger btn-sm" style="padding:3px 6px" onclick="deleteChatMsg(${parseInt(m.id)})" title="Delete"><i class="fas fa-trash" style="font-size:10px"></i></button>
                    </div>
                </div>
            `).join('');
            if (!chatPaused) feed.scrollTop = feed.scrollHeight;
        });
}

// Auto-refresh every 5 seconds
setInterval(() => { if (!chatPaused) refreshChat(); }, 5000);

async function deleteChatMsg(id) {
    const r = await apiCall('admin/api/chat_action.php', { action: 'delete', message_id: id, csrf_token: getCsrf() });
    toast(r.success ? 'Message deleted' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) refreshChat();
}
</script>

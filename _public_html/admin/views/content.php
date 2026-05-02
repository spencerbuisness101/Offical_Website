<?php
/**
 * Content Review View — Spencer's Website v7.0
 * Contributor ideas + designer backgrounds + PFP approvals
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$subtab = $_GET['sub'] ?? 'ideas';
$validSubs = ['ideas', 'backgrounds', 'pfps'];
if (!in_array($subtab, $validSubs)) $subtab = 'ideas';
?>

<div style="display:flex;gap:10px;margin-bottom:24px">
    <a href="?tab=content&sub=ideas" class="chip <?= $subtab==='ideas'?'active':'' ?>">Contributor Ideas</a>
    <a href="?tab=content&sub=backgrounds" class="chip <?= $subtab==='backgrounds'?'active':'' ?>">Designer Backgrounds</a>
    <a href="?tab=content&sub=pfps" class="chip <?= $subtab==='pfps'?'active':'' ?>">PFP Approvals</a>
</div>

<?php if ($subtab === 'ideas'): ?>
<?php
try {
    $stmt = $db->query("SELECT ci.id, ci.title, ci.idea_text, ci.user_id, ci.priority, ci.status, ci.created_at, u.username FROM contributor_ideas ci LEFT JOIN users u ON ci.user_id = u.id ORDER BY FIELD(ci.status,'pending','under_review','approved','completed','rejected'), ci.created_at DESC LIMIT 50");
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ideas = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Pending Ideas</span></div>
    <?php if ($ideas): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Title / Concept</th><th>User</th><th>Priority</th><th>Status</th><th>Logged</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($ideas as $i): ?>
            <tr>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($i['idea_text'] ?? '') ?>">
                    <?= htmlspecialchars($i['title'] ?? $i['idea_text'] ?? 'Untitled Concept') ?>
                </td>
                <td><?= htmlspecialchars($i['username'] ?? 'Anonymous') ?></td>
                <td><span class="badge badge--<?= ($i['priority']??'')==='critical'?'danger':(($i['priority']??'')==='high'?'warn':'violet') ?>"><?= strtoupper($i['priority'] ?? 'medium') ?></span></td>
                <td><span class="badge badge--<?= ($i['status']??'')==='pending'?'warn':(($i['status']??'')==='approved'?'teal':'violet') ?>"><?= strtoupper($i['status'] ?? 'pending') ?></span></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, Y', strtotime($i['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-ghost btn-action" title="Approve" onclick="updateIdea(<?= $i['id'] ?>,'approved')"><i class="fas fa-check" style="color:var(--teal)"></i></button>
                        <button class="btn btn-ghost btn-action" title="Reject" onclick="updateIdea(<?= $i['id'] ?>,'rejected')"><i class="fas fa-times" style="color:var(--danger)"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No ideas currently queued for review.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'backgrounds'): ?>
<?php
try {
    $stmt = $db->query("SELECT db.id, db.title, db.user_id, db.status, db.created_at, u.username FROM designer_backgrounds db LEFT JOIN users u ON db.user_id = u.id ORDER BY FIELD(db.status,'pending','approved','rejected'), db.created_at DESC LIMIT 50");
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $backgrounds = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Asset Submissions</span></div>
    <?php if ($backgrounds): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Asset Name</th><th>Designer</th><th>Status</th><th>Logged</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($backgrounds as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['title'] ?? 'Custom Backdrop') ?></td>
                <td><?= htmlspecialchars($b['username'] ?? 'Anonymous') ?></td>
                <td><span class="badge badge--<?= ($b['status']??'')==='pending'?'warn':(($b['status']??'')==='approved'?'teal':'danger') ?>"><?= strtoupper($b['status'] ?? 'pending') ?></span></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-ghost btn-action" onclick="updateBackground(<?= $b['id'] ?>,'approved')"><i class="fas fa-check" style="color:var(--teal)"></i></button>
                        <button class="btn btn-ghost btn-action" onclick="updateBackground(<?= $b['id'] ?>,'rejected')"><i class="fas fa-times" style="color:var(--danger)"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No asset submissions pending.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'pfps'): ?>
<?php
try {
    $stmt = $db->query("SELECT id, username, pfp_pending_url, pfp_pending_path FROM users WHERE pfp_type = 'pending' ORDER BY id DESC LIMIT 50");
    $pendingPfps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingPfps = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Avatar Approvals</span></div>
    <?php if ($pendingPfps): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>User</th><th>Preview</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pendingPfps as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['username']) ?></td>
                <td>
                    <?php if ($p['pfp_pending_url']): ?>
                    <div style="width:48px;height:48px;border-radius:12px;overflow:hidden;border:1px solid rgba(123,110,246,0.3);box-shadow:0 0 15px rgba(123,110,246,0.1)">
                        <img src="<?= htmlspecialchars($p['pfp_pending_url']) ?>" style="width:100%;height:100%;object-fit:cover">
                    </div>
                    <?php else: ?><span class="text-faint" style="font-size:11px">Local Blob</span><?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-ghost btn-action" title="Approve" onclick="decidePfp(<?= $p['id'] ?>,'approve')"><i class="fas fa-check" style="color:var(--teal)"></i></button>
                        <button class="btn btn-ghost btn-action" title="Decline" onclick="decidePfp(<?= $p['id'] ?>,'decline')"><i class="fas fa-times" style="color:var(--danger)"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No biometric updates pending.</p><?php endif; ?>
</div>
<?php endif; ?>

<script>
async function updateIdea(id, status) {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'update_idea', id, status, csrf_token: getCsrf() });
    toast(r.success ? 'Idea ' + status : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
async function updateBackground(id, status) {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'update_background', id, status, csrf_token: getCsrf() });
    toast(r.success ? 'Background ' + status : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
async function decidePfp(userId, decision) {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'pfp_' + decision, user_id: userId, csrf_token: getCsrf() });
    toast(r.success ? 'PFP ' + decision + 'd' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

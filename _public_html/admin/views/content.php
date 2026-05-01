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

<div style="display:flex;gap:6px;margin-bottom:20px">
    <a href="?tab=content&sub=ideas" class="btn btn-sm <?= $subtab==='ideas'?'btn-primary':'btn-ghost' ?>">Contributor Ideas</a>
    <a href="?tab=content&sub=backgrounds" class="btn btn-sm <?= $subtab==='backgrounds'?'btn-primary':'btn-ghost' ?>">Designer Backgrounds</a>
    <a href="?tab=content&sub=pfps" class="btn btn-sm <?= $subtab==='pfps'?'btn-primary':'btn-ghost' ?>">PFP Approvals</a>
</div>

<?php if ($subtab === 'ideas'): ?>
<?php
try {
    $stmt = $db->query("SELECT ci.id, ci.title, ci.idea_text, ci.user_id, ci.priority, ci.status, ci.created_at, u.username FROM contributor_ideas ci LEFT JOIN users u ON ci.user_id = u.id ORDER BY FIELD(ci.status,'pending','under_review','approved','completed','rejected'), ci.created_at DESC LIMIT 50");
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ideas = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Contributor Ideas</span></div>
    <?php if ($ideas): ?>
    <table class="data-table">
        <thead><tr><th>Title</th><th>User</th><th>Priority</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($ideas as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i['title'] ?? $i['idea_text'] ?? 'Untitled') ?></td>
            <td><?= htmlspecialchars($i['username']) ?></td>
            <td><span class="tag <?= $i['priority']==='critical'?'tag-red':($i['priority']==='high'?'tag-amber':'tag-violet') ?>"><?= $i['priority'] ?? 'medium' ?></span></td>
            <td><span class="tag <?= $i['status']==='pending'?'tag-amber':($i['status']==='approved'?'tag-teal':'tag-violet') ?>"><?= $i['status'] ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y', strtotime($i['created_at'])) ?></td>
            <td>
                <button class="btn btn-teal btn-sm" onclick="updateIdea(<?= $i['id'] ?>,'approved')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="updateIdea(<?= $i['id'] ?>,'rejected')">Reject</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No contributor ideas.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'backgrounds'): ?>
<?php
try {
    $stmt = $db->query("SELECT db.id, db.title, db.user_id, db.status, db.created_at, u.username FROM designer_backgrounds db LEFT JOIN users u ON db.user_id = u.id ORDER BY FIELD(db.status,'pending','approved','rejected'), db.created_at DESC LIMIT 50");
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $backgrounds = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Designer Backgrounds</span></div>
    <?php if ($backgrounds): ?>
    <table class="data-table">
        <thead><tr><th>Name</th><th>User</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($backgrounds as $b): ?>
        <tr>
            <td><?= htmlspecialchars($b['name'] ?? 'Untitled') ?></td>
            <td><?= htmlspecialchars($b['username']) ?></td>
            <td><span class="tag <?= $b['status']==='pending'?'tag-amber':($b['status']==='approved'?'tag-teal':'tag-red') ?>"><?= $b['status'] ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
            <td>
                <button class="btn btn-teal btn-sm" onclick="updateBackground(<?= $b['id'] ?>,'approved')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="updateBackground(<?= $b['id'] ?>,'rejected')">Reject</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No designer backgrounds.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'pfps'): ?>
<?php
try {
    $stmt = $db->query("SELECT id, username, pfp_pending_url, pfp_pending_path FROM users WHERE pfp_type = 'pending' ORDER BY id DESC LIMIT 50");
    $pendingPfps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingPfps = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Pending Profile Pictures</span></div>
    <?php if ($pendingPfps): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Preview</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pendingPfps as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['username']) ?></td>
            <td>
                <?php if ($p['pfp_pending_url']): ?>
                <img src="<?= htmlspecialchars($p['pfp_pending_url']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,0.1)">
                <?php else: ?><span style="color:var(--text-muted)">File upload</span><?php endif; ?>
            </td>
            <td>
                <button class="btn btn-teal btn-sm" onclick="decidePfp(<?= $p['id'] ?>,'approve')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="decidePfp(<?= $p['id'] ?>,'decline')">Decline</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No pending PFPs.</p><?php endif; ?>
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

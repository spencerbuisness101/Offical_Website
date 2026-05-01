<?php
/**
 * Announcements View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $stmt = $db->query("SELECT a.id, a.title, a.type, a.is_active, a.created_at, a.created_by, u.username as created_by_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC LIMIT 50");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $announcements = []; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Announcements</span>
        <button class="btn btn-primary btn-sm" onclick="openCreateAnnouncement()"><i class="fas fa-plus"></i> Create</button>
    </div>
    <?php if ($announcements): ?>
    <table class="data-table">
        <thead><tr><th>Title</th><th>Type</th><th>Active</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($announcements as $a): ?>
        <tr>
            <td style="font-weight:500"><?= htmlspecialchars($a['title'] ?? 'Untitled') ?></td>
            <td><span class="tag tag-violet"><?= htmlspecialchars($a['type'] ?? 'info') ?></span></td>
            <td><?= !empty($a['is_active']) ? '<span class="tag tag-teal">Active</span>' : '<span class="tag tag-red">Inactive</span>' ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($a['created_by_name'] ?? 'System') ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?></td>
            <td>
                <button class="btn btn-ghost btn-sm" onclick="toggleAnnouncement(<?= $a['id'] ?>,<?= empty($a['is_active'])?1:0 ?>)"><?= empty($a['is_active']) ? 'Enable' : 'Disable' ?></button>
                <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(<?= $a['id'] ?>)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No announcements.</p><?php endif; ?>
</div>

<script>
function openCreateAnnouncement() {
    openModal(`
        <div class="modal-title">Create Announcement</div>
        <div class="form-group"><label class="form-label">Title</label><input class="form-input" id="annTitle"></div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-input" id="annType"><option value="info">Info</option><option value="warning">Warning</option><option value="update">Update</option><option value="maintenance">Maintenance</option></select></div>
        <div class="form-group"><label class="form-label">Message</label><textarea class="form-input" id="annMessage" rows="4"></textarea></div>
        <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button><button class="btn btn-primary" onclick="createAnnouncement()">Create</button></div>
    `);
}
async function createAnnouncement() {
    const r = await apiCall('admin/api/user_analytics.php', {
        action: 'create_announcement', title: document.getElementById('annTitle').value,
        type: document.getElementById('annType').value, message: document.getElementById('annMessage').value,
        csrf_token: getCsrf()
    });
    toast(r.success ? 'Announcement created' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) { closeModal(); setTimeout(() => location.reload(), 800); }
}
async function toggleAnnouncement(id, active) {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'toggle_announcement', id, is_active: active, csrf_token: getCsrf() });
    toast(r.success ? 'Updated' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
async function deleteAnnouncement(id) {
    if (!confirm('Delete this announcement?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'delete_announcement', id, csrf_token: getCsrf() });
    toast(r.success ? 'Deleted' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

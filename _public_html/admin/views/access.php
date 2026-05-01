<?php
/**
 * Access Restrictions View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $db->exec("CREATE TABLE IF NOT EXISTS access_restrictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        value VARCHAR(255) NOT NULL,
        reason TEXT,
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $db->query("SELECT id, type, value, reason, created_by, created_at FROM access_restrictions ORDER BY created_at DESC");
    $restrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $restrictions = []; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Access Restrictions</span>
        <button class="btn btn-primary btn-sm" onclick="openAddRestriction()"><i class="fas fa-plus"></i> Add</button>
    </div>
    <?php if ($restrictions): ?>
    <table class="data-table">
        <thead><tr><th>Type</th><th>Value</th><th>Reason</th><th>Added By</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($restrictions as $r): ?>
        <tr>
            <td><span class="tag tag-violet"><?= htmlspecialchars($r['type']) ?></span></td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($r['value']) ?></td>
            <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['reason'] ?? '') ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($r['created_by']) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td><button class="btn btn-danger btn-sm" onclick="deleteRestriction(<?= $r['id'] ?>)"><i class="fas fa-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No access restrictions.</p><?php endif; ?>
</div>

<script>
function openAddRestriction() {
    openModal(`
        <div class="modal-title">Add Access Restriction</div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-input" id="restType"><option value="ip">IP Address</option><option value="page">Page</option><option value="user">User</option><option value="email">Email Domain</option></select></div>
        <div class="form-group"><label class="form-label">Value</label><input class="form-input" id="restValue" placeholder="e.g. 192.168.1.1"></div>
        <div class="form-group"><label class="form-label">Reason</label><input class="form-input" id="restReason" placeholder="Optional reason"></div>
        <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button><button class="btn btn-primary" onclick="addRestriction()">Add</button></div>
    `);
}
async function addRestriction() {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'add_restriction', type: document.getElementById('restType').value, value: document.getElementById('restValue').value, reason: document.getElementById('restReason').value, csrf_token: getCsrf() });
    toast(r.success ? 'Added' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) { closeModal(); setTimeout(() => location.reload(), 800); }
}
async function deleteRestriction(id) {
    if (!confirm('Remove this restriction?')) return;
    const r = await apiCall('admin/api/user_analytics.php', { action: 'delete_restriction', id, csrf_token: getCsrf() });
    toast(r.success ? 'Removed' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

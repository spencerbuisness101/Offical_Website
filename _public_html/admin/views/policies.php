<?php
/**
 * Policy Manager View — Spencer's Website v7.0
 * Edit policy text from admin panel with version history
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

// Ensure policy_versions table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS policy_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        policy_name VARCHAR(50) NOT NULL,
        content LONGTEXT,
        changed_by VARCHAR(50),
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        version INT DEFAULT 1,
        INDEX idx_policy (policy_name),
        INDEX idx_version (policy_name, version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { error_log("policy_versions table error: " . $e->getMessage()); }

$policies = [
    'terms' => ['file' => 'terms.php', 'label' => 'Terms of Service'],
    'privacy' => ['file' => 'privacy.php', 'label' => 'Privacy Policy'],
    'refund-policy' => ['file' => 'refund-policy.php', 'label' => 'Refund Policy'],
    'community-standards' => ['file' => 'community-standards.php', 'label' => 'Community Standards'],
    'dmca' => ['file' => 'dmca.php', 'label' => 'DMCA / Copyright Policy'],
];

$editPolicy = $_GET['edit'] ?? '';
$editContent = '';
$editVersions = [];

if ($editPolicy && isset($policies[$editPolicy])) {
    $filePath = __DIR__ . '/../../' . $policies[$editPolicy]['file'];
    if (file_exists($filePath)) {
        $editContent = file_get_contents($filePath);
    }
    try {
        $stmt = $db->prepare("SELECT id, policy_name, content, changed_by, changed_at, version FROM policy_versions WHERE policy_name = ? ORDER BY version DESC LIMIT 10");
        $stmt->execute([$editPolicy]);
        $editVersions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $editVersions = []; }
}
?>

<?php if (!$editPolicy): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Policy Manager</span></div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Edit policy pages directly from the admin panel. All changes are versioned and can be rolled back.</p>
    <table class="data-table">
        <thead><tr><th>Policy</th><th>File</th><th>Versions</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($policies as $key => $p): ?>
        <?php
            $versionCount = 0;
            try { $stmt = $db->prepare("SELECT COUNT(*) FROM policy_versions WHERE policy_name = ?"); $stmt->execute([$key]); $versionCount = (int)$stmt->fetchColumn(); } catch (Exception $e) {}
        ?>
        <tr>
            <td><?= $p['label'] ?></td>
            <td style="font-family:monospace;font-size:12px;color:var(--text-muted)"><?= $p['file'] ?></td>
            <td><span class="tag tag-violet"><?= $versionCount ?></span></td>
            <td>
                <a href="?tab=policies&edit=<?= $key ?>" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</a>
                <a href="<?= $p['file'] ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-external-link-alt"></i> View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Editing: <?= $policies[$editPolicy]['label'] ?></span>
        <div style="display:flex;gap:8px">
            <a href="?tab=policies" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            <button class="btn btn-primary btn-sm" onclick="savePolicy('<?= $editPolicy ?>')"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
    <textarea class="form-input" id="policyEditor" style="height:500px;font-family:monospace;font-size:12px;line-height:1.5"><?= htmlspecialchars($editContent) ?></textarea>
</div>

<?php if ($editVersions): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Version History</span></div>
    <table class="data-table">
        <thead><tr><th>Version</th><th>Changed By</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($editVersions as $v): ?>
        <tr>
            <td><span class="tag tag-violet">v<?= $v['version'] ?></span></td>
            <td><?= htmlspecialchars($v['changed_by']) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, Y g:i A', strtotime($v['changed_at'])) ?></td>
            <td><button class="btn btn-ghost btn-sm" onclick="rollbackPolicy('<?= $editPolicy ?>', <?= $v['version'] ?>)">Rollback</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
async function savePolicy(name) {
    const content = document.getElementById('policyEditor').value;
    const r = await apiCall('admin/api/policy_update.php', { policy_name: name, content, csrf_token: getCsrf() });
    toast(r.success ? 'Policy saved — version ' + (r.version || 'new') : 'Failed: ' + (r.message || 'Unknown'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 1000);
}

async function rollbackPolicy(name, version) {
    if (!confirm('Rollback to version ' + version + '? Current content will be replaced.')) return;
    const r = await apiCall('admin/api/policy_update.php', { policy_name: name, rollback_version: version, csrf_token: getCsrf() });
    toast(r.success ? 'Rolled back to v' + version : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 1000);
}
</script>

<?php
/**
 * Tracking / Device Fingerprints View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

try {
    $stmt = $db->query("SELECT df.id, df.user_id, df.device_uuid, df.ip_address, df.visit_count, df.first_seen, df.last_seen, u.username FROM device_fingerprints df LEFT JOIN users u ON df.user_id = u.id ORDER BY df.last_seen DESC LIMIT 100");
    $fingerprints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $fingerprints = []; }
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Device Fingerprints</span>
        <span class="tag tag-violet"><?= count($fingerprints) ?> records</span>
    </div>
    <?php if ($fingerprints): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Device UUID</th><th>IP</th><th>Visits</th><th>First Seen</th><th>Last Seen</th></tr></thead>
        <tbody>
        <?php foreach ($fingerprints as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f['username'] ?? 'Unknown') ?></td>
            <td style="font-family:monospace;font-size:11px;color:var(--text-muted)"><?= substr($f['device_uuid'], 0, 12) ?>...</td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($f['ip_address']) ?></td>
            <td><?= $f['visit_count'] ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j', strtotime($f['first_seen'])) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j', strtotime($f['last_seen'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No device fingerprints recorded.</p><?php endif; ?>
</div>

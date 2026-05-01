<?php
/**
 * Enhanced User Analytics View — Spencer's Website v7.0
 * Deep user profiles with behavioral flags
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$search = $_GET['q'] ?? '';
$analyticsData = null;

if ($search) {
    try {
        $stmt = $db->prepare("SELECT id, username, email, role, is_suspended, created_at, last_login FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 1");
        $stmt->execute(["%$search%", "%$search%"]);
        $analyticsData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $analyticsData = null; }
}

$userId = $analyticsData ? $analyticsData['id'] : null;
?>

<div class="card">
    <div class="card-header"><span class="card-title">User Analytics</span></div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Search for a user to view their deep analytics profile.</p>
    <div style="display:flex;gap:8px;margin-bottom:20px">
        <input type="text" class="form-input" style="width:300px" placeholder="Search by username or email..." value="<?= htmlspecialchars($search) ?>" id="analyticsSearch" onkeydown="if(event.key==='Enter')searchAnalytics()">
        <button class="btn btn-primary btn-sm" onclick="searchAnalytics()"><i class="fas fa-search"></i></button>
    </div>
</div>

<?php if ($analyticsData): ?>
<?php
try {
    // Login history
    $stmt = $db->prepare("SELECT ip_address, user_agent, last_activity, current_page FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC LIMIT 20");
    $stmt->execute([$userId]); $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Device fingerprints
    $stmt = $db->prepare("SELECT fingerprint_hash, device_uuid, ip_address, first_seen, last_seen, visit_count FROM device_fingerprints WHERE user_id = ? ORDER BY last_seen DESC LIMIT 10");
    $stmt->execute([$userId]); $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment history
    $stmt = $db->prepare("SELECT amount_cents, plan_type, status, ip_address, created_at FROM payment_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]); $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // AI usage
    $stmt = $db->prepare("SELECT COUNT(*) as chat_count FROM ai_chats WHERE user_id = ?");
    $stmt->execute([$userId]); $aiChats = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) as msg_count FROM ai_messages m JOIN ai_chats c ON m.chat_id = c.id WHERE c.user_id = ?");
    $stmt->execute([$userId]); $aiMessages = (int)$stmt->fetchColumn();

    // Behavioral flags
    $flags = [];
    $uniqueIps = count(array_unique(array_column($sessions, 'ip_address')));
    if ($uniqueIps > 5) $flags[] = ['label' => 'Multiple IPs (' . $uniqueIps . ')', 'level' => 'warning'];
    if (count($devices) > 3) $flags[] = ['label' => 'Multiple devices (' . count($devices) . ')', 'level' => 'warning'];
    if ($analyticsData['is_suspended']) $flags[] = ['label' => 'Suspended', 'level' => 'danger'];
} catch (Exception $e) {
    $sessions = $devices = $payments = []; $aiChats = $aiMessages = 0; $flags = [];
}
?>

<div class="stat-grid">
    <div class="stat-box"><div class="stat-value"><?= htmlspecialchars($analyticsData['username']) ?></div><div class="stat-label"><?= ucfirst($analyticsData['role']) ?> &middot; ID #<?= $analyticsData['id'] ?></div></div>
    <div class="stat-box"><div class="stat-value teal"><?= $aiChats ?></div><div class="stat-label">AI Chats</div></div>
    <div class="stat-box"><div class="stat-value accent"><?= $aiMessages ?></div><div class="stat-label">AI Messages</div></div>
    <div class="stat-box"><div class="stat-value"><?= count($payments) ?></div><div class="stat-label">Payments</div></div>
</div>

<?php if ($flags): ?>
<div class="card" style="border-left:4px solid var(--amber)">
    <div class="card-header"><span class="card-title">Behavioral Flags</span></div>
    <?php foreach ($flags as $f): ?>
    <span class="tag <?= $f['level']==='danger'?'tag-red':'tag-amber' ?>" style="margin-right:6px"><?= $f['label'] ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">Recent Sessions</span></div>
        <?php if ($sessions): ?>
        <table class="data-table"><thead><tr><th>IP</th><th>Page</th><th>Last Active</th></tr></thead><tbody>
        <?php foreach (array_slice($sessions, 0, 8) as $s): ?>
        <tr><td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($s['ip_address']) ?></td><td style="font-size:12px"><?= htmlspecialchars(basename($s['current_page'] ?? '')) ?></td><td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', $s['last_activity']) ?></td></tr>
        <?php endforeach; ?></tbody></table>
        <?php else: ?><p style="color:var(--text-muted)">No sessions</p><?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Device Fingerprints</span></div>
        <?php if ($devices): ?>
        <table class="data-table"><thead><tr><th>IP</th><th>Visits</th><th>Last Seen</th></tr></thead><tbody>
        <?php foreach ($devices as $d): ?>
        <tr><td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($d['ip_address']) ?></td><td><?= $d['visit_count'] ?></td><td style="color:var(--text-muted);font-size:12px"><?= date('M j', strtotime($d['last_seen'])) ?></td></tr>
        <?php endforeach; ?></tbody></table>
        <?php else: ?><p style="color:var(--text-muted)">No devices</p><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Payment History</span></div>
    <?php if ($payments): ?>
    <table class="data-table"><thead><tr><th>Amount</th><th>Plan</th><th>Status</th><th>IP</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?>
    <tr>
        <td>$<?= number_format($p['amount_cents']/100,2) ?></td>
        <td><span class="tag tag-violet"><?= htmlspecialchars($p['plan_type'] ?? 'N/A') ?></span></td>
        <td><span class="tag <?= $p['status']==='completed'?'tag-teal':'tag-amber' ?>"><?= $p['status'] ?></span></td>
        <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['ip_address'] ?? 'N/A') ?></td>
        <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($p['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?></tbody></table>
    <?php else: ?><p style="color:var(--text-muted)">No payments</p><?php endif; ?>
</div>
<?php endif; ?>

<script>
function searchAnalytics() {
    const q = document.getElementById('analyticsSearch').value;
    window.location.href = '?tab=analytics&q=' + encodeURIComponent(q);
}
</script>

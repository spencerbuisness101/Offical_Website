<?php
/**
 * Threat Monitor View — Spencer's Website v7.0
 * Live threat dashboard + geo-blocking
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$subtab = $_GET['sub'] ?? 'overview';
$validSubs = ['overview', 'blocked-ips', 'geo-block'];
if (!in_array($subtab, $validSubs)) $subtab = 'overview';

// Load threat data
try {
    $stmt = $db->query("SELECT COUNT(*) FROM blocked_ips WHERE expires_at IS NULL OR expires_at > NOW()"); $blockedCount = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT id, ip_address, reason, blocked_at, expires_at, blocked_by FROM blocked_ips ORDER BY blocked_at DESC LIMIT 50"); $blockedIps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->query("SELECT ip_address, endpoint, COUNT(*) as cnt, MAX(window_start) as last_hit FROM rate_limit_ip GROUP BY ip_address, endpoint ORDER BY cnt DESC LIMIT 20"); $rateLimitHits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->query("SELECT ip_address, COUNT(*) as attempts FROM rate_limit_ip WHERE endpoint='login' AND window_start > DATE_SUB(NOW(),INTERVAL 24 HOUR) GROUP BY ip_address ORDER BY attempts DESC LIMIT 10"); $failedLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blockedCount = 0; $blockedIps = []; $rateLimitHits = []; $failedLogins = [];
}

$blockedCountries = json_decode(getSetting($db, 'blocked_countries', '[]'), true) ?: [];
?>

<!-- Sub-tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px">
    <a href="?tab=threats&sub=overview" class="btn btn-sm <?= $subtab==='overview'?'btn-primary':'btn-ghost' ?>">Overview</a>
    <a href="?tab=threats&sub=blocked-ips" class="btn btn-sm <?= $subtab==='blocked-ips'?'btn-primary':'btn-ghost' ?>">Blocked IPs (<?= $blockedCount ?>)</a>
    <a href="?tab=threats&sub=geo-block" class="btn btn-sm <?= $subtab==='geo-block'?'btn-primary':'btn-ghost' ?>">Geo-Block</a>
</div>

<?php if ($subtab === 'overview'): ?>
<!-- Stats -->
<div class="stat-grid">
    <div class="stat-box">
        <div class="stat-value" style="color:var(--red)"><?= $blockedCount ?></div>
        <div class="stat-label">Blocked IPs</div>
    </div>
    <div class="stat-box">
        <div class="stat-value" style="color:var(--amber)"><?= count($failedLogins) ?></div>
        <div class="stat-label">Failed Login IPs (24h)</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= count($rateLimitHits) ?></div>
        <div class="stat-label">Rate-Limited IPs</div>
    </div>
</div>

<!-- Failed Logins -->
<div class="card">
    <div class="card-header"><span class="card-title">Failed Login Attempts (24h)</span></div>
    <?php if ($failedLogins): ?>
    <table class="data-table">
        <thead><tr><th>IP Address</th><th>Attempts</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($failedLogins as $fl): ?>
        <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($fl['ip_address']) ?></td>
            <td><span class="tag <?= $fl['attempts']>=5?'tag-red':'tag-amber' ?>"><?= $fl['attempts'] ?></span></td>
            <td><button class="btn btn-danger btn-sm" onclick="blockIp('<?= htmlspecialchars($fl['ip_address']) ?>')">Block</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No failed login attempts in the last 24 hours.</p><?php endif; ?>
</div>

<!-- Rate Limit Hits -->
<div class="card">
    <div class="card-header"><span class="card-title">Rate Limit Hits</span></div>
    <?php if ($rateLimitHits): ?>
    <table class="data-table">
        <thead><tr><th>IP Address</th><th>Endpoint</th><th>Hits</th></tr></thead>
        <tbody>
        <?php foreach ($rateLimitHits as $rl): ?>
        <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($rl['ip_address']) ?></td>
            <td><span class="tag tag-violet"><?= htmlspecialchars($rl['endpoint']) ?></span></td>
            <td><?= $rl['cnt'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No rate limit hits.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'blocked-ips'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Blocked IP Addresses</span>
        <button class="btn btn-ghost btn-sm" onclick="openBlockIpModal()"><i class="fas fa-plus"></i> Block IP</button>
    </div>
    <?php if ($blockedIps): ?>
    <table class="data-table">
        <thead><tr><th>IP Address</th><th>Reason</th><th>Blocked At</th><th>Expires</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($blockedIps as $b): ?>
        <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($b['ip_address']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars($b['reason']) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($b['blocked_at'])) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= $b['expires_at'] ? date('M j, g:i A', strtotime($b['expires_at'])) : 'Never' ?></td>
            <td><button class="btn btn-teal btn-sm" onclick="unblockIp('<?= htmlspecialchars($b['ip_address']) ?>')">Unblock</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No blocked IPs.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'geo-block'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Country Blocking</span>
        <span class="tag tag-amber"><?= count($blockedCountries) ?> countries blocked</span>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Users attempting to login from blocked countries will be rejected. Uses ISO 3166-1 alpha-2 country codes.</p>
    <div style="display:flex;gap:8px;margin-bottom:16px">
        <input type="text" class="form-input" style="width:100px" placeholder="e.g. CN" id="newCountryCode" maxlength="2">
        <button class="btn btn-primary btn-sm" onclick="addBlockedCountry()">Add Country</button>
    </div>
    <?php if ($blockedCountries): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($blockedCountries as $cc): ?>
        <span class="tag tag-red" style="cursor:pointer;font-size:13px;padding:6px 12px" onclick="removeBlockedCountry('<?= $cc ?>')" title="Click to remove">
            <?= strtoupper($cc) ?> <i class="fas fa-times" style="margin-left:4px"></i>
        </span>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--text-muted)">No countries blocked.</p><?php endif; ?>
</div>
<?php endif; ?>

<script>
async function blockIp(ip) {
    if (!confirm('Block IP ' + ip + ' for 30 minutes?')) return;
    const r = await apiCall('admin/api/threat_data.php', { action: 'block', ip, csrf_token: getCsrf() });
    toast(r.success ? 'IP blocked' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function unblockIp(ip) {
    const r = await apiCall('admin/api/threat_data.php', { action: 'unblock', ip, csrf_token: getCsrf() });
    toast(r.success ? 'IP unblocked' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

function openBlockIpModal() {
    openModal(`
        <div class="modal-title">Block IP Address</div>
        <div class="form-group"><label class="form-label">IP Address</label><input class="form-input" id="blockIpInput" placeholder="e.g. 192.168.1.1"></div>
        <div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" id="blockDurationInput" value="30" type="number"></div>
        <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button><button class="btn btn-danger" onclick="manualBlockIp()">Block</button></div>
    `);
}

async function manualBlockIp() {
    const ip = document.getElementById('blockIpInput').value.trim();
    const duration = parseInt(document.getElementById('blockDurationInput').value) || 30;
    if (!ip) { toast('Enter an IP address', 'error'); return; }
    const r = await apiCall('admin/api/threat_data.php', { action: 'manual_block', ip, duration, csrf_token: getCsrf() });
    toast(r.success ? 'IP blocked' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) { closeModal(); setTimeout(() => location.reload(), 800); }
}

async function addBlockedCountry() {
    const code = document.getElementById('newCountryCode').value.trim().toUpperCase();
    if (!code || code.length !== 2) { toast('Enter a valid 2-letter country code', 'error'); return; }
    const r = await apiCall('admin/api/threat_data.php', { action: 'add_country', code, csrf_token: getCsrf() });
    toast(r.success ? 'Country blocked' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function removeBlockedCountry(code) {
    if (!confirm('Remove ' + code + ' from blocked countries?')) return;
    const r = await apiCall('admin/api/threat_data.php', { action: 'remove_country', code, csrf_token: getCsrf() });
    toast(r.success ? 'Country unblocked' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

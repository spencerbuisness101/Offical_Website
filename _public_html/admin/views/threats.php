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
<div style="display:flex;gap:10px;margin-bottom:24px">
    <a href="?tab=threats&sub=overview" class="chip <?= $subtab==='overview'?'active':'' ?>">Threat Overview</a>
    <a href="?tab=threats&sub=blocked-ips" class="chip <?= $subtab==='blocked-ips'?'active':'' ?>">Blocked IPs (<?= $blockedCount ?>)</a>
    <a href="?tab=threats&sub=geo-block" class="chip <?= $subtab==='geo-block'?'active':'' ?>">Geo-Blocking</a>
</div>

<?php if ($subtab === 'overview'): ?>
<!-- Stats -->
<div class="stats-row">
    <div class="stat-tile">
        <div class="stat-icon red"><i class="fas fa-shield-virus"></i></div>
        <div class="stat-value" style="color:var(--red)"><?= $blockedCount ?></div>
        <div class="stat-label">Blocked IPs</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon amber"><i class="fas fa-user-secret"></i></div>
        <div class="stat-value" style="color:var(--amber)"><?= count($failedLogins) ?></div>
        <div class="stat-label">Failed Logins (24h)</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon violet"><i class="fas fa-tachometer-alt"></i></div>
        <div class="stat-value"><?= count($rateLimitHits) ?></div>
        <div class="stat-label">Rate-Limited IPs</div>
    </div>
</div>

<!-- Failed Logins -->
<div class="admin-card">
    <div class="card-header"><span class="card-title">Failed Authentication Attempts (24h)</span></div>
    <?php if ($failedLogins): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Origin IP</th><th>Magnitude</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($failedLogins as $fl): ?>
            <tr>
                <td style="font-family:var(--font-mono)"><?= htmlspecialchars($fl['ip_address']) ?></td>
                <td><span class="badge badge--<?= $fl['attempts']>=5?'danger':'warn' ?>"><?= $fl['attempts'] ?> ATTEMPTS</span></td>
                <td><button class="btn btn-danger btn-sm" onclick="blockIp('<?= htmlspecialchars($fl['ip_address']) ?>')" style="border-radius:8px">Block IP</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No abnormal authentication patterns detected.</p><?php endif; ?>
</div>

<!-- Rate Limit Hits -->
<div class="admin-card">
    <div class="card-header"><span class="card-title">Network Congestion (Rate Limits)</span></div>
    <?php if ($rateLimitHits): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>IP Address</th><th>Endpoint</th><th>Hit Count</th></tr></thead>
            <tbody>
            <?php foreach ($rateLimitHits as $rl): ?>
            <tr>
                <td style="font-family:var(--font-mono)"><?= htmlspecialchars($rl['ip_address']) ?></td>
                <td><span class="badge badge--violet"><?= strtoupper(htmlspecialchars($rl['endpoint'])) ?></span></td>
                <td><span class="text-muted"><?= $rl['cnt'] ?> hits</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No rate limit signal saturation detected.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'blocked-ips'): ?>
<div class="admin-card">
    <div class="card-header">
        <span class="card-title">Blocked IP Addresses</span>
        <button class="btn btn-ghost btn-sm" onclick="openBlockIpModal()" style="border-radius:8px"><i class="fas fa-plus"></i> Block IP</button>
    </div>
    <?php if ($blockedIps): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>IP Address</th><th>Reason</th><th>Blocked At</th><th>Expiry</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($blockedIps as $b): ?>
            <tr>
                <td style="font-family:var(--font-mono)"><?= htmlspecialchars($b['ip_address']) ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($b['reason']) ?></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, g:i A', strtotime($b['blocked_at'])) ?></td>
                <td class="text-muted" style="font-size:11px"><?= $b['expires_at'] ? date('M j, g:i A', strtotime($b['expires_at'])) : 'PERMANENT' ?></td>
                <td><button class="btn btn-ghost btn-action" onclick="unblockIp('<?= htmlspecialchars($b['ip_address']) ?>')" title="Unblock"><i class="fas fa-unlock" style="color:var(--teal)"></i></button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No IP addresses are currently blocked.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'geo-block'): ?>
<div class="admin-card">
    <div class="card-header">
        <span class="card-title">Geographic Blocking</span>
        <span class="badge badge--warn"><?= count($blockedCountries) ?> Countries Blocked</span>
    </div>
    <div style="padding:16px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:12px;margin:16px;font-size:12px;color:var(--danger)">
        <i class="fas fa-globe" style="margin-right:8px"></i> Connections from these regions will be automatically rejected.
    </div>
    <div style="display:flex;gap:12px;margin:0 16px 24px;align-items:center">
        <input type="text" class="form-input" style="width:140px" placeholder="ISO Code (e.g. CN)" id="newCountryCode" maxlength="2">
        <button class="btn btn-primary btn-sm" onclick="addBlockedCountry()" style="border-radius:10px">Restrict Region</button>
    </div>
    <?php if ($blockedCountries): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;padding:0 16px 16px">
        <?php foreach ($blockedCountries as $cc): ?>
        <span class="badge badge--danger" style="cursor:pointer;font-size:12px;padding:8px 14px" onclick="removeBlockedCountry('<?= $cc ?>')" title="Remove Restriction">
            <i class="fas fa-map-marker-alt" style="margin-right:6px"></i> <?= strtoupper($cc) ?> <i class="fas fa-times" style="margin-left:8px;opacity:0.6"></i>
        </span>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p class="text-muted" style="padding:0 16px 20px;font-size:13px">No geographic regions are currently restricted.</p><?php endif; ?>
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

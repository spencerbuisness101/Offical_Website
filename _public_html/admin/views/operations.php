<?php
/**
 * Operations View — Spencer's Website v7.0
 * System health, feature flags, cache, cron, API keys, rate limits, maintenance
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

$subtab = $_GET['sub'] ?? 'health';
$validSubs = ['health','features','cache','cron','api-keys','rate-limits','maintenance'];
if (!in_array($subtab, $validSubs)) $subtab = 'health';

$maintenanceMode = getSetting($db, 'maintenance_mode', '0') === '1';
$paymentsEnabled = getSetting($db, 'payments_enabled', '1') === '1';
?>

<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
    <a href="?tab=operations&sub=health" class="btn btn-sm <?= $subtab==='health'?'btn-primary':'btn-ghost' ?>">System Health</a>
    <a href="?tab=operations&sub=features" class="btn btn-sm <?= $subtab==='features'?'btn-primary':'btn-ghost' ?>">Feature Flags</a>
    <a href="?tab=operations&sub=cache" class="btn btn-sm <?= $subtab==='cache'?'btn-primary':'btn-ghost' ?>">Cache</a>
    <a href="?tab=operations&sub=cron" class="btn btn-sm <?= $subtab==='cron'?'btn-primary':'btn-ghost' ?>">Cron</a>
    <a href="?tab=operations&sub=api-keys" class="btn btn-sm <?= $subtab==='api-keys'?'btn-primary':'btn-ghost' ?>">API Keys</a>
    <a href="?tab=operations&sub=rate-limits" class="btn btn-sm <?= $subtab==='rate-limits'?'btn-primary':'btn-ghost' ?>">Rate Limits</a>
    <a href="?tab=operations&sub=maintenance" class="btn btn-sm <?= $subtab==='maintenance'?'btn-primary':'btn-ghost' ?>">Maintenance</a>
</div>

<?php if ($subtab === 'health'): ?>
<?php
// System health checks
$healthChecks = [];
try {
    $stmt = $db->query("SELECT 1"); $healthChecks['database'] = ['status' => 'healthy', 'detail' => 'Connected'];
} catch (Exception $e) { $healthChecks['database'] = ['status' => 'error', 'detail' => $e->getMessage()]; }

try {
    $stmt = $db->query("SHOW TABLE STATUS"); $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalSize = array_sum(array_column($tables, 'Data_length')) + array_sum(array_column($tables, 'Index_length'));
    $healthChecks['db_size'] = ['status' => $totalSize > 500*1024*1024 ? 'warning' : 'healthy', 'detail' => number_format($totalSize / 1024 / 1024, 1) . ' MB'];
} catch (Exception $e) { $healthChecks['db_size'] = ['status' => 'unknown', 'detail' => 'N/A']; }

$healthChecks['payments'] = ['status' => $paymentsEnabled ? 'healthy' : 'warning', 'detail' => $paymentsEnabled ? 'Enabled' : 'Disabled'];
$healthChecks['maintenance'] = ['status' => $maintenanceMode ? 'warning' : 'healthy', 'detail' => $maintenanceMode ? 'ACTIVE' : 'Off'];
$healthChecks['php_version'] = ['status' => 'healthy', 'detail' => phpversion()];
$healthChecks['disk_free'] = ['status' => disk_free_space('.') > 100*1024*1024 ? 'healthy' : 'warning', 'detail' => number_format(disk_free_space('.') / 1024 / 1024, 0) . ' MB free'];

// Table sizes
try {
    $dbName = getenv('DB_NAME') ?: 'thespencerwebsite_db';
    $stmt = $db->prepare("SELECT table_name, ROUND(data_length/1024) as data_kb, table_rows FROM information_schema.TABLES WHERE table_schema=? ORDER BY data_length DESC LIMIT 10");
    $stmt->execute([$dbName]); $tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tableSizes = []; }
?>
<div class="stat-grid">
    <?php foreach ($healthChecks as $name => $h): ?>
    <div class="stat-box">
        <div class="stat-value" style="font-size:14px;color:<?= $h['status']==='healthy'?'var(--teal)':($h['status']==='warning'?'var(--amber)':'var(--red)') ?>">
            <?= $h['status'] === 'healthy' ? '✓' : ($h['status'] === 'warning' ? '⚠' : '✗') ?> <?= ucfirst(str_replace('_',' ',$name)) ?>
        </div>
        <div class="stat-label"><?= htmlspecialchars($h['detail']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($tableSizes): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Largest Tables</span></div>
    <table class="data-table">
        <thead><tr><th>Table</th><th>Size (KB)</th><th>Rows</th></tr></thead>
        <tbody>
        <?php foreach ($tableSizes as $t): ?>
        <tr><td><?= htmlspecialchars($t['table_name']) ?></td><td><?= number_format($t['data_kb']) ?></td><td><?= number_format($t['table_rows']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php elseif ($subtab === 'features'): ?>
<?php
$features = [
    'feature_ai_chat' => ['label' => 'AI Chat', 'desc' => 'Enable/disable AI assistant for all users', 'default' => true],
    'feature_yaps_chat' => ['label' => 'Yaps Chat', 'desc' => 'Enable/disable real-time chat', 'default' => true],
    'feature_registration' => ['label' => 'Registration', 'desc' => 'Allow new user sign-ups', 'default' => true],
    'feature_donations' => ['label' => 'Donations', 'desc' => 'Enable donation payments', 'default' => true],
    'feature_feedback' => ['label' => 'Feedback', 'desc' => 'Allow users to submit feedback', 'default' => true],
];
foreach ($features as $key => &$f) {
    $f['enabled'] = getSetting($db, $key, $f['default'] ? '1' : '0') === '1';
}
unset($f);
?>
<div class="card">
    <div class="card-header"><span class="card-title">Feature Flags</span></div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Toggle features on/off without code changes. Changes take effect immediately.</p>
    <?php foreach ($features as $key => $f): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:0.5px solid rgba(255,255,255,0.06)">
        <div>
            <div style="font-size:14px;font-weight:500"><?= $f['label'] ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= $f['desc'] ?></div>
        </div>
        <label class="toggle">
            <input type="checkbox" <?= $f['enabled'] ? 'checked' : '' ?> onchange="toggleFeature('<?= $key ?>', this.checked)">
            <span class="slider"></span>
        </label>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($subtab === 'cache'): ?>
<?php
$cacheDir = __DIR__ . '/../../cache';
$cacheStats = ['size' => 0, 'files' => 0, 'oldest' => '', 'newest' => ''];
if (is_dir($cacheDir)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir));
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $cacheStats['size'] += $file->getSize();
            $cacheStats['files']++;
            $mtime = date('Y-m-d H:i:s', $file->getMTime());
            if (!$cacheStats['oldest'] || $mtime < $cacheStats['oldest']) $cacheStats['oldest'] = $mtime;
            if (!$cacheStats['newest'] || $mtime > $cacheStats['newest']) $cacheStats['newest'] = $mtime;
        }
    }
}
?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Cache Manager</span>
        <button class="btn btn-danger btn-sm" onclick="clearCache('all')"><i class="fas fa-trash"></i> Clear All</button>
    </div>
    <div class="stat-grid">
        <div class="stat-box"><div class="stat-value"><?= number_format($cacheStats['size']/1024,1) ?></div><div class="stat-label">KB Total</div></div>
        <div class="stat-box"><div class="stat-value"><?= $cacheStats['files'] ?></div><div class="stat-label">Files</div></div>
    </div>
    <p style="font-size:12px;color:var(--text-muted)">Oldest: <?= $cacheStats['oldest'] ?: 'N/A' ?> &middot; Newest: <?= $cacheStats['newest'] ?: 'N/A' ?></p>
</div>

<?php elseif ($subtab === 'cron'): ?>
<?php
try {
    $db->exec("CREATE TABLE IF NOT EXISTS cron_log (id INT AUTO_INCREMENT PRIMARY KEY, job_name VARCHAR(100), status VARCHAR(20), message TEXT, started_at TIMESTAMP, finished_at TIMESTAMP NULL, INDEX idx_job (job_name))");
    $stmt = $db->query("SELECT id, job_name, status, message, started_at, finished_at FROM cron_log ORDER BY started_at DESC LIMIT 20"); $cronLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $cronLog = []; }

$cronJobs = [
    ['name' => 'check_subscriptions.php', 'path' => 'cron/check_subscriptions.php', 'desc' => 'Checks and updates subscription statuses'],
];
?>
<div class="card">
    <div class="card-header"><span class="card-title">Cron Monitor</span></div>
    <table class="data-table">
        <thead><tr><th>Job</th><th>Description</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($cronJobs as $j): ?>
        <tr>
            <td><span class="tag tag-violet"><?= $j['name'] ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= $j['desc'] ?></td>
            <td><button class="btn btn-ghost btn-sm" onclick="runCron('<?= $j['path'] ?>')"><i class="fas fa-play"></i> Run Now</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($cronLog): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Cron Log</span></div>
    <table class="data-table">
        <thead><tr><th>Job</th><th>Status</th><th>Started</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach ($cronLog as $cl): ?>
        <tr>
            <td><?= htmlspecialchars($cl['job_name']) ?></td>
            <td><span class="tag <?= $cl['status']==='success'?'tag-teal':'tag-red' ?>"><?= $cl['status'] ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= $cl['started_at'] ?></td>
            <td style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($cl['message'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php elseif ($subtab === 'api-keys'): ?>
<?php
$envKeys = [
    'GROQ_API_KEY' => ['label' => 'Groq (AI)', 'prefix' => 'gsk_'],
    'STRIPE_SECRET_KEY' => ['label' => 'Stripe (Secret)', 'prefix' => 'sk_'],
    'STRIPE_PUBLISHABLE_KEY' => ['label' => 'Stripe (Public)', 'prefix' => 'pk_'],
    'RECAPTCHA_SITE_KEY' => ['label' => 'reCAPTCHA (Site)', 'prefix' => '6L'],
    'RECAPTCHA_SECRET_KEY' => ['label' => 'reCAPTCHA (Secret)', 'prefix' => '6L'],
];
foreach ($envKeys as $key => &$info) {
    $val = getenv($key) ?: '';
    $info['masked'] = $val ? substr($val, 0, 4) . str_repeat('*', max(0, strlen($val) - 8)) . substr($val, -4) : 'Not set';
    $info['configured'] = !empty($val);
}
unset($info);
?>
<div class="card">
    <div class="card-header"><span class="card-title">API Key Status</span></div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Keys are masked for security. Last 4 characters shown.</p>
    <table class="data-table">
        <thead><tr><th>Service</th><th>Key</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($envKeys as $key => $info): ?>
        <tr>
            <td><?= $info['label'] ?></td>
            <td style="font-family:monospace;font-size:12px"><?= $info['masked'] ?></td>
            <td><span class="tag <?= $info['configured']?'tag-teal':'tag-red' ?>"><?= $info['configured'] ? 'Configured' : 'Missing' ?></span></td>
            <td><button class="btn btn-ghost btn-sm" onclick="rotateKey('<?= $key ?>')"><i class="fas fa-key"></i> Rotate</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($subtab === 'rate-limits'): ?>
<?php
$rateLimits = json_decode(getSetting($db, 'rate_limits', '{}'), true) ?: [];
$defaultLimits = [
    'login' => ['max' => 5, 'window' => 60],
    'register' => ['max' => 3, 'window' => 300],
    'webhook' => ['max' => 100, 'window' => 60],
    'api_general' => ['max' => 30, 'window' => 60],
    'chat_message' => ['max' => 10, 'window' => 30],
];
foreach ($defaultLimits as $k => $v) {
    if (!isset($rateLimits[$k])) $rateLimits[$k] = $v;
}
?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Rate Limit Configuration</span>
        <button class="btn btn-primary btn-sm" onclick="saveRateLimits()"><i class="fas fa-save"></i> Save</button>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Adjust rate limits per endpoint. Changes take effect immediately.</p>
    <table class="data-table">
        <thead><tr><th>Endpoint</th><th>Max Requests</th><th>Window (seconds)</th></tr></thead>
        <tbody>
        <?php foreach ($rateLimits as $endpoint => $limits): ?>
        <tr>
            <td><span class="tag tag-violet"><?= $endpoint ?></span></td>
            <td><input class="form-input" style="width:80px" type="number" value="<?= $limits['max'] ?? 5 ?>" data-endpoint="<?= $endpoint ?>" data-field="max"></td>
            <td><input class="form-input" style="width:80px" type="number" value="<?= $limits['window'] ?? 60 ?>" data-endpoint="<?= $endpoint ?>" data-field="window"></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($subtab === 'maintenance'): ?>
<?php
$maintenanceMessage = getSetting($db, 'maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
$maintenanceTitle = getSetting($db, 'maintenance_title', 'Under Maintenance');
$maintenanceEta = getSetting($db, 'maintenance_eta', '');
?>
<div class="card" style="border-left:4px solid <?= $maintenanceMode ? 'var(--amber)' : 'var(--teal)' ?>">
    <div class="card-header">
        <span class="card-title">Maintenance Mode</span>
        <label class="toggle">
            <input type="checkbox" <?= $maintenanceMode ? 'checked' : '' ?> onchange="toggleMaintenance()">
            <span class="slider"></span>
        </label>
    </div>
    <div class="form-group">
        <label class="form-label">Title</label>
        <input class="form-input" id="maintTitle" value="<?= htmlspecialchars($maintenanceTitle) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Message</label>
        <textarea class="form-input" id="maintMessage" rows="3"><?= htmlspecialchars($maintenanceMessage) ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label">ETA</label>
        <input class="form-input" id="maintEta" value="<?= htmlspecialchars($maintenanceEta) ?>" placeholder="e.g. 2 hours">
    </div>
    <button class="btn btn-primary" onclick="saveMaintenanceSettings()"><i class="fas fa-save"></i> Save Settings</button>
</div>
<?php endif; ?>

<script>
async function toggleFeature(key, enabled) {
    const r = await apiCall('admin/api/setting_update.php', { setting: key, value: enabled ? '1' : '0', csrf_token: getCsrf() });
    toast(r.success ? 'Feature updated' : 'Failed: ' + (r.message||''), r.success ? 'success' : 'error');
}

async function clearCache(type) {
    if (!confirm('Clear all cache files?')) return;
    const r = await apiCall('admin/api/cache_action.php', { action: 'clear_all', csrf_token: getCsrf() });
    toast(r.success ? 'Cache cleared' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function runCron(path) {
    if (!confirm('Run ' + path + ' now?')) return;
    const r = await apiCall('admin/api/cron_trigger.php', { job: path, csrf_token: getCsrf() });
    toast(r.message || (r.success ? 'Job triggered' : 'Failed'), r.success ? 'success' : 'error');
}

async function rotateKey(key) {
    const val = prompt('Enter new value for ' + key + ':');
    if (!val) return;
    const r = await apiCall('admin/api/key_rotate.php', { key, value: val, csrf_token: getCsrf() });
    toast(r.success ? 'Key updated' : 'Failed', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function saveRateLimits() {
    const inputs = document.querySelectorAll('[data-endpoint]');
    const limits = {};
    inputs.forEach(inp => {
        const ep = inp.dataset.endpoint;
        const field = inp.dataset.field;
        if (!limits[ep]) limits[ep] = {};
        limits[ep][field] = parseInt(inp.value) || 0;
    });
    const r = await apiCall('admin/api/rate_limit_config.php', { limits: JSON.stringify(limits), csrf_token: getCsrf() });
    toast(r.success ? 'Rate limits saved' : 'Failed', r.success ? 'success' : 'error');
}

async function toggleMaintenance() {
    const r = await apiCall('admin/api/setting_update.php', { setting: 'maintenance_mode', value: 'toggle', csrf_token: getCsrf() });
    toast(r.success !== false ? 'Maintenance toggled' : 'Failed', r.success !== false ? 'success' : 'error');
    setTimeout(() => location.reload(), 800);
}

async function saveMaintenanceSettings() {
    const r1 = await apiCall('admin/api/setting_update.php', { setting: 'maintenance_title', value: document.getElementById('maintTitle').value, csrf_token: getCsrf() });
    const r2 = await apiCall('admin/api/setting_update.php', { setting: 'maintenance_message', value: document.getElementById('maintMessage').value, csrf_token: getCsrf() });
    const r3 = await apiCall('admin/api/setting_update.php', { setting: 'maintenance_eta', value: document.getElementById('maintEta').value, csrf_token: getCsrf() });
    toast(r3.success ? 'Settings saved' : 'Failed', r3.success ? 'success' : 'error');
}
</script>

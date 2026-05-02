<?php
/**
 * Admin Dashboard View — Spencer's Website v7.0
 * Dark futuristic design with glassmorphism cards
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

// Stats
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users"); $userCount = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP()-1800"); $activeUsers = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM page_views WHERE DATE(timestamp)=CURDATE()"); $todayVisitors = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()"); $newUsersToday = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(),INTERVAL 7 DAY)"); $newUsers7d = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP()-1800"); $activeSessions = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM refund_requests WHERE status='pending'"); $pendingRefunds = (int)$stmt->fetchColumn();
    $stmt = $db->query("SELECT SUM(amount_cents) FROM payments WHERE status='completed'"); $totalRevenue = (int)$stmt->fetchColumn();
    // Blocked IPs
    $stmt = $db->query("SELECT COUNT(*) FROM blocked_ips WHERE expires_at IS NULL OR expires_at > NOW()"); $blockedIps = (int)$stmt->fetchColumn();
    // Pending ideas
    $stmt = $db->query("SELECT COUNT(*) FROM contributor_ideas WHERE status='pending'"); $pendingIdeas = (int)$stmt->fetchColumn();
    // Pending PFPs
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE pfp_type='pending'"); $pendingPfps = (int)$stmt->fetchColumn();
    // Payments enabled?
    $paymentsEnabled = getSetting($db, 'payments_enabled', '1') === '1';
    // Maintenance mode?
    $maintenanceMode = getSetting($db, 'maintenance_mode', '0') === '1';
} catch (Exception $e) {
    $userCount = $activeUsers = $todayVisitors = $newUsersToday = $newUsers7d = $activeSessions = $pendingRefunds = $totalRevenue = $blockedIps = $pendingIdeas = $pendingPfps = 0;
    $paymentsEnabled = true; $maintenanceMode = false;
}

// Active pages
try {
    $stmt = $db->query("SELECT current_page, COUNT(*) as cnt FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP()-300 GROUP BY current_page ORDER BY cnt DESC LIMIT 8");
    $activePages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $activePages = []; }

// Hourly traffic
try {
    $stmt = $db->query("SELECT HOUR(timestamp) as h, COUNT(*) as v FROM page_views WHERE DATE(timestamp)=CURDATE() GROUP BY HOUR(timestamp) ORDER BY h");
    $hourlyRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hourly = array_fill(0, 24, 0);
    foreach ($hourlyRaw as $r) $hourly[(int)$r['h']] = (int)$r['v'];
    $trafficLabels = ['12AM','3AM','6AM','9AM','12PM','3PM','6PM','9PM'];
    $trafficData = [
        $hourly[0]+$hourly[1]+$hourly[2], $hourly[3]+$hourly[4]+$hourly[5],
        $hourly[6]+$hourly[7]+$hourly[8], $hourly[9]+$hourly[10]+$hourly[11],
        $hourly[12]+$hourly[13]+$hourly[14], $hourly[15]+$hourly[16]+$hourly[17],
        $hourly[18]+$hourly[19]+$hourly[20], $hourly[21]+$hourly[22]+$hourly[23]
    ];
} catch (Exception $e) {
    $trafficLabels = ['12AM','3AM','6AM','9AM','12PM','3PM','6PM','9PM'];
    $trafficData = [0,0,0,0,0,0,0,0];
}

// Recent audit log
try {
    $stmt = $db->query("SELECT id, admin_id, admin_username, action, target_user_id, details, ip_address, created_at FROM admin_audit_log ORDER BY created_at DESC LIMIT 8");
    $recentAudit = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recentAudit = []; }
?>

<!-- Stat Grid -->
<div class="stats-row">
    <div class="stat-tile">
        <div class="stat-icon violet"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($userCount) ?></div>
        <div class="stat-label">Total Users</div>
        <div style="margin-top:8px;font-size:11px;color:var(--teal)"><?= $newUsersToday ?> today &middot; <?= $newUsers7d ?> this week</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon teal"><i class="fas fa-signal"></i></div>
        <div class="stat-value teal"><?= $activeUsers ?></div>
        <div class="stat-label">Online Members</div>
        <div style="margin-top:8px;font-size:11px;color:var(--text-muted)"><?= $activeSessions ?> total sessions</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon pink"><i class="fas fa-eye"></i></div>
        <div class="stat-value"><?= number_format($todayVisitors) ?></div>
        <div class="stat-label">Page Views Today</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon violet" style="background:rgba(123,110,246,0.15)"><i class="fas fa-credit-card"></i></div>
        <div class="stat-value accent">$<?= number_format($totalRevenue / 100, 2) ?></div>
        <div class="stat-label">Global Revenue</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon" style="background:rgba(251,191,36,0.1);color:var(--warning)"><i class="fas fa-undo"></i></div>
        <div class="stat-value" style="color:<?= $pendingRefunds > 0 ? 'var(--amber)' : 'var(--text)' ?>"><?= $pendingRefunds ?></div>
        <div class="stat-label">Pending Refunds</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger)"><i class="fas fa-shield-alt"></i></div>
        <div class="stat-value" style="color:var(--red)"><?= $blockedIps ?></div>
        <div class="stat-label">Security Blocks</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="admin-card">
    <div class="card-header"><span class="card-title">Administrative Actions</span></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="?tab=content" class="btn btn-ghost btn-sm"><i class="fas fa-lightbulb"></i> Review Ideas (<?= $pendingIdeas ?>)</a>
        <a href="?tab=users" class="btn btn-ghost btn-sm"><i class="fas fa-user-cog"></i> Review PFPs (<?= $pendingPfps ?>)</a>
        <a href="?tab=payments" class="btn btn-ghost btn-sm"><i class="fas fa-credit-card"></i> Payments</a>
        <a href="?tab=threats" class="btn btn-ghost btn-sm"><i class="fas fa-shield-alt"></i> Threat Monitor</a>
        <a href="?tab=announcements" class="btn btn-ghost btn-sm"><i class="fas fa-bullhorn"></i> Announcements</a>
        <button class="btn btn-sm <?= $paymentsEnabled ? 'btn-teal' : 'btn-danger' ?>" onclick="togglePayments()" style="border-radius:10px"><?= $paymentsEnabled ? '✓ Payments Online' : '⚠ Payments Offline' ?></button>
        <button class="btn btn-sm <?= $maintenanceMode ? 'btn-danger' : 'btn-ghost' ?>" onclick="toggleMaintenance()" style="border-radius:10px"><?= $maintenanceMode ? '🚧 Maintenance Active' : 'Maintenance Inactive' ?></button>
    </div>
</div>

<!-- Traffic Chart -->
<div class="admin-card">
    <div class="card-header">
        <span class="card-title">Traffic Activity (Today)</span>
        <button class="btn btn-ghost btn-sm" onclick="location.reload()" style="width:32px;height:32px;padding:0"><i class="fas fa-sync-alt"></i></button>
    </div>
    <div style="height:220px">
        <canvas id="trafficChart"></canvas>
    </div>
</div>

<!-- Two-column: Active Pages + Recent Audit -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:24px">
    <div class="admin-card">
        <div class="card-header"><span class="card-title">Current Activity</span></div>
        <?php if ($activePages): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Active Page</th><th>Sessions</th></tr></thead>
                <tbody>
                <?php foreach ($activePages as $p): ?>
                    <tr>
                        <td style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars(basename($p['current_page'] ?? 'unknown')) ?></td>
                        <td><span class="badge badge--teal"><?= $p['cnt'] ?> Active</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><p class="text-muted" style="font-size:13px">No current activity detected.</p><?php endif; ?>
    </div>
    <div class="admin-card">
        <div class="card-header"><span class="card-title">Recent Administrative Actions</span></div>
        <?php if ($recentAudit): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Action</th><th>Operator</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentAudit as $a): ?>
                    <tr>
                        <td><span class="badge badge--violet"><?= htmlspecialchars($a['action']) ?></span></td>
                        <td><?= htmlspecialchars($a['admin_username']) ?></td>
                        <td class="text-muted" style="font-size:11px"><?= date('g:i A', strtotime($a['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><p class="text-muted" style="font-size:13px">No recent audit logs found.</p><?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
new Chart(document.getElementById('trafficChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trafficLabels) ?>,
        datasets: [{
            label: 'Page Views',
            data: <?= json_encode($trafficData) ?>,
            borderColor: '#7B6EF6',
            backgroundColor: 'rgba(123,110,246,0.08)',
            borderWidth: 2, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: '#7B6EF6'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } }
        }
    }
});

async function togglePayments() {
    const r = await apiCall('admin/api/setting_update.php', { setting: 'payments_enabled', value: 'toggle', csrf_token: getCsrf() });
    toast(r.success ? 'Payments toggled' : 'Failed: ' + (r.message||''), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
async function toggleMaintenance() {
    const r = await apiCall('admin/api/setting_update.php', { setting: 'maintenance_mode', value: 'toggle', csrf_token: getCsrf() });
    toast(r.success ? 'Maintenance toggled' : 'Failed: ' + (r.message||''), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

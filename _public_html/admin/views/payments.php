<?php
/**
 * Payments View — Spencer's Website v7.0
 * Payment management + IP viewer + webhook monitor
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

// Sub-tab
$subtab = $_GET['sub'] ?? 'transactions';
$validSubs = ['transactions', 'ip-viewer', 'webhooks', 'refunds'];
if (!in_array($subtab, $validSubs)) $subtab = 'transactions';

$paymentsEnabled = getSetting($db, 'payments_enabled', '1') === '1';
?>

<!-- Sub-tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px">
    <a href="?tab=payments&sub=transactions" class="btn btn-sm <?= $subtab==='transactions'?'btn-primary':'btn-ghost' ?>">Transactions</a>
    <a href="?tab=payments&sub=ip-viewer" class="btn btn-sm <?= $subtab==='ip-viewer'?'btn-primary':'btn-ghost' ?>">IP Viewer</a>
    <a href="?tab=payments&sub=webhooks" class="btn btn-sm <?= $subtab==='webhooks'?'btn-primary':'btn-ghost' ?>">Webhooks</a>
    <a href="?tab=payments&sub=refunds" class="btn btn-sm <?= $subtab==='refunds'?'btn-primary':'btn-ghost' ?>">Refunds</a>
    <span style="margin-left:auto"><span class="tag <?= $paymentsEnabled ? 'tag-teal' : 'tag-red' ?>"><?= $paymentsEnabled ? 'Payments Enabled' : 'Payments Disabled' ?></span></span>
</div>

<?php if ($subtab === 'transactions'): ?>
<?php
try {
    $stmt = $db->query("SELECT ps.id, ps.user_id, ps.plan_type, ps.amount_cents, ps.status, ps.ip_address, ps.created_at, u.username FROM payment_sessions ps LEFT JOIN users u ON ps.user_id = u.id ORDER BY ps.created_at DESC LIMIT 50");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $transactions = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Recent Transactions</span></div>
    <?php if ($transactions): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Status</th><th>IP Address</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['username'] ?? 'Guest') ?></td>
            <td><span class="tag tag-violet"><?= htmlspecialchars($t['plan_type'] ?? 'N/A') ?></span></td>
            <td>$<?= number_format(($t['amount_cents'] ?? 0) / 100, 2) ?></td>
            <td><span class="tag <?= $t['status']==='completed'?'tag-teal':($t['status']==='pending'?'tag-amber':'tag-red') ?>"><?= htmlspecialchars($t['status'] ?? 'unknown') ?></span></td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($t['ip_address'] ?? 'N/A') ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($t['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No transactions found.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'ip-viewer'): ?>
<?php
// IP-based transaction analysis
try {
    $stmt = $db->query("
        SELECT ip_address, COUNT(*) as tx_count, COUNT(DISTINCT user_id) as user_count,
               MIN(created_at) as first_tx, MAX(created_at) as last_tx
        FROM payment_sessions WHERE ip_address IS NOT NULL AND ip_address != ''
        GROUP BY ip_address ORDER BY tx_count DESC LIMIT 50
    ");
    $ipStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ipStats = []; }

// Suspicious IPs (3+ different accounts)
$suspiciousIps = array_filter($ipStats, fn($ip) => $ip['user_count'] >= 3);
?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Payment IP Addresses</span>
        <span class="tag tag-amber"><?= count($suspiciousIps) ?> suspicious</span>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">IPs with 3+ different accounts are flagged as suspicious for potential fraud.</p>
    <?php if ($ipStats): ?>
    <table class="data-table">
        <thead><tr><th>IP Address</th><th>Transactions</th><th>Unique Users</th><th>Risk</th><th>First Seen</th><th>Last Seen</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($ipStats as $ip): ?>
        <tr>
            <td style="font-family:monospace"><?= htmlspecialchars($ip['ip_address']) ?></td>
            <td><?= $ip['tx_count'] ?></td>
            <td><?= $ip['user_count'] ?></td>
            <td>
                <?php if ($ip['user_count'] >= 3): ?><span class="tag tag-red">HIGH</span>
                <?php elseif ($ip['user_count'] >= 2): ?><span class="tag tag-amber">MEDIUM</span>
                <?php else: ?><span class="tag tag-teal">LOW</span><?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j', strtotime($ip['first_tx'])) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($ip['last_tx'])) ?></td>
            <td><button class="btn btn-ghost btn-sm" onclick="viewIpDetails('<?= htmlspecialchars($ip['ip_address']) ?>')"><i class="fas fa-search"></i></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No IP data available.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'webhooks'): ?>
<?php
try {
    $stmt = $db->query("SELECT id, event_type, status, created_at FROM stripe_webhook_events ORDER BY created_at DESC LIMIT 50");
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $webhooks = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Stripe Webhook Events</span></div>
    <?php if ($webhooks): ?>
    <table class="data-table">
        <thead><tr><th>Event Type</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($webhooks as $w): ?>
        <tr>
            <td><span class="tag tag-violet"><?= htmlspecialchars($w['event_type'] ?? 'unknown') ?></span></td>
            <td><span class="tag <?= $w['status']==='processed'?'tag-teal':'tag-red' ?>"><?= htmlspecialchars($w['status']) ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($w['created_at'])) ?></td>
            <td>
                <?php if ($w['status'] !== 'processed'): ?>
                <button class="btn btn-ghost btn-sm" onclick="retryWebhook(<?= $w['id'] ?>)">Retry</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No webhook events recorded.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'refunds'): ?>
<?php
try {
    $stmt = $db->query("SELECT rr.id, rr.user_id, rr.amount_cents, rr.reason, rr.status, rr.created_at, u.username FROM refund_requests rr LEFT JOIN users u ON rr.user_id = u.id ORDER BY rr.created_at DESC LIMIT 50");
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $refunds = []; }
?>
<div class="card">
    <div class="card-header"><span class="card-title">Refund Requests</span></div>
    <?php if ($refunds): ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($refunds as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['username'] ?? 'Unknown') ?></td>
            <td>$<?= number_format(($r['amount_cents'] ?? 0) / 100, 2) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['reason'] ?? 'N/A') ?></td>
            <td><span class="tag <?= $r['status']==='approved'?'tag-teal':($r['status']==='pending'?'tag-amber':'tag-red') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M j, g:i A', strtotime($r['created_at'])) ?></td>
            <td>
                <?php if ($r['status'] === 'pending'): ?>
                <button class="btn btn-teal btn-sm" onclick="processRefund(<?= $r['id'] ?>,'approve')">Approve</button>
                <button class="btn btn-danger btn-sm" onclick="processRefund(<?= $r['id'] ?>,'deny')">Deny</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No refund requests.</p><?php endif; ?>
</div>
<?php endif; ?>

<script>
async function viewIpDetails(ip) {
    const r = await fetch('admin/api/user_analytics.php?ip_lookup=' + encodeURIComponent(ip));
    const data = await r.json();
    if (!data.success) { toast('Failed to load IP details', 'error'); return; }
    let html = '<div class="modal-title">IP Address: ' + ip + '</div>';
    html += '<h4 style="font-size:12px;color:var(--text-muted);margin:12px 0 6px;text-transform:uppercase">Transactions from this IP</h4>';
    if (data.transactions?.length) {
        html += '<table class="data-table"><thead><tr><th>User</th><th>Amount</th><th>Date</th></tr></thead><tbody>';
        data.transactions.forEach(t => { html += '<tr><td>' + (t.username||'Guest') + '</td><td>$' + (t.amount_cents/100).toFixed(2) + '</td><td style="color:var(--text-muted)">' + t.created_at + '</td></tr>'; });
        html += '</tbody></table>';
    } else html += '<p style="color:var(--text-muted)">No transactions</p>';
    html += '<h4 style="font-size:12px;color:var(--text-muted);margin:12px 0 6px;text-transform:uppercase">Accounts using this IP</h4>';
    if (data.accounts?.length) {
        html += data.accounts.map(a => '<div style="padding:4px 0"><span class="tag tag-violet">' + a.username + '</span> <span style="color:var(--text-muted);font-size:12px">(' + a.role + ')</span></div>').join('');
    } else html += '<p style="color:var(--text-muted)">No accounts</p>';
    html += '<div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Close</button></div>';
    openModal(html);
}

async function retryWebhook(id) {
    const r = await apiCall('admin/api/webhook_retry.php', { id, csrf_token: getCsrf() });
    toast(r.message || (r.success ? 'Retried' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function processRefund(id, action) {
    const r = await apiCall('admin/api/user_analytics.php', { action: 'refund_' + action, refund_id: id, csrf_token: getCsrf() });
    toast(r.message || (r.success ? 'Done' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}
</script>

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
<div style="display:flex;gap:10px;margin-bottom:24px;align-items:center">
    <a href="?tab=payments&sub=transactions" class="chip <?= $subtab==='transactions'?'active':'' ?>">Ledger</a>
    <a href="?tab=payments&sub=ip-viewer" class="chip <?= $subtab==='ip-viewer'?'active':'' ?>">Geo-Telemetry</a>
    <a href="?tab=payments&sub=webhooks" class="chip <?= $subtab==='webhooks'?'active':'' ?>">Signal Webhooks</a>
    <a href="?tab=payments&sub=refunds" class="chip <?= $subtab==='refunds'?'active':'' ?>">Resource Returns</a>
    <div style="margin-left:auto">
        <span class="badge badge--<?= $paymentsEnabled ? 'teal' : 'danger' ?>" style="padding:6px 12px;font-size:11px">
            <i class="fas fa-circle" style="font-size:8px;margin-right:6px"></i>
            <?= $paymentsEnabled ? 'GATEWAY ONLINE' : 'GATEWAY OFFLINE' ?>
        </span>
    </div>
</div>

<?php if ($subtab === 'transactions'): ?>
<?php
try {
    $stmt = $db->query("SELECT ps.id, ps.user_id, ps.plan_type, ps.amount_cents, ps.status, ps.ip_address, ps.created_at, u.username FROM payment_sessions ps LEFT JOIN users u ON ps.user_id = u.id ORDER BY ps.created_at DESC LIMIT 50");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $transactions = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Recent Financial Exchange Ledger</span></div>
    <?php if ($transactions): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Operator</th><th>Protocol</th><th>Magnitude</th><th>Signal Status</th><th>Source Node</th><th>Timestamp</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
            <tr>
                <td><div style="font-weight:500;color:var(--text-soft)"><?= htmlspecialchars($t['username'] ?? 'Anonymous') ?></div></td>
                <td><span class="badge badge--violet"><?= strtoupper(htmlspecialchars($t['plan_type'] ?? 'DATA')) ?></span></td>
                <td><span style="color:var(--accent);font-weight:600">$<?= number_format(($t['amount_cents'] ?? 0) / 100, 2) ?></span></td>
                <td><span class="badge badge--<?= $t['status']==='completed'?'teal':($t['status']==='pending'?'warn':'danger') ?>"><?= strtoupper(htmlspecialchars($t['status'] ?? 'unknown')) ?></span></td>
                <td style="font-family:var(--font-mono);font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($t['ip_address'] ?? '0.0.0.0') ?></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, g:i A', strtotime($t['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No financial exchanges found in the ledger.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'ip-viewer'): ?>
<?php
try {
    $stmt = $db->query("
        SELECT ip_address, COUNT(*) as tx_count, COUNT(DISTINCT user_id) as user_count,
               MIN(created_at) as first_tx, MAX(created_at) as last_tx
        FROM payment_sessions WHERE ip_address IS NOT NULL AND ip_address != ''
        GROUP BY ip_address ORDER BY tx_count DESC LIMIT 50
    ");
    $ipStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ipStats = []; }
$suspiciousIps = array_filter($ipStats, fn($ip) => $ip['user_count'] >= 3);
?>
<div class="admin-card">
    <div class="card-header">
        <span class="card-title">Network Origin Telemetry</span>
        <span class="badge badge--warn"><?= count($suspiciousIps) ?> Anomalies Found</span>
    </div>
    <div style="padding:16px;background:rgba(251,191,36,0.05);border:1px solid rgba(251,191,36,0.15);border-radius:12px;margin:16px;font-size:12px;color:var(--warning)">
        <i class="fas fa-exclamation-triangle" style="margin-right:8px"></i> Nodes with 3+ unique identifiers are flagged for biometric inconsistency (Potential Fraud).
    </div>
    <?php if ($ipStats): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Origin IP</th><th>Exchanges</th><th>IDs</th><th>Threat Vector</th><th>Last Seen</th><th>Protocol</th></tr></thead>
            <tbody>
            <?php foreach ($ipStats as $ip): ?>
            <tr>
                <td style="font-family:var(--font-mono)"><?= htmlspecialchars($ip['ip_address']) ?></td>
                <td><?= $ip['tx_count'] ?></td>
                <td><?= $ip['user_count'] ?></td>
                <td>
                    <?php if ($ip['user_count'] >= 3): ?><span class="badge badge--danger">CRITICAL</span>
                    <?php elseif ($ip['user_count'] >= 2): ?><span class="badge badge--warn">ELEVATED</span>
                    <?php else: ?><span class="badge badge--teal">SECURE</span><?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, g:i A', strtotime($ip['last_tx'])) ?></td>
                <td><button class="btn btn-ghost btn-action" onclick="viewIpDetails('<?= htmlspecialchars($ip['ip_address']) ?>')"><i class="fas fa-radar"></i></button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No origin telemetry available.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'webhooks'): ?>
<?php
try {
    $stmt = $db->query("SELECT id, event_type, status, created_at FROM stripe_webhook_events ORDER BY created_at DESC LIMIT 50");
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $webhooks = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Inter-System Signal Webhooks</span></div>
    <?php if ($webhooks): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Protocol Event</th><th>Signal Integrity</th><th>Timestamp</th><th>Protocol Override</th></tr></thead>
            <tbody>
            <?php foreach ($webhooks as $w): ?>
            <tr>
                <td><span class="badge badge--violet"><?= htmlspecialchars($w['event_type'] ?? 'SIGNAL_LOST') ?></span></td>
                <td><span class="badge badge--<?= $w['status']==='processed'?'teal':'danger' ?>"><?= strtoupper(htmlspecialchars($w['status'])) ?></span></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, g:i A', strtotime($w['created_at'])) ?></td>
                <td>
                    <?php if ($w['status'] !== 'processed'): ?>
                    <button class="btn btn-ghost btn-sm" onclick="retryWebhook(<?= $w['id'] ?>)" style="border-radius:8px">Retry Uplink</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No signal webhooks recorded.</p><?php endif; ?>
</div>

<?php elseif ($subtab === 'refunds'): ?>
<?php
try {
    $stmt = $db->query("SELECT rr.id, rr.user_id, rr.amount_cents, rr.reason, rr.status, rr.created_at, u.username FROM refund_requests rr LEFT JOIN users u ON rr.user_id = u.id ORDER BY rr.created_at DESC LIMIT 50");
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $refunds = []; }
?>
<div class="admin-card">
    <div class="card-header"><span class="card-title">Resource Return Authorization</span></div>
    <?php if ($refunds): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Entity</th><th>Magnitude</th><th>Justification</th><th>Authorization</th><th>Logged</th><th>Override</th></tr></thead>
            <tbody>
            <?php foreach ($refunds as $r): ?>
            <tr>
                <td style="font-weight:500;color:var(--text-soft)"><?= htmlspecialchars($r['username'] ?? 'UNKNOWN') ?></td>
                <td style="color:var(--accent)">$<?= number_format(($r['amount_cents'] ?? 0) / 100, 2) ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:11px" title="<?= htmlspecialchars($r['reason'] ?? 'None') ?>">
                    <?= htmlspecialchars($r['reason'] ?? 'No justification provided.') ?>
                </td>
                <td><span class="badge badge--<?= $r['status']==='approved'?'teal':($r['status']==='pending'?'warn':'danger') ?>"><?= strtoupper(htmlspecialchars($r['status'])) ?></span></td>
                <td class="text-muted" style="font-size:11px"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-ghost btn-action" onclick="processRefund(<?= $r['id'] ?>,'approve')"><i class="fas fa-check" style="color:var(--teal)"></i></button>
                        <button class="btn btn-ghost btn-action" onclick="processRefund(<?= $r['id'] ?>,'deny')"><i class="fas fa-times" style="color:var(--danger)"></i></button>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?><p class="text-muted" style="padding:20px;font-size:13px">No resource return requests found.</p><?php endif; ?>
</div>
<?php endif; ?>

<script>
async function viewIpDetails(ip) {
    const r = await fetch('admin/api/user_analytics.php?ip_lookup=' + encodeURIComponent(ip));
    const data = await r.json();
    if (!data.success) { toast('Failed to load IP details', 'error'); return; }
    let html = `
        <div class="card-title" style="margin-bottom:20px">Node Telemetry: ${esc(ip)}</div>
        <h4 class="form-label" style="margin-bottom:12px;color:var(--accent)">Financial Records</h4>
    `;
    if (data.transactions?.length) {
        html += `
            <div class="admin-table-wrap" style="margin-bottom:20px">
                <table class="admin-table">
                    <thead><tr><th>Operator</th><th>Magnitude</th><th>Timestamp</th></tr></thead>
                    <tbody style="font-size:11px">
                        ${data.transactions.map(t => `
                            <tr>
                                <td>${esc(t.username||'Anonymous')}</td>
                                <td style="color:var(--accent)">$${(t.amount_cents/100).toFixed(2)}</td>
                                <td class="text-muted">${esc(t.created_at)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } else html += '<p class="text-muted" style="font-size:12px;margin-bottom:20px">No financial exchanges found for this node.</p>';
    
    html += '<h4 class="form-label" style="margin-bottom:12px;color:var(--violet)">Linked Identities</h4>';
    if (data.accounts?.length) {
        html += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">';
        html += data.accounts.map(a => `<span class="badge badge--violet">${esc(a.username)} <span class="text-faint" style="font-size:9px">(${esc(a.role)})</span></span>`).join('');
        html += '</div>';
    } else html += '<p class="text-muted" style="font-size:12px;margin-bottom:24px">No unique identities linked to this node.</p>';
    
    html += '<div class="modal-actions"><button class="btn btn-primary" onclick="closeModal()" style="border-radius:10px">Close Signal</button></div>';
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

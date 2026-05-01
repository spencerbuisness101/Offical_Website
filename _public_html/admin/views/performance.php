<?php
/**
 * Performance View — Spencer's Website v7.0
 */
if (!$db) { echo '<div class="card"><p style="color:var(--red)">Database connection failed.</p></div>'; return; }

// Slow pages
try {
    $stmt = $db->query("SELECT page_url, AVG(load_time) as avg_ms, COUNT(*) as samples FROM performance_metrics WHERE load_time > 2000 GROUP BY page_url ORDER BY avg_ms DESC LIMIT 15");
    $slowPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $slowPages = []; }

// Daily avg load times (7 days)
try {
    $stmt = $db->query("SELECT DATE(timestamp) as day, ROUND(AVG(load_time)) as avg_ms, COUNT(*) as samples FROM performance_metrics WHERE timestamp >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY DATE(timestamp) ORDER BY day");
    $dailyLoad = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $dailyLoad = []; }

// DB table sizes
try {
    $dbName = getenv('DB_NAME') ?: 'thespencerwebsite_db';
    $stmt = $db->prepare("SELECT table_name, ROUND(data_length/1024) as data_kb, ROUND(index_length/1024) as index_kb, table_rows FROM information_schema.TABLES WHERE table_schema=? ORDER BY data_length DESC LIMIT 10");
    $stmt->execute([$dbName]); $tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tableSizes = []; }
?>

<!-- Performance Stats -->
<div class="stat-grid">
    <div class="stat-box">
        <div class="stat-value"><?= count($slowPages) ?></div>
        <div class="stat-label">Slow Pages (>2s)</div>
    </div>
    <div class="stat-box">
        <div class="stat-value teal"><?= $dailyLoad ? round(array_sum(array_column($dailyLoad, 'avg_ms')) / count($dailyLoad)) : 0 ?>ms</div>
        <div class="stat-label">Avg Load (7d)</div>
    </div>
</div>

<!-- Load Time Chart -->
<?php if ($dailyLoad): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Average Load Time (7 days)</span></div>
    <canvas id="perfChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- Slow Pages -->
<div class="card">
    <div class="card-header"><span class="card-title">Slow Pages</span></div>
    <?php if ($slowPages): ?>
    <table class="data-table">
        <thead><tr><th>Page</th><th>Avg Load</th><th>Samples</th></tr></thead>
        <tbody>
        <?php foreach ($slowPages as $p): ?>
        <tr>
            <td style="font-size:13px"><?= htmlspecialchars(basename($p['page_url'])) ?></td>
            <td><span class="tag <?= $p['avg_ms']>5000?'tag-red':'tag-amber' ?>"><?= round($p['avg_ms']) ?>ms</span></td>
            <td style="color:var(--text-muted)"><?= $p['samples'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><p style="color:var(--text-muted)">No slow pages detected.</p><?php endif; ?>
</div>

<!-- Table Sizes -->
<?php if ($tableSizes): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Database Table Sizes</span></div>
    <table class="data-table">
        <thead><tr><th>Table</th><th>Data (KB)</th><th>Index (KB)</th><th>Rows</th></tr></thead>
        <tbody>
        <?php foreach ($tableSizes as $t): ?>
        <tr>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($t['table_name']) ?></td>
            <td><?= number_format($t['data_kb']) ?></td>
            <td><?= number_format($t['index_kb']) ?></td>
            <td><?= number_format($t['table_rows']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($dailyLoad): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
new Chart(document.getElementById('perfChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('M j', strtotime($d['day'])), $dailyLoad)) ?>,
        datasets: [{
            label: 'Avg Load (ms)',
            data: <?= json_encode(array_map(fn($d) => (int)$d['avg_ms'], $dailyLoad)) ?>,
            backgroundColor: 'rgba(123,110,246,0.3)',
            borderColor: '#7B6EF6',
            borderWidth: 1, borderRadius: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
            x: { grid: { display: false }, ticks: { color: '#64748b' } }
        }
    }
});
</script>
<?php endif; ?>

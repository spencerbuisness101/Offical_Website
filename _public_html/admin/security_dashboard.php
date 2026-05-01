<?php
/**
 * Security Dashboard - Phase 4 Admin View
 * 
 * Monitor security metrics:
 * - Ban evasion detection stats
 * - Rate limiting status
 * - Device fingerprinting data
 * - Suspicious activity alerts
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BanEvasionDetector.php';
require_once __DIR__ . '/../includes/RateLimit.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin.php');
    exit;
}

// Get statistics
$banStats = [];
$rateStats = [];
$recentEvasionAttempts = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Ban evasion statistics
    $banDetector = new BanEvasionDetector();
    $banStats = $banDetector->getStatistics(7);
    
    // Rate limiting statistics
    $rateLimit = new RateLimit();
    $rateStats = $rateLimit->getStatistics(24);
    
    // Recent evasion attempts
    $stmt = $db->query("
        SELECT e.*, u.username as matched_username
        FROM ban_evasion_logs e
        LEFT JOIN users u ON e.matched_user_id = u.id
        WHERE e.detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY e.detected_at DESC
        LIMIT 20
    ");
    $recentEvasionAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Failed to load security data: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
        }
        
        .admin-header {
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .back-link {
            color: #4ECDC4;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
        }
        
        .stat-card.danger {
            border-color: rgba(239, 68, 68, 0.3);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(239, 68, 68, 0.1));
        }
        
        .stat-card.warning {
            border-color: rgba(234, 179, 8, 0.3);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(234, 179, 8, 0.1));
        }
        
        .stat-label {
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .stat-value.danger {
            color: #ef4444;
        }
        
        .stat-value.warning {
            color: #eab308;
        }
        
        .section {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .attempts-table th,
        .attempts-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .attempts-table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .badge-warning {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }
        
        .badge-success {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .rate-limit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .rate-item {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 8px;
            padding: 15px;
        }
        
        .rate-item-name {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .rate-item-value {
            font-size: 18px;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🛡️ Security Dashboard</h1>
        <a href="/admin.php" class="back-link">← Back to Admin Panel</a>
    </div>
    
    <div class="container">
        <!-- Ban Evasion Stats -->
        <div class="stats-grid">
            <div class="stat-card danger">
                <div class="stat-label">Evasion Attempts (7d)</div>
                <div class="stat-value danger"><?php echo number_format($banStats['detected_attempts'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-label">Blocked Registrations</div>
                <div class="stat-value warning"><?php echo number_format($banStats['blocked_registrations'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-label">Auto-Terminated</div>
                <div class="stat-value danger"><?php echo number_format($banStats['terminated_evasion_accounts'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Escalated Originals</div>
                <div class="stat-value"><?php echo number_format($banStats['escalated_originals'] ?? 0); ?></div>
            </div>
        </div>
        
        <!-- Recent Evasion Attempts -->
        <div class="section">
            <h2>Recent Evasion Attempts</h2>
            
            <?php if (empty($recentEvasionAttempts)): ?>
                <div class="empty-state">
                    <p>No ban evasion attempts detected in the last 7 days.</p>
                </div>
            <?php else: ?>
                <table class="attempts-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Attempted Email</th>
                            <th>Username</th>
                            <th>Confidence</th>
                            <th>Action</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEvasionAttempts as $attempt): ?>
                            <tr>
                                <td><?php echo date('M j, H:i', strtotime($attempt['detected_at'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($attempt['attempted_email'], 0, 20)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($attempt['attempted_username']); ?></td>
                                <td><?php echo $attempt['detection_confidence']; ?>%</td>
                                <td>
                                    <?php if ($attempt['action'] === 'terminate'): ?>
                                        <span class="badge badge-danger">Terminated</span>
                                    <?php elseif ($attempt['action'] === 'block'): ?>
                                        <span class="badge badge-warning">Blocked</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Flagged</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Rate Limiting Stats -->
        <div class="section">
            <h2>Rate Limiting (24h)</h2>
            <div class="rate-limit-grid">
                <?php foreach ($rateStats as $action => $stats): ?>
                    <div class="rate-item">
                        <div class="rate-item-name"><?php echo ucfirst(str_replace('_', ' ', $action)); ?></div>
                        <div class="rate-item-value">
                            <?php echo number_format($stats['total_attempts']); ?> attempts
                            <span style="color: #64748b; font-size: 12px;">
                                (<?php echo $stats['unique_identifiers']; ?> unique)
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

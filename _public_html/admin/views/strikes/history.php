<?php
/**
 * User Strike History - Admin View
 * 
 * View all strikes for a specific user with visual distinction for expired strikes.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../../../includes/init.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/StrikeManager.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin.php');
    exit;
}

$userId = intval($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: /admin.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user info
    $stmt = $db->prepare("
        SELECT id, username, email, account_tier, account_status, 
               current_strike_count, DATE_FORMAT(last_strike_at, '%M %d, %Y') as last_strike
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: /admin.php');
        exit;
    }
    
    // Get strikes using StrikeManager
    $strikeManager = new StrikeManager();
    $strikes = $strikeManager->getUserStrikes($userId);
    $activeStrikes = $strikeManager->countActiveStrikes($userId);
    
} catch (Exception $e) {
    $error = 'Failed to load strike history: ' . $e->getMessage();
    $strikes = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strike History - <?php echo htmlspecialchars($user['username']); ?></title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .back-link {
            color: #4ECDC4;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .user-card {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: rgba(15, 23, 42, 0.6);
            border-radius: 8px;
        }
        
        .info-label {
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #f1f5f9;
            font-size: 16px;
            font-weight: 600;
        }
        
        .strike-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .badge-expired {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
        }
        
        .badge-community {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .badge-paid {
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.2), rgba(99, 102, 241, 0.2));
            color: #4ECDC4;
        }
        
        .timeline {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
        }
        
        .timeline h2 {
            font-size: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .timeline-item {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-item.expired {
            opacity: 0.6;
        }
        
        .timeline-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .timeline-marker.tier-1 {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }
        
        .timeline-marker.tier-2 {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .timeline-marker.tier-3 {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .timeline-title {
            font-size: 16px;
            font-weight: 600;
            color: #f1f5f9;
        }
        
        .timeline-date {
            color: #64748b;
            font-size: 13px;
        }
        
        .timeline-meta {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .timeline-evidence {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
        }
        
        .timeline-status {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .timeline-status.active {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .timeline-status.expired {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(15, 23, 42, 0.6);
            border-radius: 8px;
            font-size: 12px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .legend-marker.active {
            background: #f87171;
        }
        
        .legend-marker.expired {
            background: #64748b;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>📋 Strike History</h1>
        <a href="/admin/views/strikes/apply.php" class="back-link">Apply New Strike →</a>
    </div>
    
    <div class="container">
        <a href="/admin.php" class="back-link">← Back to Admin Panel</a>
        
        <div class="user-card">
            <div class="user-info">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Account Tier</div>
                    <div class="info-value">
                        <span class="badge badge-<?php echo $user['account_tier']; ?>">
                            <?php echo ucfirst($user['account_tier']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Active Strikes</div>
                    <div class="info-value">
                        <span class="strike-count">
                            <?php echo $activeStrikes; ?> active
                            <?php if (count($strikes) > $activeStrikes): ?>
                                <span class="badge badge-expired">
                                    <?php echo count($strikes) - $activeStrikes; ?> expired
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value" style="text-transform:capitalize;">
                        <?php echo $user['account_status']; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="timeline">
            <h2>Strike Timeline</h2>
            
            <?php if (empty($strikes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✓</div>
                    <h3>No Strikes</h3>
                    <p>This user has a clean record with no strikes.</p>
                </div>
            <?php else: ?>
                <?php foreach ($strikes as $strike): ?>
                    <div class="timeline-item <?php echo $strike['is_expired'] ? 'expired' : ''; ?>">
                        <div class="timeline-marker tier-<?php echo $strike['tier_applied']; ?>">
                            T<?php echo $strike['tier_applied']; ?>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-title">
                                    <?php echo htmlspecialchars($strike['rule_id']); ?> - 
                                    <?php echo htmlspecialchars($strike['violation_type']); ?>
                                </span>
                                <span class="timeline-date">
                                    <?php echo date('F j, Y', strtotime($strike['created_at'])); ?>
                                </span>
                            </div>
                            <div class="timeline-meta">
                                Applied by <?php echo htmlspecialchars($strike['applied_by_username'] ?? 'Unknown'); ?> • 
                                <?php echo $strike['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                            <div class="timeline-evidence">
                                <?php echo nl2br(htmlspecialchars($strike['evidence'])); ?>
                            </div>
                            <span class="timeline-status <?php echo $strike['is_expired'] ? 'expired' : 'active'; ?>">
                                <?php echo $strike['is_expired'] ? '⚠ Expired (over 30 days)' : '⚡ Currently Active'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-marker active"></div>
                        <span>Active strikes count toward escalation</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker expired"></div>
                        <span>Expired strikes (over 30 days old) are visible but don't count</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

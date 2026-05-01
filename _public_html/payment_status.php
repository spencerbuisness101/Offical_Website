<?php
/**
 * Payment Status Check - Spencer's Website v7.0
 * Allows users to verify their payment status and subscription details
 */

require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Get user info
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'];

// Get payment status from database
$payment_status = null;
$subscription_details = null;

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user's payment status
    $stmt = $db->prepare("
        SELECT 
            payment_provider,
            subscription_status,
            subscription_id,
            plan_type,
            amount,
            currency,
            created_at,
            expires_at,
            last_payment_at,
            auto_renew
        FROM user_subscriptions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $payment_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent payment events
    $stmt = $db->prepare("
        SELECT 
            event_type,
            amount,
            currency,
            status,
            created_at,
            metadata
        FROM payment_events 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $payment_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Payment status error: " . $e->getMessage());
    $payment_status = null;
    $payment_events = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .status-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .status-trial {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            border-color: rgba(78, 205, 196, 0.5);
            transform: translateY(-2px);
        }
        
        .event-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .event-type {
            font-weight: 600;
            color: #4ECDC4;
            margin-bottom: 5px;
        }
        
        .event-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .no-payments {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="container">
        <div class="header">
            <h1>Payment Status</h1>
            <p>Check your subscription and payment details</p>
        </div>
        
        <?php if ($payment_status): ?>
            <div class="status-card">
                <h2 style="margin-bottom: 20px;">Subscription Status</h2>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <?php
                    $status_class = 'status-inactive';
                    if ($payment_status['subscription_status'] === 'active') {
                        $status_class = 'status-active';
                    } elseif ($payment_status['subscription_status'] === 'trial') {
                        $status_class = 'status-trial';
                    }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo ucfirst($payment_status['subscription_status'] ?? 'Unknown'); ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Provider</span>
                    <span class="detail-value"><?php echo ucfirst($payment_status['payment_provider'] ?? 'Unknown'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Plan</span>
                    <span class="detail-value"><?php echo ucfirst($payment_status['plan_type'] ?? 'Free'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Amount</span>
                    <span class="detail-value"><?php echo ($payment_status['amount'] ?? '0') . ' ' . ($payment_status['currency'] ?? 'USD'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Started</span>
                    <span class="detail-value"><?php echo date('M j, Y', strtotime($payment_status['created_at'] ?? 'now')); ?></span>
                </div>
                
                <?php if ($payment_status['expires_at']): ?>
                <div class="detail-row">
                    <span class="detail-label">Expires</span>
                    <span class="detail-value"><?php echo date('M j, Y', strtotime($payment_status['expires_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label">Auto-renew</span>
                    <span class="detail-value"><?php echo ($payment_status['auto_renew'] ?? false) ? 'Enabled' : 'Disabled'; ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="status-card">
                <h2 style="margin-bottom: 20px;">No Active Subscription</h2>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    You don't have any active subscriptions. Upgrade to premium to unlock all features!
                </p>
                <a href="main.php" class="back-button">
                    <i class="fas fa-crown"></i> Upgrade to Premium
                </a>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($payment_events)): ?>
            <div class="status-card">
                <h2 style="margin-bottom: 20px;">Recent Payment Events</h2>
                
                <?php foreach ($payment_events as $event): ?>
                    <div class="event-item">
                        <div class="event-type"><?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?></div>
                        <div class="event-details">
                            <div>Amount: <?php echo ($event['amount'] ?? '0') . ' ' . ($event['currency'] ?? 'USD'); ?></div>
                            <div>Status: <?php echo ucfirst($event['status'] ?? 'Unknown'); ?></div>
                            <div>Date: <?php echo date('M j, Y H:i', strtotime($event['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="status-card">
                <div class="no-payments">
                    <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No payment events found</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="main.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Account Downgrade Settings
 * 
 * Allows Paid Account users (13+) to voluntarily downgrade to Community Account.
 * Full anonymization of posts/messages and 30-day re-upgrade cooldown.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/DowngradeManager.php';

// Must be logged in as Paid Account user
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /index.php');
    exit;
}

// Check eligibility
$downgradeManager = new DowngradeManager();
$eligibility = $downgradeManager->canDowngrade($_SESSION['user_id']);

if (!$eligibility['can_downgrade']) {
    $_SESSION['error'] = $eligibility['reason'];
    header('Location: /set.php');
    exit;
}

$success = '';
$error = '';

// Handle downgrade confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm_downgrade'] ?? '';
    $password = $_POST['password'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($confirm !== 'DELETE MY DATA') {
        $error = 'Please type "DELETE MY DATA" to confirm.';
    } elseif (empty($password)) {
        $error = 'Please enter your password to confirm.';
    } else {
        // Verify password
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect password.';
            } else {
                // Process downgrade
                $result = $downgradeManager->downgradeAccount(
                    $_SESSION['user_id'], 
                    'user', 
                    $reason
                );
                
                if ($result['success']) {
                    // Clear session
                    session_destroy();
                    
                    // Create Community Account session
                    require_once __DIR__ . '/../includes/CommunityAuth.php';
                    $communityAuth = new CommunityAuth();
                    $sessionToken = $communityAuth->createSession(
                        $_SERVER['REMOTE_ADDR'], 
                        $_SERVER['HTTP_USER_AGENT']
                    );
                    
                    setcookie('community_session', $sessionToken, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                    
                    // Redirect to success page
                    header('Location: /settings/downgrade_success.php');
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downgrade Account - Settings</title>
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
            padding: 40px 20px;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .back-link {
            color: #4ECDC4;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .danger-card {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 2px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            padding: 40px;
        }
        
        .danger-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .danger-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #ef4444;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
        }
        
        .warning-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .warning-box h3 {
            color: #ef4444;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-box ul {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .warning-box li {
            margin-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #ef4444;
        }
        
        .confirm-input {
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .btn {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            margin-top: 15px;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        
        .legal-notice {
            color: #64748b;
            font-size: 12px;
            text-align: center;
            margin-top: 30px;
            line-height: 1.6;
        }
        
        .stats-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #ef4444;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/set.php" class="back-link">← Back to Settings</a>
        
        <div class="danger-card">
            <div class="danger-header">
                <div class="danger-icon">⚠️</div>
                <h1>Downgrade to Community Account</h1>
                <p class="subtitle">This action is permanent and cannot be undone</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="warning-box">
                <h3><span>🚨</span> Warning: Irreversible Action</h3>
                <ul>
                    <li><strong>All posts and comments will be permanently anonymized</strong> and attributed to "[Deleted User]"</li>
                    <li><strong>All messages you sent will remain</strong> but show "[Deleted User]" as the sender</li>
                    <li><strong>Your profile will be completely removed</strong> from public view</li>
                    <li><strong>Your email and personal data will be purged</strong> after a 7-day grace period</li>
                    <li><strong>You cannot re-upgrade for 30 days</strong> (cooldown period)</li>
                    <li><strong>Your subscription will be cancelled immediately</strong> (no refund for current period)</li>
                    <li><strong>You will lose access to:</strong> YAPS messaging, posting, commenting, and public profile</li>
                </ul>
            </div>
            
            <form method="POST" action="" onsubmit="return confirmDowngrade()">
                <div class="form-group">
                    <label for="confirm">Type "DELETE MY DATA" to confirm</label>
                    <input type="text" 
                           id="confirm" 
                           name="confirm_downgrade" 
                           class="confirm-input" 
                           placeholder="DELETE MY DATA"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Enter your password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Your current password"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="reason">Why are you downgrading? (Optional)</label>
                    <select name="reason" id="reason">
                        <option value="">Select a reason...</option>
                        <option value="Privacy concerns">Privacy concerns</option>
                        <option value="Don't need paid features">Don't need paid features</option>
                        <option value="Too expensive">Too expensive</option>
                        <option value="Switching to another platform">Switching to another platform</option>
                        <option value="Taking a break">Taking a break</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-danger">
                    I Understand - Downgrade My Account
                </button>
                
                <a href="/set.php" class="btn btn-secondary" style="display:block; text-decoration:none; text-align:center;">
                    Cancel - Keep My Paid Account
                </a>
            </form>
            
            <p class="legal-notice">
                By proceeding, you confirm you understand that this action is irreversible. 
                You have the right to downgrade under our Privacy Policy and Terms of Service. 
                Your data will be handled according to our Data Retention Policy.
            </p>
        </div>
    </div>
    
    <script>
        function confirmDowngrade() {
            const confirmText = document.getElementById('confirm').value;
            if (confirmText !== 'DELETE MY DATA') {
                alert('Please type "DELETE MY DATA" exactly to confirm.');
                return false;
            }
            return confirm('FINAL WARNING: Are you absolutely sure you want to permanently downgrade your account? This cannot be undone.');
        }
    </script>
</body>
</html>

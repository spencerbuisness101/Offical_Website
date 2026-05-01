<?php
/**
 * Revoke Parental Consent
 * 
 * Parent-facing page where they can revoke consent that was previously granted.
 * This immediately downgrades the child's account to Community tier.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/VPCManager.php';

// Get consent ID from URL
$consentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($consentId <= 0) {
    $error = 'Invalid revocation link.';
    $showForm = false;
} else {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get consent record
        $stmt = $db->prepare("
            SELECT pc.*, u.account_tier 
            FROM parental_consent pc
            LEFT JOIN users u ON pc.child_user_id = u.id
            WHERE pc.id = ? AND pc.status = 'verified'
        ");
        $stmt->execute([$consentId]);
        $consentRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$consentRecord) {
            $error = 'Consent record not found or already revoked.';
            $showForm = false;
        } elseif ($consentRecord['account_tier'] !== 'paid') {
            $error = 'This account has already been downgraded.';
            $showForm = false;
        } else {
            $showForm = true;
            $childUserId = $consentRecord['child_user_id'];
            $parentEmail = $consentRecord['parent_email'];
            
            // Process revocation
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $reason = $_POST['reason'] ?? '';
                
                $vpcManager = new VPCManager();
                $result = $vpcManager->revokeConsent($consentId, $reason);
                
                if ($result['success']) {
                    $revoked = true;
                    $successMessage = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Consent revocation error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
        $showForm = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revoke Parental Consent - Spencer's Website</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ef4444, #f87171);
            border-radius: 15px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        h1 {
            color: #f1f5f9;
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 16px;
            margin-bottom: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            text-align: center;
        }
        
        .warning-box {
            background: rgba(234, 179, 8, 0.1);
            border: 2px solid rgba(234, 179, 8, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .warning-box h3 {
            color: #eab308;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box ul {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .warning-box li {
            margin-bottom: 5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        textarea:focus {
            outline: none;
            border-color: #ef4444;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
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
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #f1f5f9;
        }
        
        .legal-notice {
            color: #64748b;
            font-size: 12px;
            text-align: center;
            margin-top: 30px;
            line-height: 1.6;
        }
        
        .revoked-message {
            text-align: center;
            padding: 20px;
        }
        
        .revoked-message h2 {
            color: #22c55e;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .revoked-message p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚫</div>
        <h1>Revoke Parental Consent</h1>
        <p class="subtitle">You are about to revoke consent for your child's Paid Account</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($revoked) && $revoked): ?>
            <div class="revoked-message">
                <h2>✓ Consent Revoked</h2>
                <p><?php echo htmlspecialchars($successMessage); ?></p>
                <p style="margin-top:20px;">
                    Your child's account has been immediately downgraded to a Community Account. All social features have been disabled and their personal data will be permanently anonymized within 7 days.
                </p>
            </div>
        <?php elseif ($showForm): ?>
            <div class="warning-box">
                <h3><span>⚠️</span> Warning: This Action Cannot Be Undone</h3>
                <ul>
                    <li>Your child will immediately lose access to all social features</li>
                    <li>All messages, posts, and comments will be permanently anonymized</li>
                    <li>Your child's profile will be converted to an anonymous Community Account</li>
                    <li>They will not be able to upgrade again for 30 days</li>
                    <li>This action is irreversible</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="reason">Reason for Revocation (Optional)</label>
                    <textarea id="reason" name="reason" placeholder="Please let us know why you're revoking consent. This helps us improve our safety measures."></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-danger" onclick="return confirmRevocation()">
                        Revoke Consent
                    </button>
                    <a href="/" class="btn btn-secondary" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">
                        Cancel
                    </a>
                </div>
            </form>
            
            <p class="legal-notice">
                By revoking consent, you assert your parental rights under COPPA and other applicable children's privacy laws. 
                This action will be logged for compliance purposes.
            </p>
            
            <script>
                function confirmRevocation() {
                    return confirm('Are you absolutely sure you want to revoke parental consent? This action is irreversible and will immediately downgrade your child\'s account.');
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

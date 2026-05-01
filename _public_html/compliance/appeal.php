<?php
/**
 * Lockdown Appeal Page - Phase 3 Implementation
 * 
 * Users in lockdown mode (B1 NSFW or C1 Doxxing) are forcibly redirected here.
 * Server-side enforcement prevents access to any other pages until appeal is resolved.
 * 
 * SPECIFICATION:
 * - Account becomes view-only during lockdown
 * - Account deletion button is HIDDEN
 * - User must submit written appeal explaining behavior
 * - Appeal stored in database and emailed to Admin
 * - Admin reviews and approves/denies
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PunishmentManager.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// Get user's lockdown information
$stmt = $db->prepare("
    SELECT u.*, s.rule_id, s.violation_type, s.evidence, s.created_at as strike_date
    FROM users u
    LEFT JOIN user_strikes s ON u.lockdown_strike_id = s.id
    WHERE u.id = ? AND u.account_status = 'restricted'
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If not in lockdown, redirect to main
if (!$user) {
    header('Location: /main.php');
    exit;
}

// Check for existing pending appeal
$stmt = $db->prepare("
    SELECT id FROM lockdown_appeals
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$existingAppeal = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle appeal submission
$appealSubmitted = false;
$appealError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingAppeal) {
    $appealText = trim($_POST['appeal_text'] ?? '');
    
    // Validation
    if (strlen($appealText) < 50) {
        $appealError = 'Your appeal must be at least 50 characters. Please provide more detail.';
    } elseif (strlen($appealText) > 5000) {
        $appealError = 'Your appeal cannot exceed 5000 characters.';
    } else {
        try {
            // Insert appeal into database
            $stmt = $db->prepare("
                INSERT INTO lockdown_appeals 
                (user_id, appeal_text, status, created_at)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $appealText]);
            
            // Send email notification to admin
            $this->notifyAdminOfAppeal($userId, $user['username'], $appealText);
            
            $appealSubmitted = true;
            
        } catch (Exception $e) {
            error_log("Appeal submission error: " . $e->getMessage());
            $appealError = 'An error occurred while submitting your appeal. Please try again.';
        }
    }
}

/**
 * Send email notification to admin about new appeal
 */
function notifyAdminOfAppeal($userId, $username, $appealText) {
    $to = 'admin@spencerswebsite.com'; // Admin email
    $subject = 'New Lockdown Appeal Submitted';
    
    $message = "A new appeal has been submitted by a user in lockdown mode.\n\n";
    $message .= "User ID: {$userId}\n";
    $message .= "Username: {$username}\n";
    $message .= "Submitted: " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Appeal Text:\n";
    $message .= "-------------------------------------------\n";
    $message .= $appealText . "\n";
    $message .= "-------------------------------------------\n\n";
    $message .= "Review Appeal: https://" . $_SERVER['HTTP_HOST'] . "/admin/review_appeals.php\n";
    
    // Use mail() or your preferred email method
    mail($to, $subject, $message);
}

// Determine lockdown reason text
$lockdownReason = '';
if ($user['lockdown_rule'] === 'B1') {
    $lockdownReason = 'NSFW/Adult Content Violation';
} elseif ($user['lockdown_rule'] === 'C1') {
    $lockdownReason = 'Doxxing/Privacy Violation';
} else {
    $lockdownReason = 'Serious Rule Violation';
}

$lockdownDate = $user['strike_date'] ? date('F j, Y', strtotime($user['strike_date'])) : 'Recently';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Account Lockdown - Appeal Required</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            line-height: 1.6;
        }
        
        .lockdown-container {
            max-width: 600px;
            width: 100%;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 2px solid #ef4444;
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.25);
        }
        
        .lockdown-icon {
            font-size: 72px;
            text-align: center;
            margin-bottom: 24px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .lockdown-title {
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            color: #ef4444;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .lockdown-subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 1.1rem;
            margin-bottom: 32px;
        }
        
        .lockdown-reason {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
        }
        
        .lockdown-reason-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ef4444;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .lockdown-reason-text {
            color: #fca5a5;
            font-size: 1rem;
        }
        
        .appeal-section h3 {
            font-size: 1.25rem;
            margin-bottom: 16px;
            color: #e2e8f0;
        }
        
        .appeal-section p {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
        
        .appeal-form textarea {
            width: 100%;
            min-height: 180px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            color: #e2e8f0;
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            line-height: 1.6;
            transition: border-color 0.2s;
        }
        
        .appeal-form textarea:focus {
            outline: none;
            border-color: #4ECDC4;
        }
        
        .appeal-form textarea::placeholder {
            color: #64748b;
        }
        
        .char-count {
            text-align: right;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 8px;
        }
        
        .char-count.warning {
            color: #f59e0b;
        }
        
        .submit-btn {
            width: 100%;
            margin-top: 24px;
            padding: 16px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
        }
        
        .submit-btn:disabled {
            background: #475569;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .warning-text {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .warning-text strong {
            color: #ef4444;
        }
        
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }
        
        .success-message h3 {
            color: #22c55e;
            font-size: 1.5rem;
            margin-bottom: 12px;
        }
        
        .success-message p {
            color: #86efac;
            font-size: 1rem;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            color: #fca5a5;
        }
        
        .rules-reminder {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        
        .rules-reminder h4 {
            color: #3b82f6;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        
        .rules-reminder ul {
            margin-left: 20px;
            color: #94a3b8;
        }
        
        .rules-reminder li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="lockdown-container">
        <?php if ($appealSubmitted): ?>
            <div class="success-message">
                <div style="font-size: 64px; margin-bottom: 16px;">✓</div>
                <h3>Appeal Submitted</h3>
                <p>Your appeal has been sent to the administrators for review. You will receive a response via Smail within 72 hours.</p>
                <p style="margin-top: 16px; font-size: 0.9rem; color: #64748b;">You can now close this page. Do not submit multiple appeals.</p>
            </div>
        <?php else: ?>
            <div class="lockdown-icon">⚠️</div>
            <h1 class="lockdown-title">Account Locked</h1>
            <p class="lockdown-subtitle">Your account has been restricted due to a serious policy violation.</p>
            
            <div class="lockdown-reason">
                <div class="lockdown-reason-label">Violation</div>
                <div class="lockdown-reason-text"><?php echo htmlspecialchars($lockdownReason); ?></div>
            </div>
            
            <div class="rules-reminder">
                <h4>📋 Appeal Guidelines</h4>
                <ul>
                    <li>Explain what happened from your perspective</li>
                    <li>Acknowledge the rule violation and why it occurred</li>
                    <li>Describe steps you'll take to prevent future violations</li>
                    <li>Be respectful and honest - hostile appeals are automatically denied</li>
                    <li>Minimum 50 characters required</li>
                </ul>
            </div>
            
            <div class="appeal-section">
                <h3>Submit Your Appeal</h3>
                <p>To restore access, you must submit a written appeal explaining your behavior and requesting reinstatement.</p>
                
                <?php if ($appealError): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($appealError); ?>
                    </div>
                <?php endif; ?>
                
                <form class="appeal-form" method="POST" action="" id="appealForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <textarea 
                        name="appeal_text" 
                        id="appealText"
                        placeholder="Write your appeal here. Explain what happened, why it won't happen again, and why your account should be restored..."
                        required
                        minlength="50"
                    ></textarea>
                    <div class="char-count" id="charCount">0 characters (minimum 50)</div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        Submit Appeal to Admin
                    </button>
                </form>
            </div>
            
            <p class="warning-text">
                <strong>⚠️ Important:</strong> Attempting to circumvent this lockdown by creating new accounts, using VPNs, or browser tricks will result in <strong>immediate permanent termination</strong> without appeal.
            </p>
        <?php endif; ?>
    </div>
    
    <script>
        // Prevent accidental navigation
        <?php if (!$appealSubmitted): ?>
        let formSubmitted = false;
        
        // Character counter
        const textarea = document.getElementById('appealText');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('submitBtn');
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length + ' characters' + (length < 50 ? ' (minimum 50)' : ' ✓');
            
            if (length < 50) {
                charCount.classList.add('warning');
                submitBtn.disabled = true;
            } else {
                charCount.classList.remove('warning');
                submitBtn.disabled = false;
            }
        });
        
        // Disable button initially if too short
        if (textarea.value.length < 50) {
            submitBtn.disabled = true;
            charCount.classList.add('warning');
        }
        
        // Prevent leaving without submitting
        window.onbeforeunload = function(e) {
            if (!formSubmitted && textarea.value.length > 0) {
                e.preventDefault();
                e.returnValue = 'You have started writing an appeal. Are you sure you want to leave?';
                return e.returnValue;
            }
        };
        
        // Remove warning after form submission
        document.getElementById('appealForm').addEventListener('submit', function() {
            formSubmitted = true;
            window.onbeforeunload = null;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        });
        
        // Prevent back button
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.pushState(null, null, location.href);
        };
        <?php endif; ?>
    </script>
</body>
</html>

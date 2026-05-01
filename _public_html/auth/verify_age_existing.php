<?php
/**
 * Existing User Age Verification
 * 
 * One-time age reverification for existing users after policy update.
 * Users who declare themselves under 13 will be immediately downgraded to Community Accounts.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
require_once __DIR__ . '/../includes/init.php';


// Check if user is logged in (existing user)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Check if user has already verified age
if (isset($_SESSION['age_verified_at']) && !empty($_SESSION['age_verified_at'])) {
    header('Location: /main.php');
    exit;
}

// Check if user is admin (admins exempt from reverification)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Auto-verify admins
    require_once __DIR__ . '/../config/database.php';
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET age_verified_at = NOW(), declared_birthdate = '1990-01-01' 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Admin auto-verification error: " . $e->getMessage());
    }
    
    header('Location: /main.php');
    exit;
}

// Handle form submission
$error = '';
$success = false;
$downgraded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthdate = $_POST['birthdate'] ?? '';
    
    // Server-side validation
    if (empty($birthdate)) {
        $error = 'Please enter a valid date of birth.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birthdate);
        
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            $error = 'Please enter a valid date of birth.';
        } else {
            $today = new DateTime();
            $minDate = new DateTime('1900-01-01');
            $minAgeDate = (clone $today)->modify('-3 years');
            $maxAgeDate = (clone $today)->modify('-120 years');
            
            // Validate bounds
            if ($date > $today || $date->format('Y') < 1900 || $date > $minAgeDate || $date < $maxAgeDate) {
                $error = 'Please enter a valid date of birth.';
            } else {
                // Calculate age
                $age = $today->diff($date)->y;
                
                require_once __DIR__ . '/../config/database.php';
                
                try {
                    $database = new Database();
                    $db = $database->getConnection();
                    
                    // Get user info before making changes
                    $stmt = $db->prepare("SELECT username, email, account_tier FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        $error = 'User not found.';
                    } else {
                        // Log the verification
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $pepper = $_ENV['PEPPER_SECRET'] ?? '';
                        $ipHash = hash('sha256', $ip . $pepper);
                        
                        $routingDecision = ($age < 13) ? 'community' : 'reverification';
                        // Hash user agent; null-out DOB for community routing (COPPA: DOB is PII for children)
                        $userAgentHash = hash('sha256', $userAgent . $pepper);
                        $logDate = ($age < 13) ? null : $birthdate;
                        
                        $stmt = $db->prepare("
                            INSERT INTO age_verification_log 
                            (user_id, declared_date, calculated_age, routing_decision, ip_hash, user_agent) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$_SESSION['user_id'], $logDate, $age, $routingDecision, $ipHash, $userAgentHash]);
                        
                        if ($age < 13) {
                            // UNDER 13: Downgrade to Community Account
                            
                            // 1. Update user record
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET age_verified_at = NOW(),
                                    declared_birthdate = ?,
                                    account_tier = 'community',
                                    account_status = 'active',
                                    email = NULL,
                                    email_hash = ?
                                WHERE id = ?
                            ");
                            
                            // Hash email for ban list before clearing
                            $emailHash = hash('sha256', ($user['email'] ?? '') . $pepper);
                            $stmt->execute([$birthdate, $emailHash, $_SESSION['user_id']]);
                            
                            // 2. Anonymize all posts (set username to [Deleted User])
                            $stmt = $db->prepare("
                                UPDATE posts 
                                SET username = '[Deleted User]', user_id = NULL 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            
                            // 3. Anonymize all messages
                            $stmt = $db->prepare("
                                UPDATE messages 
                                SET sender_name = '[Deleted User]', sender_id = NULL 
                                WHERE sender_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            
                            // 4. Delete from user_premium if exists
                            $stmt = $db->prepare("DELETE FROM user_premium WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            
                            // 5. Log downgrade
                            $stmt = $db->prepare("
                                INSERT INTO account_downgrades 
                                (user_id, from_tier, to_tier, initiated_by, reason, created_at)
                                VALUES (?, 'paid', 'community', 'user', 'Age verification: declared under 13', NOW())
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            
                            // 6. Clear sensitive session data
                            unset($_SESSION['username']);
                            unset($_SESSION['email']);
                            $_SESSION['is_community_account'] = true;
                            $_SESSION['account_tier'] = 'community';
                            $_SESSION['can_message'] = false;
                            $_SESSION['can_post'] = false;
                            
                            // 7. Clear PHP session cookie (will use Community session instead)
                            session_destroy();
                            
                            // 8. Create Community Account session
                            require_once __DIR__ . '/../includes/CommunityAuth.php';
                            $communityAuth = new CommunityAuth();
                            $sessionToken = $communityAuth->createSession($ip, $userAgent);
                            
                            setcookie('community_session', $sessionToken, [
                                'expires' => time() + (30 * 24 * 60 * 60),
                                'path' => '/',
                                'domain' => '',
                                'secure' => true,
                                'httponly' => true,
                                'samesite' => 'Strict'
                            ]);
                            
                            $downgraded = true;
                            
                        } else {
                            // 13+: Just record the verification
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET age_verified_at = NOW(),
                                    declared_birthdate = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$birthdate, $_SESSION['user_id']]);
                            
                            $_SESSION['age_verified_at'] = date('Y-m-d H:i:s');
                            // Bust the lockdown cache so init.php re-reads the DB on the next
                            // page load. Without this, the stale _lockdown_cached_user entry
                            // (which still has age_verified_at = NULL) would cause init.php to
                            // redirect back here immediately, creating an infinite loop.
                            unset($_SESSION['_lockdown_cached_user'], $_SESSION['_lockdown_check_at']);
                            $success = true;
                        }
                    }
                    
                } catch (Exception $e) {
                    error_log("Age verification error: " . $e->getMessage());
                    $error = 'An error occurred. Please try again.';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Age Verification Required - Spencer's Website</title>
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
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            border-radius: 20px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        
        h1 {
            color: #f1f5f9;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
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
        }
        
        .alert-warning {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            color: #eab308;
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        label {
            display: block;
            color: #cbd5e1;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 12px;
        }
        
        input[type="date"] {
            width: 100%;
            padding: 16px 20px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #f1f5f9;
            font-size: 18px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        input[type="date"]:focus {
            outline: none;
            border-color: #4ECDC4;
            background: rgba(15, 23, 42, 1);
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .privacy-note {
            color: #64748b;
            font-size: 13px;
            margin-top: 30px;
            line-height: 1.5;
        }
        
        .downgrade-info {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        
        .downgrade-info h3 {
            color: #eab308;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .downgrade-info p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .downgrade-info a {
            color: #4ECDC4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔒</div>
        <h1>Age Verification Required</h1>
        <p class="subtitle">
            To comply with children's privacy laws, we need to verify your age. 
            This is a one-time requirement for all existing users.
        </p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Thank you!</strong> Your age has been verified. You can continue using the platform.
            </div>
            <a href="/main.php" class="submit-btn" style="display:inline-block; text-decoration:none;">
                Continue to Site
            </a>
        <?php elseif ($downgraded): ?>
            <div class="alert alert-warning">
                <strong>Account Downgraded</strong>
            </div>
            <div class="downgrade-info">
                <h3>⚠️ Important Notice</h3>
                <p>
                    Because you declared that you are under 13 years old, your account has been downgraded to a 
                    <strong>Community Account</strong> in compliance with COPPA (Children's Online Privacy Protection Act).
                </p>
                <p>
                    <strong>What this means:</strong>
                </p>
                <ul style="color:#94a3b8; margin-left:20px; margin-bottom:15px;">
                    <li>You can still play games and browse the community</li>
                    <li>You cannot send messages or post content</li>
                    <li>Your profile has been anonymized</li>
                    <li>No personal information is stored</li>
                </ul>
                <p>
                    If you believe this was a mistake, or if you want to upgrade to a Paid Account with full features, 
                    you'll need to have a parent or guardian complete the 
                    <a href="/auth/parental_consent.php">Parental Consent process</a>.
                </p>
            </div>
            <a href="/main.php" class="submit-btn" style="display:inline-block; text-decoration:none; margin-top:20px;">
                Continue to Site
            </a>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="birthdate">Please confirm your date of birth:</label>
                    <input type="date" 
                           id="birthdate" 
                           name="birthdate" 
                           required 
                           max="<?php echo date('Y-m-d'); ?>"
                           min="1900-01-01">
                </div>
                
                <button type="submit" class="submit-btn">
                    Verify Age
                </button>
            </form>
            
            <p class="privacy-note">
                This information is used solely for legal compliance and is stored securely. 
                Users under 13 will have their accounts converted to COPPA-compliant Community Accounts.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>

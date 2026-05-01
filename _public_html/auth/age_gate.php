<?php
/**
 * Age Gate - COPPA-Compliant Age Verification
 * 
 * This is the FIRST and ONLY screen presented to new users.
 * No other fields are visible until age is processed.
 * 
 * SPECIFICATION:
 * - Single field: "When were you born?" (neutral date picker)
 * - No email, username, or password fields visible
 * - Routing decision made server-side after submission
 * - Age < 13: Community Account (no PII collection)
 * - Age ≥ 13: Option for Community or Paid Account
 */

// Prevent direct access (must be routed through signup process)
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true) {
    header('Location: /main.php');
    exit;
}

// Check if user already has a Community Account session
if (isset($_COOKIE['community_session']) && !empty($_COOKIE['community_session'])) {
    // Validate session
    require_once __DIR__ . '/../includes/CommunityAuth.php';
    $communityAuth = new CommunityAuth();
    if ($communityAuth->validateSession($_COOKIE['community_session'])) {
        header('Location: /main.php');
        exit;
    }
}

// Check for rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$pepper = $_ENV['PEPPER_SECRET'] ?? '';
$ipHash = hash('sha256', $ip . $pepper);

require_once __DIR__ . '/../config/database.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT attempt_count FROM vpc_rate_limit WHERE ip_hash = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$ipHash]);
    $rateLimit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateLimit && $rateLimit['attempt_count'] >= 3) {
        $rateLimited = true;
    } else {
        $rateLimited = false;
    }
} catch (Exception $e) {
    // If database error, allow through but log
    error_log("Age gate rate limit check failed: " . $e->getMessage());
    $rateLimited = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Age Verification</title>
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
        
        .age-gate-container {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 60px 50px;
            max-width: 480px;
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
            margin-bottom: 40px;
            line-height: 1.6;
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
        
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        
        .error-message {
            color: #f87171;
            font-size: 14px;
            margin-top: 10px;
            display: none;
        }
        
        .error-message.visible {
            display: block;
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
        
        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .privacy-note {
            color: #64748b;
            font-size: 13px;
            margin-top: 30px;
            line-height: 1.5;
        }
        
        .rate-limit-notice {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-radius: 12px;
            padding: 20px;
            color: #f87171;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .coppa-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 20px;
            padding: 8px 16px;
            color: #22c55e;
            font-size: 12px;
            font-weight: 500;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="age-gate-container">
        <div class="logo">🎬</div>
        <h1>Welcome!</h1>
        <p class="subtitle">To get started, we need to know your age so we can provide the right experience.</p>
        
        <?php if ($rateLimited): ?>
            <div class="rate-limit-notice">
                <strong>Account creation limit reached</strong><br>
                Too many accounts have been created from this network recently. Please try again in 24 hours.
            </div>
        <?php else: ?>
            <form id="ageGateForm" action="/auth/process_age.php" method="POST">
                <div class="form-group">
                    <label for="birthdate">When were you born?</label>
                    <input type="date" 
                           id="birthdate" 
                           name="birthdate" 
                           required 
                           max="<?php echo date('Y-m-d'); ?>"
                           min="1900-01-01">
                    <div class="error-message" id="errorMessage">
                        Please enter a valid date of birth.
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Continue
                </button>
            </form>
        <?php endif; ?>
        
        <p class="privacy-note">
            We take your privacy seriously. Age information helps us comply with safety regulations and provide age-appropriate features.
        </p>
        
        <div class="coppa-badge">
            <span>✓</span> COPPA Compliant
        </div>
    </div>

    <script>
        document.getElementById('ageGateForm')?.addEventListener('submit', function(e) {
            const birthdate = document.getElementById('birthdate').value;
            const errorMessage = document.getElementById('errorMessage');
            const submitBtn = document.getElementById('submitBtn');
            
            // Client-side validation (defense in depth - server validates too)
            if (!birthdate) {
                e.preventDefault();
                errorMessage.textContent = 'Please enter a valid date of birth.';
                errorMessage.classList.add('visible');
                return false;
            }
            
            const selectedDate = new Date(birthdate);
            const today = new Date();
            const minDate = new Date('1900-01-01');
            const maxDate = new Date(today.getFullYear() - 3, today.getMonth(), today.getDate());
            
            if (selectedDate > today) {
                e.preventDefault();
                errorMessage.textContent = 'Please enter a valid date of birth.';
                errorMessage.classList.add('visible');
                return false;
            }
            
            if (selectedDate < minDate || selectedDate > maxDate) {
                e.preventDefault();
                errorMessage.textContent = 'Please enter a valid date of birth.';
                errorMessage.classList.add('visible');
                return false;
            }
            
            // Disable button to prevent double-submit
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            return true;
        });
    </script>
</body>
</html>

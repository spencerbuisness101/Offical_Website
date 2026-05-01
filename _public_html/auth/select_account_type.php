<?php
/**
 * Account Type Selection - For Users 13+
 * 
 * After passing the age gate with age 13+, users can choose:
 * - Community Account (Free): Games, community browsing, no messaging
 * - Paid Account (Subscription): Full features including YAPS messaging
 */

session_start();
define('APP_RUNNING', true);

// Check if user has passed age gate
if (!isset($_SESSION['calculated_age']) || $_SESSION['calculated_age'] < 13) {
    header('Location: /auth/age_gate.php');
    exit;
}

$age = $_SESSION['calculated_age'];

// Check if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true) {
    header('Location: /main.php');
    exit;
}

// Check if already has Community Account
if (isset($_COOKIE['community_session']) && !empty($_COOKIE['community_session'])) {
    header('Location: /main.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Account Type</title>
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
            max-width: 900px;
            width: 100%;
            text-align: center;
        }
        
        .header {
            margin-bottom: 50px;
        }
        
        h1 {
            color: #f1f5f9;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .subtitle {
            color: #94a3b8;
            font-size: 18px;
        }
        
        .options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .option-card {
            background: rgba(30, 41, 59, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .option-card:hover {
            border-color: #4ECDC4;
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .option-card.paid {
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .option-card.paid:hover {
            border-color: #6366f1;
        }
        
        .badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge.free {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .badge.paid {
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            color: white;
        }
        
        .icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        
        .icon.community {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .icon.paid {
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.2), rgba(99, 102, 241, 0.2));
        }
        
        h2 {
            color: #f1f5f9;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .price {
            color: #94a3b8;
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        .price .amount {
            color: #4ECDC4;
            font-size: 36px;
            font-weight: 700;
        }
        
        .features {
            text-align: left;
            margin-bottom: 30px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            color: #cbd5e1;
            font-size: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .feature:last-child {
            border-bottom: none;
        }
        
        .feature .check {
            color: #22c55e;
            flex-shrink: 0;
        }
        
        .feature .x {
            color: #f87171;
            flex-shrink: 0;
        }
        
        .cta-button {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .cta-button.community {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 2px solid #22c55e;
        }
        
        .cta-button.community:hover {
            background: #22c55e;
            color: white;
        }
        
        .cta-button.paid {
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            color: white;
        }
        
        .cta-button.paid:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .privacy-note {
            margin-top: 40px;
            padding: 20px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .privacy-note strong {
            color: #22c55e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome! You're <?php echo $age; ?> years old</h1>
            <p class="subtitle">Choose the account type that works best for you</p>
        </div>
        
        <div class="options">
            <!-- Community Account Option -->
            <div class="option-card">
                <span class="badge free">Free</span>
                <div class="icon community">🎮</div>
                <h2>Community Account</h2>
                <p class="price">No credit card required</p>
                
                <div class="features">
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Play single-player games</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Browse the community</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>No advertisements ever</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Private, anonymous browsing</span>
                    </div>
                    <div class="feature">
                        <span class="x">✗</span>
                        <span>No messaging or chat</span>
                    </div>
                    <div class="feature">
                        <span class="x">✗</span>
                        <span>No public posts or comments</span>
                    </div>
                    <div class="feature">
                        <span class="x">✗</span>
                        <span>No friend lists or profiles</span>
                    </div>
                </div>
                
                <a href="/auth/create_community.php" class="cta-button community">
                    Continue with Free Account
                </a>
            </div>
            
            <!-- Paid Account Option -->
            <div class="option-card paid">
                <span class="badge paid">Full Access</span>
                <div class="icon paid">⭐</div>
                <h2>Paid Account</h2>
                <p class="price"><span class="amount">$4.99</span>/month</p>
                
                <div class="features">
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Everything in Community</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>YAPS messaging system</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Internal email & messaging</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Post and comment publicly</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Create a public profile</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Add friends and connect</span>
                    </div>
                    <div class="feature">
                        <span class="check">✓</span>
                        <span>Premium support</span>
                    </div>
                </div>
                
                <a href="/auth/signup.php?type=paid" class="cta-button paid">
                    Upgrade to Paid Account
                </a>
            </div>
        </div>
        
        <div class="privacy-note">
            <strong>🔒 Privacy First:</strong> Community Accounts collect zero personal information. 
            We don't ask for your email, name, or any identifying information. Your session is anonymous 
            and automatically purged after 30 days of inactivity. COPPA compliant for users of all ages.
        </div>
    </div>
</body>
</html>

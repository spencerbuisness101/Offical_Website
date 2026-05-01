<?php
/**
 * Account Downgrade Success Page
 * 
 * Shown after successful downgrade to Community Account.
 * Explains what happened and what to expect.
 */

session_start();

// Check for Community Account session
if (!isset($_COOKIE['community_session']) || empty($_COOKIE['community_session'])) {
    header('Location: /index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Downgraded - Spencer's Website</title>
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
            padding: 60px 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
        }
        
        h1 {
            color: #22c55e;
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
        
        .info-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-box h3 {
            color: #f1f5f9;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box ul {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 8px;
        }
        
        .cooldown-notice {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .cooldown-notice h4 {
            color: #eab308;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .cooldown-notice p {
            color: #94a3b8;
            font-size: 13px;
        }
        
        .btn {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .privacy-badge {
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
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Account Successfully Downgraded</h1>
        <p class="subtitle">
            Your Paid Account has been downgraded to a Community Account.<br>
            All personal data will be anonymized within 7 days.
        </p>
        
        <div class="info-box">
            <h3>📋 What Has Changed:</h3>
            <ul>
                <li>Your posts and comments are now attributed to "[Deleted User]"</li>
                <li>Your profile has been anonymized and is no longer public</li>
                <li>You can still play games</li>
                <li>You cannot send messages, post, or comment</li>
                <li>Your subscription has been cancelled (no refund issued)</li>
            </ul>
        </div>
        
        <div class="cooldown-notice">
            <h4>⚠️ 30-Day Re-Upgrade Cooldown</h4>
            <p>
                You cannot upgrade back to a Paid Account for 30 days from now. 
                This cooldown period is in place to prevent abuse of the downgrade feature.
            </p>
        </div>
        
        <a href="/main.php" class="btn">Continue to Homepage</a>
        
        <div class="privacy-badge">
            <span>🔒</span> Your privacy is protected - Zero personal data stored
        </div>
    </div>
</body>
</html>

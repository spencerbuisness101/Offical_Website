<?php
/**
 * Suspended Account Page - Spencer's Website v7.0
 * Shown to users whose subscription has expired.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// If NOT suspended, redirect to main
if (empty($_SESSION['is_suspended'])) {
    header('Location: main.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username'] ?? '');
$suspensionReason = '';
$suspendedAt = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT suspension_reason, suspended_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $suspensionReason = $userData['suspension_reason'] ?? 'Subscription expired';
        $suspendedAt = $userData['suspended_at'] ? date('F j, Y', strtotime($userData['suspended_at'])) : '';
    }
} catch (Exception $e) {
    $suspensionReason = 'Subscription expired';
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <style>
        .suspended-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #2d1b1b 50%, #16213e 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .suspended-container {
            background: rgba(30, 20, 20, 0.95);
            border: 2px solid rgba(239, 68, 68, 0.5);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 520px;
            width: 90%;
            backdrop-filter: blur(15px);
            box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2);
        }

        .suspended-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .suspended-container h2 {
            color: #ef4444;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 800;
        }

        .suspended-container .username {
            color: #94a3b8;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .reason-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .reason-box .label {
            color: #ef4444;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .reason-box .value {
            color: #e2e8f0;
            font-size: 15px;
            font-weight: 500;
        }

        .reason-box .date {
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn-resume {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-resume:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 14px 28px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .info-text {
            color: #64748b;
            font-size: 13px;
            margin-top: 20px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="suspended-overlay">
        <div class="suspended-container">
            <div class="suspended-icon">&#9888;&#65039;</div>
            <h2>Account Suspended</h2>
            <p class="username">Hey <?php echo htmlspecialchars($username); ?>, your access has been paused.</p>

            <div class="reason-box">
                <div class="label">Reason</div>
                <div class="value"><?php echo htmlspecialchars($suspensionReason); ?></div>
                <?php if ($suspendedAt): ?>
                <div class="date">Since: <?php echo $suspendedAt; ?></div>
                <?php endif; ?>
            </div>

            <p style="color: #94a3b8; font-size: 14px; line-height: 1.6;">
                Your monthly subscription payment could not be processed. Resume your subscription to regain full access to all premium features.
            </p>

            <div class="action-buttons">
                <a href="index.php" class="btn-resume">Resume Subscription</a>
                <a href="auth/logout.php?csrf_token=<?php echo htmlspecialchars(generateCsrfToken()); ?>" class="btn-logout">Log Out</a>
            </div>

            <p class="info-text">
                If you believe this is an error, please contact the site administrator.
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>

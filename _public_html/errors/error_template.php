<?php
/**
 * Error Page Template - Spencer's Website v5.0
 * Base template for all error pages
 *
 * Usage: Include this file after setting $error_code, $error_title, $error_message, and $error_icon
 */

// Prevent direct access
if (!defined('ERROR_PAGE')) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Include init for session and settings (guarded to prevent infinite 500 loop)
if (!defined('INIT_LOADED')) {
    $init_path = dirname(__DIR__) . '/includes/init.php';
    if (file_exists($init_path)) {
        require_once $init_path;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Get user info if available
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';
$role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'visitor';
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Default values
$error_code = $error_code ?? 500;
$error_title = $error_title ?? 'Something Went Wrong';
$error_message = $error_message ?? 'An unexpected error occurred. Please try again later.';
$error_icon = $error_icon ?? '&#x26A0;';
$error_description = $error_description ?? '';
$error_eta = $error_eta ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Error <?php echo $error_code; ?> - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',system-ui,-apple-system,sans-serif; }

        body {
            background: #04040A;
            min-height: 100vh; color: #E2E8F0;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }

        .error-container {
            background: rgba(255,255,255,0.035);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border-radius: 20px; padding: 48px 40px;
            max-width: 580px; width: 100%;
            border: 0.5px solid rgba(123,110,246,0.22);
            box-shadow: 0 24px 64px rgba(0,0,0,0.55);
            text-align: center; position: relative; overflow: hidden;
        }
        .error-container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, #7B6EF6, #1DFFC4);
        }

        .error-icon {
            font-size: 4rem; margin-bottom: 16px;
            animation: iconBounce 1.8s ease-in-out infinite;
        }
        @keyframes iconBounce {
            0%,100% { transform: translateY(0); }
            45%     { transform: translateY(-10px); }
        }

        .error-code {
            font-size: 5.5rem; font-weight: 700; letter-spacing: -2px;
            background: linear-gradient(135deg,#7B6EF6,#1DFFC4);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            line-height: 1;
            margin-bottom: 8px;
            animation: codeIn 0.6s cubic-bezier(.175,.885,.32,1.275) both;
        }
        @keyframes codeIn {
            from { opacity:0; transform: scale(0.6); }
            to   { opacity:1; transform: scale(1); }
        }

        .error-title {
            font-size: 1.6rem; margin-bottom: 12px;
            color: #f1f5f9; font-weight: 600;
        }
        .error-message {
            font-size: 1rem; color: #94a3b8;
            margin-bottom: 28px; line-height: 1.6;
        }
        .error-detail {
            color: #64748b; font-size: 0.9rem;
            margin-bottom: 14px; line-height: 1.5;
        }
        .error-eta {
            color: #64748b; font-size: 0.85rem; margin-bottom: 20px;
        }

        .error-actions {
            display: flex; gap: 12px;
            justify-content: center; flex-wrap: wrap;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 12px 26px; border-radius: 10px;
            text-decoration: none; font-weight: 600; font-size: 14px;
            transition: transform 0.25s, box-shadow 0.25s;
            border: none; cursor: pointer;
        }
        .btn-primary {
            background: #7B6EF6;
            color: #fff;
            box-shadow: 0 4px 16px rgba(123,110,246,0.28);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(123,110,246,0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.07);
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.14);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.12); color: #f1f5f9;
            transform: translateY(-1px);
        }

        .user-info {
            position: fixed; top: 20px; left: 20px;
            background: rgba(15,23,42,0.8);
            backdrop-filter: blur(10px);
            padding: 9px 14px; border-radius: 8px; font-size: 13px;
            border: 1px solid rgba(78,205,196,0.2); color: #94a3b8;
        }

        .quick-links {
            margin-top: 28px; padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .quick-links-title {
            font-size: 0.8rem; color: #475569;
            text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 12px;
        }
        .quick-links-grid { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .quick-link {
            background: rgba(255,255,255,0.04);
            padding: 8px 14px; border-radius: 6px;
            color: #64748b; text-decoration: none; font-size: 13px;
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.2s;
        }
        .quick-link:hover { background: rgba(255,255,255,0.08); color: #cbd5e1; }

        @media (max-width: 600px) {
            .error-container { padding: 32px 20px; }
            .error-code { font-size: 4rem; }
            .error-title { font-size: 1.3rem; }
            .error-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .user-info { position: static; margin-bottom: 16px; }
        }
    </style>
</head>
<body>
    <?php if ($is_logged_in): ?>
    <div class="user-info">
        <?php echo $username; ?> (<?php echo ucfirst($role); ?>)
    </div>
    <?php endif; ?>

    <div class="error-container">
        <div class="error-icon"><?php echo $error_icon; ?></div>
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo htmlspecialchars($error_title); ?></h1>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>

        <?php if (!empty($error_description)): ?>
        <p class="error-detail"><?php echo nl2br(htmlspecialchars($error_description)); ?></p>
        <?php endif; ?>

        <?php if (!empty($error_eta)): ?>
        <p class="error-eta">&#x23F0; Estimated return: <?php echo htmlspecialchars($error_eta); ?></p>
        <?php endif; ?>

        <div class="error-actions">
            <a href="/main.php" class="btn btn-primary">Go Home</a>
            <button onclick="history.back()" class="btn btn-secondary">Go Back</button>
        </div>

        <div class="quick-links">
            <div class="quick-links-title">Quick Links</div>
            <div class="quick-links-grid">
                <a href="/game.php" class="quick-link">Games</a>
                <?php if (!$is_logged_in): ?>
                <a href="/index.php" class="quick-link">Login</a>
                <?php endif; ?>
                <a href="/info.php" class="quick-link">Info</a>
            </div>
        </div>
    </div>
</body>
</html>

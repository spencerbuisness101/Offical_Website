<?php
/**
 * Delete Account — User Self-Deletion with 30-Day Grace Period
 *
 * Spec: User can delete their account via Settings.
 *   - Account disabled; username → "[Deleted User]" placeholder in UI.
 *   - 30-day grace period: user can restore by logging back in.
 *   - After 30 days, personal data purged by cron/process_account_deletions.php.
 *   - Public posts remain with "[Deleted User]" attribution.
 *
 * Blocked for:
 *   - Community Accounts (no persistent user record)
 *   - Accounts under active Time Removal (suspended)
 *   - Accounts in lockdown (restricted)
 *   - Accounts already terminated
 */

define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/suspension_guard.php';
require_once __DIR__ . '/../config/database.php';

// Must be a real Paid Account
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || (int)($_SESSION['user_id'] ?? 0) === 0) {
    header('Location: /index.php');
    exit;
}

// Community Accounts have no persistent record to delete
if (isset($_SESSION['is_community_account']) && $_SESSION['is_community_account']) {
    header('Location: /main.php');
    exit;
}

// Suspended users cannot delete while serving punishment
requireNotSuspended(false);

$userId = (int)$_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// Check current account status
$stmt = $db->prepare("SELECT account_status, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || in_array($user['account_status'], ['restricted', 'terminated'])) {
    header('Location: /main.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_phrase'] ?? '';

        if ($confirm !== 'DELETE MY ACCOUNT') {
            $error = 'Please type DELETE MY ACCOUNT exactly to confirm.';
        } elseif (empty($password)) {
            $error = 'Please enter your current password.';
        } else {
            // Verify password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($password, $row['password_hash'])) {
                $error = 'Incorrect password.';
            } else {
                // Schedule deletion — 30-day grace period
                $scheduledAt = date('Y-m-d H:i:s', strtotime('+30 days'));

                $stmt = $db->prepare("
                    UPDATE users 
                    SET account_status      = 'pending_deletion',
                        deletion_requested_at  = NOW(),
                        deletion_scheduled_at  = ?
                    WHERE id = ?
                ");
                $stmt->execute([$scheduledAt, $userId]);

                // Log for audit trail
                $stmt = $db->prepare("
                    INSERT INTO system_logs (level, message, user_id, created_at)
                    VALUES ('info', ?, ?, NOW())
                ");
                $stmt->execute(["Account deletion scheduled for user {$user['username']} (ID: {$userId}). Grace period ends {$scheduledAt}.", $userId]);

                $success = true;
                // Keep user logged in during grace period — they can restore by visiting the site
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account — Spencer's Website</title>
    <link rel="stylesheet" href="/styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: rgba(30,41,59,.97);
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 18px;
            padding: 48px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(239,68,68,.12);
        }
        h1 { color: #ef4444; font-size: 1.8rem; margin-bottom: 8px; }
        .subtitle { color: #94a3b8; font-size: .95rem; margin-bottom: 32px; line-height: 1.6; }
        .warning-box {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 28px;
        }
        .warning-box h3 { color: #ef4444; margin-bottom: 10px; font-size: 1rem; }
        .warning-box ul { color: #fca5a5; font-size: .9rem; margin-left: 18px; line-height: 1.8; }
        .grace-box {
            background: rgba(34,197,94,.08);
            border: 1px solid rgba(34,197,94,.25);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 28px;
            color: #86efac;
            font-size: .9rem;
            line-height: 1.6;
        }
        label { display: block; color: #cbd5e1; font-size: .9rem; margin-bottom: 6px; margin-top: 18px; }
        input[type=password], input[type=text] {
            width: 100%; padding: 12px 16px;
            background: rgba(15,23,42,.8);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 8px; color: #f1f5f9; font-size: 1rem;
        }
        input:focus { outline: none; border-color: #ef4444; }
        .btn-danger {
            width: 100%; margin-top: 28px; padding: 14px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff; border: none; border-radius: 10px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: opacity .2s;
        }
        .btn-danger:hover { opacity: .85; }
        .btn-cancel {
            display: block; text-align: center; margin-top: 14px;
            color: #64748b; text-decoration: none; font-size: .9rem;
        }
        .alert-error {
            background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.4);
            color: #fca5a5; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3);
            color: #86efac; border-radius: 8px; padding: 16px; margin-bottom: 20px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>⚠️ Delete Account</h1>
    <p class="subtitle">
        This will schedule your account for permanent deletion. A <strong>30-day grace period</strong>
        applies — you can cancel anytime by logging back in.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">
            <strong>Deletion scheduled.</strong><br>
            Your account will be permanently deleted in 30 days. To cancel, simply log back into your account at any time.<br><br>
            You will remain logged in during the grace period.
        </div>
        <a href="/main.php" class="btn-cancel" style="display:inline-block;margin-top:0;">← Back to site</a>
    <?php else: ?>
        <div class="warning-box">
            <h3>🗑️ What will happen:</h3>
            <ul>
                <li>Your account is immediately marked as pending deletion</li>
                <li>After 30 days, all personal data is permanently purged</li>
                <li>Your public posts remain with "[Deleted User]" attribution</li>
                <li>Your subscription is cancelled — no refund for remaining period</li>
                <li>This cannot be undone after the 30-day grace period</li>
            </ul>
        </div>

        <div class="grace-box">
            ✅ <strong>You have 30 days to change your mind.</strong> Log back in anytime during the grace period to restore your account.
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <label for="password">Current Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <label for="confirm_phrase">Type <strong>DELETE MY ACCOUNT</strong> to confirm</label>
            <input type="text" id="confirm_phrase" name="confirm_phrase" required placeholder="DELETE MY ACCOUNT">

            <button type="submit" class="btn-danger">Schedule Account Deletion</button>
        </form>

        <a href="/set.php" class="btn-cancel">← Cancel and go back to Settings</a>
    <?php endif; ?>
</div>
</body>
</html>

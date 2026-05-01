<?php
/**
 * Local Dev Only — Clear stuck rate-limit state via browser
 * ----------------------------------------------------------------------------
 * Browser-runnable equivalent of `migrations/_local_clear_rate_limits.sql`.
 *
 * USAGE
 *   1. Make sure you're on your local machine (loopback / private LAN).
 *   2. Open in your browser:
 *        http://localhost/migrations/clear_rate_limits_local.php
 *      (or whatever your local URL prefix is — e.g. /Offical_Website/_public_html/migrations/...)
 *   3. Read the preview, then click the red CONFIRM button.
 *   4. Done — login again with correct credentials.
 *
 * SAFETY
 *   - Runs ONLY when REMOTE_ADDR is loopback or RFC-1918 private (127.x, ::1, 10.x, 172.16-31.x, 192.168.x).
 *   - GET request just shows current counts + a CONFIRM form. No mutation.
 *   - POST request requires a same-session token AND a typed phrase ("CLEAR RATE LIMITS").
 *
 * !!! DELETE THIS FILE AFTER YOU'RE UNBLOCKED !!!
 *   This is intentionally not part of the migration runner. It exists only to
 *   recover from the "Too many login attempts" stuck state on a dev machine.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// ---- 1) Local-only gate -----------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
function _isPrivateIp(string $ip): bool {
    if ($ip === '127.0.0.1' || $ip === '::1') return true;
    // IPv4 RFC-1918 / link-local
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = array_map('intval', explode('.', $ip));
        if ($parts[0] === 10) return true;
        if ($parts[0] === 192 && $parts[1] === 168) return true;
        if ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31) return true;
        if ($parts[0] === 169 && $parts[1] === 254) return true;
    }
    // IPv6 unique-local fc00::/7
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $hex = strtolower(bin2hex(@inet_pton($ip)));
        if (substr($hex, 0, 2) === 'fc' || substr($hex, 0, 2) === 'fd') return true;
    }
    return false;
}
if (!_isPrivateIp($ip)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden — this tool is only available from a local/private network IP.\n";
    echo "Detected: " . htmlspecialchars($ip);
    exit;
}

// ---- 2) Bootstrap DB --------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
session_start();

try {
    $db = db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "DB connection failed: " . $e->getMessage();
    exit;
}

// ---- 3) Helpers -------------------------------------------------------------
function tableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function countRows(PDO $db, string $table, string $where = ''): int {
    if (!tableExists($db, $table)) return -1;
    try {
        $sql = "SELECT COUNT(*) FROM `$table`" . ($where ? " WHERE $where" : '');
        return (int)$db->query($sql)->fetchColumn();
    } catch (Throwable $e) { return -1; }
}

// Generate / re-use a per-session confirm token so a stale CSRF can't replay.
if (empty($_SESSION['_clear_rate_token'])) {
    $_SESSION['_clear_rate_token'] = bin2hex(random_bytes(16));
}
$confirmToken = $_SESSION['_clear_rate_token'];

// ---- 4) POST: actually clear ------------------------------------------------
$flash = null;
$cleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken  = $_POST['token']  ?? '';
    $postedPhrase = trim((string)($_POST['phrase'] ?? ''));
    if (!hash_equals($confirmToken, $postedToken)) {
        $flash = ['error', 'Token mismatch — refresh and try again.'];
    } elseif ($postedPhrase !== 'CLEAR RATE LIMITS') {
        $flash = ['error', 'You must type CLEAR RATE LIMITS exactly to confirm.'];
    } else {
        $report = [];
        try {
            if (tableExists($db, 'rate_limit_ip')) {
                $db->exec('TRUNCATE TABLE rate_limit_ip');
                $report[] = 'rate_limit_ip — truncated';
            }
            if (tableExists($db, 'rate_limit_log')) {
                $db->exec('TRUNCATE TABLE rate_limit_log');
                $report[] = 'rate_limit_log — truncated';
            }
            $u = $db->prepare('UPDATE users SET login_attempts = 0, last_failed_login = NULL, locked_until = NULL
                               WHERE login_attempts > 0 OR last_failed_login IS NOT NULL OR locked_until IS NOT NULL');
            $u->execute();
            $report[] = 'users — reset ' . $u->rowCount() . ' row(s) (login_attempts / locked_until cleared)';
            $cleared = true;
            $flash = ['ok', "Cleared.\n\n" . implode("\n", $report)];

            // Rotate the confirm token after success so a refresh can't replay.
            unset($_SESSION['_clear_rate_token']);
        } catch (Throwable $e) {
            $flash = ['error', 'Failed: ' . $e->getMessage()];
        }
    }
}

// ---- 5) Snapshot for the page ----------------------------------------------
$snap = [
    'rate_limit_ip'           => countRows($db, 'rate_limit_ip'),
    'rate_limit_log'          => countRows($db, 'rate_limit_log'),
    'users_with_attempts'     => countRows($db, 'users', 'login_attempts > 0'),
    'users_locked'            => countRows($db, 'users', 'locked_until IS NOT NULL AND locked_until > NOW()'),
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clear rate limits (local dev)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 40px 20px;
            font: 14px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #04040A; color: #E2E8F0; min-height: 100vh;
            display: flex; align-items: flex-start; justify-content: center;
        }
        .wrap { max-width: 720px; width: 100%; }
        h1 { font-size: 22px; font-weight: 300; margin: 0 0 8px; }
        .sub { color: #94A3B8; font-size: 13px; margin-bottom: 28px; }
        .card {
            background: rgba(255,255,255,0.04);
            border: 0.5px solid rgba(255,255,255,0.10);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 18px;
        }
        h2 { font-size: 13px; font-weight: 500; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.1em; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px 0; border-bottom: 0.5px solid rgba(255,255,255,0.06); text-align: left; }
        td:last-child, th:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .num-warn { color: #FBBF24; }
        .num-ok   { color: #1DFFC4; }
        .num-bad  { color: #F87171; }
        label { display: block; font-size: 12px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em; margin: 14px 0 6px; }
        input[type=text] {
            width: 100%; padding: 12px 14px;
            background: rgba(0,0,0,0.3);
            color: #E2E8F0;
            border: 0.5px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            font-family: ui-monospace, monospace;
            font-size: 14px;
        }
        input[type=text]:focus { outline: 2px solid #7B6EF6; outline-offset: 1px; }
        button {
            margin-top: 18px;
            padding: 12px 22px;
            border: none;
            border-radius: 100px;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
        }
        button:hover { box-shadow: 0 8px 20px -8px rgba(239,68,68,0.6); transform: translateY(-1px); }
        .flash-ok    { background: rgba(29,255,196,0.06); border-color: rgba(29,255,196,0.3); white-space: pre-line; }
        .flash-error { background: rgba(248,113,113,0.06); border-color: rgba(248,113,113,0.3); }
        .danger { color: #F87171; font-size: 12px; margin-top: 8px; }
        code { background: rgba(255,255,255,0.06); padding: 1px 6px; border-radius: 4px; font-family: ui-monospace, monospace; font-size: 12px; }
        a { color: #7B6EF6; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Clear rate limits — local dev</h1>
    <p class="sub">
        This tool resets the <code>rate_limit_ip</code> and <code>rate_limit_log</code> tables
        and clears every user's <code>login_attempts</code> / <code>locked_until</code> columns.
        Use this once to recover from a stuck "Too many login attempts" state, then
        <strong>delete this file</strong> from <code>_public_html/migrations/</code>.
    </p>

    <?php if ($flash): ?>
        <div class="card flash-<?= htmlspecialchars($flash[0]) ?>">
            <?= nl2br(htmlspecialchars($flash[1])) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Current state</h2>
        <table>
            <tr><td>rate_limit_ip rows</td>
                <td class="<?= $snap['rate_limit_ip'] > 0 ? 'num-warn' : 'num-ok' ?>">
                    <?= $snap['rate_limit_ip'] === -1 ? 'table missing' : (int)$snap['rate_limit_ip'] ?>
                </td></tr>
            <tr><td>rate_limit_log rows</td>
                <td class="<?= $snap['rate_limit_log'] > 0 ? 'num-warn' : 'num-ok' ?>">
                    <?= $snap['rate_limit_log'] === -1 ? 'table missing' : (int)$snap['rate_limit_log'] ?>
                </td></tr>
            <tr><td>users with login_attempts &gt; 0</td>
                <td class="<?= $snap['users_with_attempts'] > 0 ? 'num-warn' : 'num-ok' ?>">
                    <?= (int)$snap['users_with_attempts'] ?>
                </td></tr>
            <tr><td>users currently locked_until in the future</td>
                <td class="<?= $snap['users_locked'] > 0 ? 'num-bad' : 'num-ok' ?>">
                    <?= (int)$snap['users_locked'] ?>
                </td></tr>
        </table>
    </div>

    <?php if (!$cleared): ?>
    <div class="card">
        <h2>Confirm</h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($confirmToken) ?>">
            <label for="phrase">Type <code>CLEAR RATE LIMITS</code> to enable the button</label>
            <input id="phrase" name="phrase" type="text" required pattern="CLEAR RATE LIMITS"
                   placeholder="CLEAR RATE LIMITS">
            <button type="submit">TRUNCATE rate_limit tables &amp; reset user lockouts</button>
            <p class="danger">After this works, DELETE this file: <code>_public_html/migrations/clear_rate_limits_local.php</code></p>
        </form>
    </div>
    <?php else: ?>
    <div class="card">
        <h2>Next steps</h2>
        <ol style="padding-left: 18px; line-height: 1.8;">
            <li>Go back to <a href="/index.php">/index.php</a> and sign in normally.</li>
            <li>Delete this file: <code>_public_html/migrations/clear_rate_limits_local.php</code></li>
        </ol>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

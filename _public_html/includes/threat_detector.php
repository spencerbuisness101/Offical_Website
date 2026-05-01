<?php
/**
 * Live Threat Detector - Spencer's Website v7.0 Elite
 * Auto-blocks IPs with 5+ failed login attempts within 10 minutes.
 * Include early in init.php to block malicious IPs before any page logic runs.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Check if the current IP is blocked. If so, show 403 and exit.
 * @param PDO $db
 */
function checkBlockedIp($db) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;

    try {
        // Ensure blocked_ips table exists
        $db->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            reason VARCHAR(255) DEFAULT 'Auto-blocked: excessive failed logins',
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            blocked_by VARCHAR(50) DEFAULT 'system',
            INDEX idx_ip (ip_address),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Check if this IP is currently blocked
        $stmt = $db->prepare("SELECT id, expires_at FROM blocked_ips WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        $blocked = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($blocked) {
            // Check if block has expired
            if ($blocked['expires_at'] && strtotime($blocked['expires_at']) < time()) {
                // Expired — remove the block
                $del = $db->prepare("DELETE FROM blocked_ips WHERE id = ?");
                $del->execute([$blocked['id']]);
                $del->closeCursor();
                return; // Allow through
            }
            // Still blocked
            http_response_code(403);
            error_log("THREAT DETECTOR: Blocked IP {$ip} attempted access");
            die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title><style>body{background:#0a0e1a;color:#e2e8f0;font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}.c{text-align:center;max-width:400px;}.c h1{color:#ef4444;font-size:2rem;}p{color:#94a3b8;}</style></head><body><div class="c"><h1>Access Denied</h1><p>Your IP has been temporarily blocked due to suspicious activity. This block will expire automatically. If you believe this is an error, please contact the site administrator.</p></div></body></html>');
        }
    } catch (Exception $e) {
        error_log("Threat detector error: " . $e->getMessage());
        // Don't block on error — fail open
    }
}

/**
 * Scan for IPs with excessive failed logins and auto-block them.
 * Called after failed login attempts.
 * @param PDO $db
 * @param string $ip The IP that just failed
 */
function autoBlockCheck($db, $ip = null) {
    if (!$ip) $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;

    try {
        // Count failed attempts from this IP in the last 10 minutes
        $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limit_ip WHERE ip_address = ? AND endpoint = 'login' AND window_start > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $stmt->execute([$ip]);
        $count = (int)$stmt->fetchColumn();
        $stmt->closeCursor();

        if ($count >= 5) {
            // Auto-block for 30 minutes
            $expiresAt = date('Y-m-d H:i:s', time() + 1800);
            $stmt = $db->prepare("INSERT INTO blocked_ips (ip_address, reason, expires_at, blocked_by) VALUES (?, ?, ?, 'system') ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)");
            $stmt->execute([$ip, "Auto-blocked: {$count} failed logins in 10 minutes", $expiresAt]);
            $stmt->closeCursor();
            error_log("THREAT DETECTOR: Auto-blocked IP {$ip} — {$count} failed logins in 10 minutes");
        }
    } catch (Exception $e) {
        error_log("Auto-block check error: " . $e->getMessage());
    }
}

/**
 * Get all currently blocked IPs for admin dashboard.
 * @param PDO $db
 * @return array
 */
function getBlockedIps($db) {
    try {
        $stmt = $db->query("SELECT id, ip_address, blocked_at, expires_at, reason, blocked_by FROM blocked_ips ORDER BY blocked_at DESC LIMIT 100");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Unblock an IP (admin action).
 * @param PDO $db
 * @param string $ip
 * @return bool
 */
function unblockIp($db, $ip) {
    try {
        $stmt = $db->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        error_log("THREAT DETECTOR: Admin unblocked IP {$ip}");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

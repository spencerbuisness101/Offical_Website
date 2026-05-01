<?php
/**
 * DB-backed IP Rate Limiting - Spencer's Website v7.0
 * Cannot be bypassed by clearing cookies/sessions.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Check and increment IP-based rate limit.
 * @param PDO $db
 * @param string $endpoint  Identifier for the rate-limited action
 * @param int $max          Max requests per window
 * @param int $windowSec    Window size in seconds
 * @return bool True if within limit, false if exceeded
 */
function checkIpRateLimit($db, $endpoint, $max = 5, $windowSec = 60) {
    $rawIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // SEC-H1: Store hashed IP — never raw IP — to prevent PII exposure on DB breach
    $pepper = $_ENV['PEPPER_SECRET'] ?? getenv('PEPPER_SECRET') ?? '';
    $ip = hash('sha256', $rawIp . $pepper);

    // Cleanup is handled by cron/cleanup_rate_limits.php

    try {
        // Get current count for this IP + endpoint within window
        $stmt = $db->prepare("
            SELECT id, request_count, window_start
            FROM rate_limit_ip
            WHERE ip_address = ? AND endpoint = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY window_start DESC
            LIMIT 1
        ");
        $stmt->execute([$ip, $endpoint, $windowSec]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            if ((int)$row['request_count'] >= $max) {
                return false;
            }
            // Increment
            $db->prepare("UPDATE rate_limit_ip SET request_count = request_count + 1 WHERE id = ?")
               ->execute([$row['id']]);
            return true;
        }

        // No existing window — start a new one
        $db->prepare("INSERT INTO rate_limit_ip (ip_address, endpoint, request_count, window_start) VALUES (?, ?, 1, NOW())")
           ->execute([$ip, $endpoint]);
        return true;

    } catch (Exception $e) {
        error_log("IP rate limit check error: " . $e->getMessage());
        // Throw so callers can decide whether to fail-open or fail-closed.
        // Previously returned false (fail-closed), which caused permanent
        // login lockouts when the rate_limit_ip table was missing or errored.
        throw new RuntimeException("IP rate limit check failed: " . $e->getMessage(), 0, $e);
    }
}

/**
 * Enforce IP rate limit — returns 429 and exits if exceeded.
 * @param PDO $db
 * @param string $endpoint
 * @param int $max
 * @param int $windowSec
 */
function enforceIpRateLimit($db, $endpoint, $max = 5, $windowSec = 60) {
    if (!checkIpRateLimit($db, $endpoint, $max, $windowSec)) {
        http_response_code(429);
        if (!headers_sent()) {
            header('Retry-After: ' . $windowSec);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $windowSec
        ]);
        exit;
    }
}

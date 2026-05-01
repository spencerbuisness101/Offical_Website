<?php
/**
 * Fingerprint Ban Helper Functions
 * Phase 4 — Integrated with banned_devices / banned_ips tables
 * Backward-compatible fallback to banned_fingerprints / device_fingerprints
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Check if a fingerprint is banned.
 * Phase 4: checks banned_devices + banned_ips; falls back to banned_fingerprints.
 */
function isFingerprintBanned(PDO $db, string $fingerprintHash, ?string $ipAddress = null): array {
    // 1. Phase 4 — banned_devices (permanent by design)
    try {
        $stmt = $db->prepare("SELECT fingerprint_hash, reason, banned_at FROM banned_devices WHERE fingerprint_hash = ? LIMIT 1");
        $stmt->execute([$fingerprintHash]);
        $ban = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($ban) {
            return [
                'banned' => true,
                'reason' => $ban['reason'],
                'permanent' => true,
                'expires_at' => null,
                'banned_at' => $ban['banned_at'],
                'source' => 'banned_devices'
            ];
        }
    } catch (Exception $e) {
        error_log("Phase 4 banned_devices check error: " . $e->getMessage());
    }

    // 2. Phase 4 — banned_ips
    if ($ipAddress) {
        try {
            $stmt = $db->prepare("SELECT ip_address, reason, banned_at, expires_at FROM banned_ips WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmt->execute([$ipAddress]);
            $ban = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($ban) {
                return [
                    'banned' => true,
                    'reason' => $ban['reason'],
                    'permanent' => is_null($ban['expires_at'] ?? null),
                    'expires_at' => $ban['expires_at'] ?? null,
                    'banned_at' => $ban['banned_at'],
                    'source' => 'banned_ips'
                ];
            }
        } catch (Exception $e) {
            error_log("Phase 4 banned_ips check error: " . $e->getMessage());
        }
    }

    // 3. Legacy fallback — banned_fingerprints
    try {
        $stmt = $db->prepare("SELECT fingerprint_hash, ban_reason, permanent, expires_at, banned_at FROM banned_fingerprints WHERE fingerprint_hash = ? AND (permanent = TRUE OR expires_at > NOW()) LIMIT 1");
        $stmt->execute([$fingerprintHash]);
        $ban = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($ban) {
            return [
                'banned' => true,
                'reason' => $ban['ban_reason'],
                'permanent' => (bool)$ban['permanent'],
                'expires_at' => $ban['expires_at'],
                'banned_at' => $ban['banned_at'],
                'source' => 'banned_fingerprints'
            ];
        }
    } catch (Exception $e) {
        error_log("Legacy banned_fingerprints check error: " . $e->getMessage());
    }

    return ['banned' => false];
}

/**
 * Ban a fingerprint — writes to Phase 4 banned_devices and legacy banned_fingerprints.
 */
function banFingerprint(PDO $db, string $fingerprintHash, string $reason = 'Suspicious activity detected', bool $permanent = false, ?int $bannedBy = null, ?DateTime $expiresAt = null): bool {
    $success = false;

    // Phase 4 — banned_devices (no expiration in schema; always permanent)
    try {
        $stmt = $db->prepare("INSERT INTO banned_devices (fingerprint_hash, banned_user_id, banned_at, reason) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE banned_at = NOW(), reason = VALUES(reason), banned_user_id = VALUES(banned_user_id)");
        $stmt->execute([$fingerprintHash, $bannedBy, $reason]);
        $success = true;
    } catch (Exception $e) {
        error_log("Phase 4 banFingerprint insert error: " . $e->getMessage());
    }

    // Legacy — banned_fingerprints
    try {
        $stmt = $db->prepare("DELETE FROM banned_fingerprints WHERE fingerprint_hash = ? AND permanent = FALSE");
        $stmt->execute([$fingerprintHash]);
        $stmt = $db->prepare("INSERT INTO banned_fingerprints (fingerprint_hash, ban_reason, permanent, expires_at, banned_by) VALUES (?, ?, ?, ?, ?)");
        $expiresAtStr = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;
        $stmt->execute([$fingerprintHash, $reason, $permanent, $expiresAtStr, $bannedBy]);
        $success = true;
    } catch (Exception $e) {
        error_log("Legacy banFingerprint insert error: " . $e->getMessage());
    }

    return $success;
}

/**
 * Unban a fingerprint — removes from both Phase 4 and legacy tables.
 */
function unbanFingerprint(PDO $db, string $fingerprintHash): bool {
    $success = false;
    try {
        $stmt = $db->prepare("DELETE FROM banned_devices WHERE fingerprint_hash = ?");
        $stmt->execute([$fingerprintHash]);
        $success = ($stmt->rowCount() > 0);
    } catch (Exception $e) {
        error_log("Phase 4 unban error: " . $e->getMessage());
    }
    try {
        $stmt = $db->prepare("DELETE FROM banned_fingerprints WHERE fingerprint_hash = ?");
        $stmt->execute([$fingerprintHash]);
        if ($stmt->rowCount() > 0) $success = true;
    } catch (Exception $e) {
        error_log("Legacy unban error: " . $e->getMessage());
    }
    return $success;
}

/**
 * Get all banned fingerprints — merges Phase 4 banned_devices + legacy banned_fingerprints.
 */
function getBannedFingerprints(PDO $db, bool $includeExpired = false): array {
    $results = [];

    // Phase 4 — banned_devices
    try {
        $sql = "SELECT bd.fingerprint_hash, bd.reason as ban_reason, bd.banned_at, u.username as banned_by_username, TRUE as permanent, NULL as expires_at FROM banned_devices bd LEFT JOIN users u ON bd.banned_user_id = u.id";
        $sql .= " ORDER BY bd.banned_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("Phase 4 getBannedFingerprints error: " . $e->getMessage());
    }

    // Legacy — banned_fingerprints
    try {
        $sql = "SELECT bf.*, u.username as banned_by_username FROM banned_fingerprints bf LEFT JOIN users u ON bf.banned_by = u.id";
        if (!$includeExpired) {
            $sql .= " WHERE bf.permanent = TRUE OR bf.expires_at > NOW()";
        }
        $sql .= " ORDER BY bf.banned_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("Legacy getBannedFingerprints error: " . $e->getMessage());
    }

    return $results;
}

/**
 * Clean up expired fingerprint bans — only legacy table has expiration support.
 */
function cleanupExpiredFingerprintBans(PDO $db): int {
    try {
        $stmt = $db->prepare("DELETE FROM banned_fingerprints WHERE permanent = FALSE AND expires_at <= NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("cleanupExpiredFingerprintBans error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user should be flagged for suspicious activity.
 * Checks device_links, then user_devices (Phase 4), then device_fingerprints (legacy).
 */
function shouldFlagFingerprint(PDO $db, string $fingerprintHash, int $currentUserId): bool {
    // Check device_links for multiple users
    try {
        $stmt = $db->prepare("SELECT linked_user_ids FROM device_links WHERE fingerprint_hash = ? LIMIT 1");
        $stmt->execute([$fingerprintHash]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($link) {
            $linkedIds = json_decode($link['linked_user_ids'], true) ?: [];
            $otherUsers = array_filter($linkedIds, fn($id) => $id !== $currentUserId);
            if (count($otherUsers) >= 2) {
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("shouldFlagFingerprint device_links error: " . $e->getMessage());
    }

    // Phase 4 — user_devices
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as user_count FROM user_devices WHERE fingerprint_hash = ? AND user_id IS NOT NULL AND last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$fingerprintHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (($result['user_count'] ?? 0) >= 3) {
            return true;
        }
    } catch (Exception $e) {
        error_log("Phase 4 shouldFlagFingerprint user_devices error: " . $e->getMessage());
    }

    // Legacy — device_fingerprints
    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as user_count FROM device_fingerprints WHERE fingerprint_hash = ? AND user_id IS NOT NULL AND last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$fingerprintHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (($result['user_count'] ?? 0) >= 3) {
            return true;
        }
    } catch (Exception $e) {
        error_log("Legacy shouldFlagFingerprint device_fingerprints error: " . $e->getMessage());
    }

    return false;
}
?>

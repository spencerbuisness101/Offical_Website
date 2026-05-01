<?php
/**
 * Device Fingerprint Helper Functions - Spencer's Website v7.0
 * Server-side fingerprint storage, linking, and fraud detection.
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Store or update a device fingerprint record.
 * Phase 4: writes to user_devices (primary) and device_fingerprints (legacy rich data).
 */
function storeFingerprint(PDO $db, array $data): int {
    $hash = $data['fingerprint_hash'] ?? '';
    $uuid = $data['device_uuid'] ?? '';
    if (!$hash || !$uuid) return 0;

    $userId = $data['user_id'] ?? null;
    $ipAddress = $data['ip_address'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // ---- Phase 4: user_devices (simpler tracking table) ----
    try {
        if ($userId) {
            $stmt = $db->prepare("
                INSERT INTO user_devices (user_id, fingerprint_hash, ip_address, user_agent, first_seen, last_seen, login_count)
                VALUES (?, ?, ?, ?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE
                    last_seen = NOW(),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    login_count = login_count + 1
            ");
            $stmt->execute([$userId, $hash, $ipAddress, $userAgent]);
        }
    } catch (Exception $e) {
        error_log("Phase 4 storeFingerprint user_devices error: " . $e->getMessage());
    }

    // ---- Legacy: device_fingerprints (rich fingerprint data) ----
    try {
        $stmt = $db->prepare("SELECT id, visit_count FROM device_fingerprints WHERE fingerprint_hash = ? AND device_uuid = ? LIMIT 1");
        $stmt->execute([$hash, $uuid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $db->prepare("UPDATE device_fingerprints SET
                user_id = COALESCE(?, user_id),
                ip_address = ?,
                visit_count = visit_count + 1,
                last_seen = CURRENT_TIMESTAMP
                WHERE id = ?");
            $stmt->execute([$userId, $ipAddress, $existing['id']]);
            return $existing['id'];
        }

        $stmt = $db->prepare("INSERT INTO device_fingerprints
            (user_id, device_uuid, fingerprint_hash, screen_resolution, gpu_renderer, canvas_hash, font_list_hash, timezone, language, platform, user_agent_hash, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $uuid,
            $hash,
            $data['screen_resolution'] ?? '',
            $data['gpu_renderer'] ?? '',
            $data['canvas_hash'] ?? '',
            $data['font_list_hash'] ?? '',
            $data['timezone'] ?? '',
            $data['language'] ?? '',
            $data['platform'] ?? '',
            $data['user_agent_hash'] ?? '',
            $ipAddress
        ]);
        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        error_log("Legacy storeFingerprint device_fingerprints error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Link a fingerprint hash to a user ID in the device_links table.
 */
function linkFingerprintToUser(PDO $db, string $hash, int $userId): void {
    if (!$hash || !$userId) return;

    $stmt = $db->prepare("SELECT id, linked_user_ids FROM device_links WHERE fingerprint_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $linkedIds = json_decode($existing['linked_user_ids'], true) ?: [];
        if (!in_array($userId, $linkedIds)) {
            $linkedIds[] = $userId;
            $confidence = min(count($linkedIds) * 0.25, 1.0);
            $stmt = $db->prepare("UPDATE device_links SET linked_user_ids = ?, confidence_score = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([json_encode($linkedIds), $confidence, $existing['id']]);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO device_links (fingerprint_hash, linked_user_ids, confidence_score) VALUES (?, ?, 0.25)");
        $stmt->execute([$hash, json_encode([$userId])]);
    }
}

/**
 * Get all user IDs linked to a fingerprint hash.
 */
function getLinkedUsers(PDO $db, string $hash): array {
    $stmt = $db->prepare("SELECT linked_user_ids FROM device_links WHERE fingerprint_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];
    return json_decode($row['linked_user_ids'], true) ?: [];
}

/**
 * Detect potential fraud: flag if the same fingerprint has multiple users.
 * Returns array with 'is_fraud' boolean and 'details' string.
 */
function detectFraud(PDO $db, string $hash, int $userId): array {
    $linkedUsers = getLinkedUsers($db, $hash);
    $otherUsers = array_filter($linkedUsers, fn($id) => $id !== $userId);

    if (count($otherUsers) >= 2) {
        return [
            'is_fraud' => true,
            'details' => 'Fingerprint shared by ' . count($linkedUsers) . ' users: ' . implode(', ', $linkedUsers),
            'risk_score' => min(count($otherUsers) * 0.3, 1.0)
        ];
    }

    return ['is_fraud' => false, 'details' => '', 'risk_score' => 0.0];
}

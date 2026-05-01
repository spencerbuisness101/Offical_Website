<?php
/**
 * Device Fingerprint Tracking API - Spencer's Website v7.0
 * Receives fingerprint data from js/fingerprint.js and stores it.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rate_limit_ip.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limit: 2 requests per minute per IP
// Uses DB-backed rate limiting (SHA-256 hashed IP) — immune to cookie/session bypass
enforceIpRateLimit(db(), 'fingerprint_track', 2, 60);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['fingerprint_hash']) || empty($input['device_uuid'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $db = db();

    // Ensure tables exist
    $db->exec("CREATE TABLE IF NOT EXISTS device_fingerprints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        device_uuid VARCHAR(64) NOT NULL,
        fingerprint_hash VARCHAR(64) NOT NULL,
        screen_resolution VARCHAR(20),
        gpu_renderer VARCHAR(255),
        canvas_hash VARCHAR(64),
        font_list_hash VARCHAR(64),
        timezone VARCHAR(50),
        language VARCHAR(10),
        platform VARCHAR(50),
        user_agent_hash VARCHAR(64),
        ip_address VARCHAR(45),
        first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        visit_count INT DEFAULT 1,
        INDEX idx_fingerprint (fingerprint_hash),
        INDEX idx_uuid (device_uuid),
        INDEX idx_user (user_id),
        INDEX idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS device_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fingerprint_hash VARCHAR(64) NOT NULL,
        linked_user_ids JSON,
        confidence_score DECIMAL(5,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fp (fingerprint_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add IP and user ID
    $input['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $input['user_id'] = $_SESSION['user_id'] ?? null;

    // Store fingerprint
    $fingerprintId = storeFingerprint($db, $input);

    // Link to user if logged in
    if (!empty($_SESSION['user_id'])) {
        linkFingerprintToUser($db, $input['fingerprint_hash'], (int)$_SESSION['user_id']);
    }

    echo json_encode(['success' => true, 'id' => $fingerprintId]);

} catch (Exception $e) {
    error_log("Fingerprint tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

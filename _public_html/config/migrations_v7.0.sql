-- Spencer's Website v7.0 Migrations
-- Run this in phpMyAdmin after importing the v6.0 database dump

-- ============================================
-- Device Fingerprints (multi-layer tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS device_fingerprints (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Device-to-user linking (fraud detection)
-- ============================================
CREATE TABLE IF NOT EXISTS device_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fingerprint_hash VARCHAR(64) NOT NULL,
    linked_user_ids JSON,
    confidence_score DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fp (fingerprint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- User Feedback
-- ============================================
CREATE TABLE IF NOT EXISTS user_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    admin_response TEXT NULL,
    status ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Payment Fraud Log
-- ============================================
CREATE TABLE IF NOT EXISTS payment_fraud_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45),
    action VARCHAR(50),
    details TEXT,
    risk_score DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Schema changes to existing tables
-- ============================================
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS created_by_role VARCHAR(20) DEFAULT 'admin';

-- Phase 4 Security Implementation Tables
-- Device fingerprinting, ban evasion detection, rate limiting

-- User devices table (for fingerprinting Paid Accounts)
CREATE TABLE IF NOT EXISTS user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fingerprint_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    first_seen DATETIME NOT NULL,
    last_seen DATETIME NOT NULL,
    login_count INT DEFAULT 1,
    is_suspicious BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_device (user_id, fingerprint_hash),
    INDEX idx_fingerprint (fingerprint_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_last_seen (last_seen),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Banned devices table
CREATE TABLE IF NOT EXISTS banned_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fingerprint_hash VARCHAR(255) NOT NULL,
    banned_user_id INT,
    banned_at DATETIME NOT NULL,
    reason VARCHAR(100),
    UNIQUE KEY unique_banned_device (fingerprint_hash),
    INDEX idx_banned_user (banned_user_id),
    FOREIGN KEY (banned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Banned IPs table
CREATE TABLE IF NOT EXISTS banned_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    banned_user_id INT,
    banned_at DATETIME NOT NULL,
    reason VARCHAR(100),
    expires_at DATETIME,
    UNIQUE KEY unique_banned_ip (ip_address),
    INDEX idx_banned_user (banned_user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (banned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email patterns for ban evasion detection
CREATE TABLE IF NOT EXISTS ban_email_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_hash VARCHAR(255) NOT NULL,
    normalized_local VARCHAR(100),
    domain VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_hash (email_hash),
    INDEX idx_normalized (normalized_local),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Username patterns for ban evasion detection
CREATE TABLE IF NOT EXISTS banned_username_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    normalized_username VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_pattern (user_id),
    INDEX idx_normalized (normalized_username),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ban evasion detection logs
CREATE TABLE IF NOT EXISTS ban_evasion_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempted_email VARCHAR(255),
    attempted_username VARCHAR(50),
    detection_confidence INT,
    matched_accounts JSON,
    action VARCHAR(20), -- 'allow', 'block', 'terminate'
    detected_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    new_user_id INT,
    matched_user_id INT,
    INDEX idx_detected_at (detected_at),
    INDEX idx_ip (ip_address),
    INDEX idx_action (action),
    FOREIGN KEY (new_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limiting log
CREATE TABLE IF NOT EXISTS rate_limit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    success BOOLEAN DEFAULT TRUE,
    INDEX idx_action_identifier (action, identifier),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts log (for security analysis)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_success (success),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns to users table for Phase 4
-- NOTE: AFTER clauses removed — referenced columns may not exist in all schemas
ALTER TABLE users
ADD COLUMN IF NOT EXISTS device_fingerprint VARCHAR(255),
ADD COLUMN IF NOT EXISTS ban_evasion_detected BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS ban_evasion_confidence INT,
ADD COLUMN IF NOT EXISTS escalation_reason TEXT;

-- Add index for device fingerprint lookups
CREATE INDEX IF NOT EXISTS idx_device_fingerprint ON users(device_fingerprint);

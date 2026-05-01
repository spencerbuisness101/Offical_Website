-- Migration: Create Moderation & Security Tables
-- For: Community Standards enforcement, login tracking, strike system

-- Note: user_strikes table moved to migrations/013_consolidate_user_strikes.sql

-- Login history for new device detection
CREATE TABLE IF NOT EXISTS user_login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    is_new_device BOOLEAN DEFAULT FALSE,
    notification_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time (user_id, created_at),
    INDEX idx_ip (ip_address),
    INDEX idx_new_device (user_id, is_new_device)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appeal submissions for lockdown mode
CREATE TABLE IF NOT EXISTS user_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    strike_id INT,
    appeal_text TEXT NOT NULL,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    reviewed_by INT NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add columns to users table for account status tracking
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'restricted', 'suspended') DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS lockdown_reason TEXT NULL COMMENT 'Private admin-only reason',
    ADD COLUMN IF NOT EXISTS lockdown_reason_public VARCHAR(255) NULL COMMENT 'Sanitized reason shown to user',
    ADD COLUMN IF NOT EXISTS lockdown_until TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS termination_reason TEXT NULL,
    ADD COLUMN IF NOT EXISTS terminated_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deletion_scheduled_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS current_strike_count TINYINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_strike_at TIMESTAMP NULL;

-- Ensure SYSTEM account exists (User ID 0)
-- Only insert if the referenced columns actually exist
SET @has_role := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @has_active := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active');
SET @has_created := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'created_at');
SET @can_insert := (@has_role > 0 AND @has_active > 0 AND @has_created > 0);
SET @sql := IF(@can_insert > 0,
    'INSERT INTO users (id, username, password_hash, role, is_active, created_at)
     VALUES (0, ''SYSTEM'', ''$2y$10$INVALID_HASH_DO_NOT_USE'', ''system'', 0, NOW())
     ON DUPLICATE KEY UPDATE id = 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Spencer's Website v5.0 Database Migrations
-- Run these migrations on MySQL to enable v5.0 features
-- Note: The admin panel will auto-run these on first visit, but you can run manually if needed

-- 1. Site settings table for maintenance mode and other site-wide settings
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default maintenance mode setting
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0');
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');

-- 2. User storage table for personalized user data
CREATE TABLE IF NOT EXISTS user_storage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    storage_key VARCHAR(100) NOT NULL,
    storage_value LONGTEXT,
    storage_type ENUM('json', 'text') DEFAULT 'json',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_storage (user_id, storage_key),
    INDEX idx_user_id (user_id),
    INDEX idx_storage_key (storage_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Performance indexes for existing tables
-- Note: These may fail if indexes already exist, which is fine

-- Index for page_views timestamp (analytics performance)
CREATE INDEX IF NOT EXISTS idx_page_views_timestamp ON page_views(timestamp);

-- Index for system_logs timestamp (log retrieval performance)
CREATE INDEX IF NOT EXISTS idx_system_logs_timestamp ON system_logs(timestamp);

-- Index for user_sessions last_activity (session tracking performance)
CREATE INDEX IF NOT EXISTS idx_user_sessions_activity ON user_sessions(last_activity);

-- 4. Add password_changed_at column to users table for tracking password changes
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL DEFAULT NULL;

-- 5. Create admin_audit_log table for tracking sensitive admin actions
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_user_id INT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_user_id (admin_user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - These migrations are idempotent (safe to run multiple times)
-- - The admin panel's createAnalyticsTables() function also runs these automatically
-- - MySQL may not support IF NOT EXISTS on some commands, errors are expected

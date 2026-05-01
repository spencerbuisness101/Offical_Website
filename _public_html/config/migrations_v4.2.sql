-- Spencer's Website v4.2 Database Migrations
-- Run these migrations on MySQL to enable v4.2 features
-- Note: The admin panel will auto-run these on first visit, but you can run manually if needed

-- 1. Add tags column to announcements table (for announcement tagging feature)
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS tags VARCHAR(255) NULL AFTER type;

-- 2. Add name_tag column to users table (for custom chat name tags)
ALTER TABLE users ADD COLUMN IF NOT EXISTS name_tag VARCHAR(50) NULL;

-- 3. Ensure emoji support (utf8mb4) for announcements
ALTER TABLE announcements CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4. Ensure emoji support for chat messages
ALTER TABLE yaps_chat_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. Create user_settings table if it doesn't exist (for name tags via settings)
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_setting (user_id, setting_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- - These migrations are idempotent (safe to run multiple times)
-- - The admin panel's createAnalyticsTables() function also runs these automatically
-- - MySQL may not support IF NOT EXISTS on ALTER TABLE, so errors are expected

-- Add performance indexes for frequently queried columns
-- Each index creation checks: table exists AND first column exists

-- Indexes for announcements table
SET @tbl := 'announcements';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'created_by_role');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_created_by_role (created_by_role)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'is_active');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_is_active (is_active)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'priority');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_priority (priority)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'target_audience');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_target_audience (target_audience)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'expiry_date');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_expiry_date (expiry_date)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'created_at');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'is_active');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE announcements ADD INDEX IF NOT EXISTS idx_active_priority (is_active, priority, created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for user_sessions table
SET @tbl := 'user_sessions';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'last_activity');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_last_activity (last_activity)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'user_id');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_user_id (user_id)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_sessions_activity_user (last_activity, user_id)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for ai_chat_history table
SET @tbl := 'ai_chat_history';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'user_id');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE ai_chat_history ADD INDEX IF NOT EXISTS idx_user_created (user_id, created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'persona');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE ai_chat_history ADD INDEX IF NOT EXISTS idx_persona (persona)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for user_settings table
SET @tbl := 'user_settings';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'user_id');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE user_settings ADD INDEX IF NOT EXISTS idx_user_key (user_id, setting_key)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for webhook_events table
SET @tbl := 'webhook_events';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'provider');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE webhook_events ADD INDEX IF NOT EXISTS idx_provider_event (provider, event_id)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'created_at');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE webhook_events ADD INDEX IF NOT EXISTS idx_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for contributor_ideas table
SET @tbl := 'contributor_ideas';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'user_id');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE contributor_ideas ADD INDEX IF NOT EXISTS idx_user_status (user_id, status)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'created_at');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE contributor_ideas ADD INDEX IF NOT EXISTS idx_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes for designer_backgrounds table
SET @tbl := 'designer_backgrounds';
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'user_id');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE designer_backgrounds ADD INDEX IF NOT EXISTS idx_user_status (user_id, status)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'is_active');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE designer_backgrounds ADD INDEX IF NOT EXISTS idx_is_active (is_active)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'created_at');
SET @s := IF(@table_exists > 0 AND @col_exists > 0, 'ALTER TABLE designer_backgrounds ADD INDEX IF NOT EXISTS idx_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

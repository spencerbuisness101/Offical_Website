-- =============================================================================
-- Migration 009 — Performance indexes on hot query paths
-- =============================================================================
-- All indexes conditional on table/column existence
-- =============================================================================

-- Users table indexes
SET @tbl := 'users';
SET @exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);

-- email index — column may not exist
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email');
SET @s := IF(@exists > 0 AND @col > 0, 'CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- locked_until — column added by older migration
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locked_until');
SET @s := IF(@exists > 0 AND @col > 0, 'CREATE INDEX IF NOT EXISTS idx_users_locked ON users(locked_until)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_active, role — original columns
SET @col1 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active');
SET @col2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @s := IF(@exists > 0 AND @col1 > 0 AND @col2 > 0, 'CREATE INDEX IF NOT EXISTS idx_users_active_role ON users(is_active, role)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- last_login — may not exist on all schemas
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login');
SET @s := IF(@exists > 0 AND @col > 0, 'CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Smail table indexes
SET @tbl := 'smail_messages';
SET @exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s := IF(@exists > 0, 'CREATE INDEX IF NOT EXISTS idx_smail_inbox ON smail_messages(receiver_id, read_status)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := IF(@exists > 0, 'CREATE INDEX IF NOT EXISTS idx_smail_created ON smail_messages(created_at DESC)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rate limiting table index
SET @tbl := 'rate_limit_ip';
SET @exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s := IF(@exists > 0, 'CREATE INDEX IF NOT EXISTS idx_ratelimit_window ON rate_limit_ip(ip_address, endpoint, window_start)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

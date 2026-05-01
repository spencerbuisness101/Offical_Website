-- Migration 005: Add google_id column for OAuth tracking
-- Required for proper Google account linking

-- =====================================================
-- C1: Add google_id column to users table
-- =====================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL COMMENT 'Google OAuth subject identifier',
    ADD UNIQUE INDEX IF NOT EXISTS uk_google_id (google_id) COMMENT 'Prevent duplicate Google accounts';

-- =====================================================
-- M2/M5: Add unique index on email for login performance and integrity
-- Only runs if the email column exists (not all schemas have it)
-- =====================================================
SET @email_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email');
SET @sql := IF(@email_exists > 0, 'ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uk_email (email)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add index on email for fast lookup (in case unique index not supported)
-- =====================================================
SET @sql2 := IF(@email_exists > 0, 'CREATE INDEX IF NOT EXISTS idx_email ON users(email)', 'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

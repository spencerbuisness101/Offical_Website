-- =============================================================================
-- Migration 017 — Unify all tables to utf8mb4_unicode_ci collation
-- =============================================================================
-- Ensures consistent charset + collation across the entire database.
-- Uses dynamic SQL via INFORMATION_SCHEMA to only convert tables that exist.
-- =============================================================================

-- Helper: convert a single table if it exists
-- Usage: CALL ConvertTable('tablename');
-- Uses prepared statement to avoid repeating the logic

-- Convert all known tables to utf8mb4_unicode_ci
-- Each block checks table existence before converting

-- Phase 1: Community & Compliance
SET @tbl = 'community_sessions';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'age_verification_log';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'parental_consent';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'vpc_rate_limit';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'lockdown_appeals';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'account_downgrades';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Phase 2: Security & Device Tracking
SET @tbl = 'user_devices';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'banned_devices';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'banned_ips';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'rate_limit_log';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'rate_limit_ip';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'login_attempts';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'device_fingerprints';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'device_links';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Phase 3: Messaging & Notifications
SET @tbl = 'smail_messages';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'ephemeral_notifications';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'notification_queue';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'notifications';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'websocket_sessions';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'yaps_chat_messages';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Phase 4: AI & Content
SET @tbl = 'ai_chat_history';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'contributor_ideas';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'designer_backgrounds';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Phase 5: Core & System
SET @tbl = 'users';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'user_settings';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'user_storage';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'user_announcements';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'user_login_history';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'user_appeals';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'site_settings';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'appeal_workflow_rules';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'appeal_templates';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'ban_email_patterns';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'banned_username_patterns';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'ban_evasion_logs';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl = 'performance_metrics';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl);
SET @s = IF(@exists > 0, CONCAT('ALTER TABLE ', @tbl, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'), 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

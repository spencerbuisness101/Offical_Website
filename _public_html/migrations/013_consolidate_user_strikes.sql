-- =============================================================================
-- Migration 013 — Consolidate user_strikes schema
-- =============================================================================
-- Problem: 2 incompatible CREATE TABLE definitions:
--   1. migrations/001_community_compliance_tables.sql — uses violation_type,
--      tier_applied (used by StrikeManager.php)
--   2. migrations/create_moderation_tables.sql — uses strike_number,
--      punishment_type, punishment_duration, expires_at (used by applyStrike())
-- Plus an inline CREATE TABLE in api/strike_user.php.
-- =============================================================================
-- Consolidated schema includes ALL columns from both definitions.
-- =============================================================================

CREATE TABLE IF NOT EXISTS user_strikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_id VARCHAR(10) NOT NULL COMMENT 'A1, B2, C1, etc.',
    strike_number TINYINT NOT NULL COMMENT '1, 2, or 3',
    violation_type VARCHAR(50) NOT NULL COMMENT 'Human-readable violation name',
    evidence TEXT NULL,
    applied_by INT NOT NULL DEFAULT 0 COMMENT 'Admin user ID (0 = SYSTEM)',
    tier_applied INT NOT NULL DEFAULT 1 COMMENT '1=Warning, 2=Removal, 3=Termination (legacy StrikeManager)',
    punishment_type ENUM('warning', 'suspension', 'lockdown', 'termination') DEFAULT 'warning',
    punishment_duration INT DEFAULT 0 COMMENT 'days, 0 for warning/permanent',
    expires_at TIMESTAMP NULL COMMENT '30 days from created_at',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_rule (rule_id),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable strike records for Three-Tier Punishment System';

-- =============================================================================
-- Add columns for existing installations that may have either schema version.
-- =============================================================================
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS strike_number TINYINT NOT NULL COMMENT '1, 2, or 3' AFTER rule_id;
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS violation_type VARCHAR(50) NOT NULL COMMENT 'Human-readable violation name' AFTER strike_number;
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS tier_applied INT NOT NULL DEFAULT 1 COMMENT 'Legacy tier' AFTER applied_by;
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS punishment_type ENUM('warning', 'suspension', 'lockdown', 'termination') DEFAULT 'warning' AFTER tier_applied;
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS punishment_duration INT DEFAULT 0 COMMENT 'days' AFTER punishment_type;
ALTER TABLE user_strikes ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL AFTER punishment_duration;

-- Add missing indexes
CREATE INDEX IF NOT EXISTS idx_user_active ON user_strikes(user_id, is_active);
CREATE INDEX IF NOT EXISTS idx_user_date ON user_strikes(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_expires ON user_strikes(expires_at);

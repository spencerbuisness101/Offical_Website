-- =============================================================================
-- Migration 012 — Consolidate admin_audit_log schema
-- =============================================================================
-- Problem: 4 incompatible definitions across migrations_v5.0.sql,
-- 003_phase5_appeals_system.sql, admin.php, and admin/includes/init_admin.php
-- with different column names (admin_user_id vs admin_id, action_type vs action,
-- target_user_id vs target_type+target_id).
-- =============================================================================
-- Uses the Phase 5 definition as the base (most feature-rich) while preserving
-- the admin_username column that both admin.php call sites write to.
-- =============================================================================

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_username VARCHAR(50) NOT NULL COMMENT 'Denormalized for display — avoids JOIN on every audit row',
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL COMMENT 'Type of target: appeal, user, strike, etc. (new code)',
    target_id INT NULL COMMENT 'Target record ID (new code — replaces target_user_id)',
    target_user_id INT NULL COMMENT 'Target user ID (legacy — used by 20+ admin/api files)',
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id),
    INDEX idx_target_user (target_user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Add columns for installations where the table already exists from an older
-- inline CREATE TABLE (admin.php, init_admin.php, or v5.0 migration).
-- =============================================================================
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS admin_username VARCHAR(50) NOT NULL COMMENT 'Denormalized for display' AFTER admin_id;
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS target_type VARCHAR(50) NULL COMMENT 'Type of target' AFTER action;
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS target_id INT NULL AFTER target_type;
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS target_user_id INT NULL AFTER target_id;
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS user_agent TEXT NULL AFTER ip_address;

-- Add missing indexes (IF NOT EXISTS is standard MariaDB/MySQL 8.0+)
CREATE INDEX IF NOT EXISTS idx_admin_id ON admin_audit_log(admin_id);
CREATE INDEX IF NOT EXISTS idx_target ON admin_audit_log(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_target_user ON admin_audit_log(target_user_id);

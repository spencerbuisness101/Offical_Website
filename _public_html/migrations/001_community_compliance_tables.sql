-- Phase 1: Community Platform Compliance - Database Migration
-- Creates all required tables for COPPA-compliant age verification and Community Accounts

-- =====================================================
-- TABLE 1: Community Account Sessions (Ephemeral, No PII)
-- =====================================================
CREATE TABLE IF NOT EXISTS community_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) UNIQUE NOT NULL COMMENT 'SHA-256 hash of cookie value',
    ip_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of IP + PEPPER',
    user_agent_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of user agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'Auto-purge after 30 days inactivity',
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ephemeral sessions for Community Accounts (COPPA compliant)';

-- =====================================================
-- TABLE 2: Age Verification Audit Log (COPPA Compliance Record)
-- =====================================================
CREATE TABLE IF NOT EXISTS age_verification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL for new Community Account signups',
    session_id VARCHAR(255) NULL COMMENT 'Community session token if applicable',
    declared_date DATE NOT NULL,
    calculated_age INT NOT NULL,
    routing_decision ENUM('community', 'paid_signup', 'blocked', 'reverification') NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_decision (routing_decision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all age verification decisions';

-- =====================================================
-- TABLE 3: VPC (Verifiable Parental Consent) Records
-- =====================================================
CREATE TABLE IF NOT EXISTS parental_consent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_user_id INT NOT NULL,
    parent_email VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(6) NULL COMMENT '6-digit numeric code',
    code_expires_at TIMESTAMP NULL,
    consent_token VARCHAR(64) NULL COMMENT 'Single-use consent link token',
    token_expires_at TIMESTAMP NULL,
    transaction_id VARCHAR(255) NULL,
    charge_amount DECIMAL(4,2) DEFAULT 1.00 COMMENT 'Non-refundable $1.00 verification charge',
    charge_processed_at TIMESTAMP NULL,
    consent_granted_at TIMESTAMP NULL,
    consent_revoked_at TIMESTAMP NULL,
    revocation_reason TEXT NULL,
    status ENUM('pending_code', 'pending_consent', 'verified', 'revoked') DEFAULT 'pending_code',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_child (child_user_id),
    INDEX idx_parent_email (parent_email),
    INDEX idx_code (verification_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parental consent records for under-13 Paid Account access';

-- =====================================================
-- TABLE 4: VPC Rate Limiting (IP-based temporary tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS vpc_rate_limit (
    ip_hash VARCHAR(64) PRIMARY KEY COMMENT 'SHA-256 hash of IP + PEPPER',
    attempt_count INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_attempt (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting for Community Account creation (3 per IP per 24h)';

-- =====================================================
-- TABLE 5: Lockdown Appeals
-- =====================================================
CREATE TABLE IF NOT EXISTS lockdown_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appeal_text TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'denied', 'approved') DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User appeals for lockdown mode (NSFW/Doxxing violations)';

-- =====================================================
-- TABLE 6: Strike History (Immutable)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_strikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_id VARCHAR(10) NOT NULL COMMENT 'A1, B1, etc.',
    violation_type VARCHAR(50) NOT NULL,
    evidence TEXT NULL,
    applied_by INT NOT NULL COMMENT 'Admin user ID',
    tier_applied INT NOT NULL COMMENT '1=Warning, 2=Removal, 3=Termination',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_rule (rule_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable strike records for Three-Tier Punishment System';

-- =====================================================
-- TABLE 7: Account Downgrades (Audit Trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS account_downgrades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_tier ENUM('paid') NOT NULL,
    to_tier ENUM('community') NOT NULL,
    initiated_by ENUM('user', 'parent_revocation') NOT NULL,
    reason TEXT NULL,
    reupgrade_eligible_at TIMESTAMP NULL COMMENT '30-day cooldown before re-upgrade allowed',
    subscription_canceled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_reupgrade (reupgrade_eligible_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for account downgrades (voluntary and parental)';

-- =====================================================
-- MODIFY EXISTING USERS TABLE
-- =====================================================
-- Add new columns for account tier, status, and compliance tracking
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_tier ENUM('community', 'paid') DEFAULT 'paid' COMMENT 'Account type: free Community or paid subscription',
    ADD COLUMN IF NOT EXISTS age_verified_at TIMESTAMP NULL COMMENT 'When user completed age verification',
    ADD COLUMN IF NOT EXISTS declared_birthdate DATE NULL COMMENT 'Self-declared birthdate from age gate',
    ADD COLUMN IF NOT EXISTS account_status ENUM('active', 'restricted', 'suspended', 'terminated') DEFAULT 'active' COMMENT 'Current account status',
    ADD COLUMN IF NOT EXISTS restriction_until TIMESTAMP NULL COMMENT 'For Time Removal punishments',
    ADD COLUMN IF NOT EXISTS email_hash VARCHAR(64) NULL COMMENT 'SHA-256 hash for ban list matching',
    ADD COLUMN IF NOT EXISTS reupgrade_blocked_until TIMESTAMP NULL COMMENT '30-day cooldown after downgrade',
    ADD INDEX idx_tier (account_tier),
    ADD INDEX idx_status (account_status);

-- Note: Remove current_strike_count column (migrate to dynamic query on user_strikes table)
-- This will be handled in a separate migration after strike system is fully implemented

-- =====================================================
-- STORED PROCEDURE: Clean Expired Community Sessions
-- =====================================================
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CleanExpiredCommunitySessions()
BEGIN
    DELETE FROM community_sessions 
    WHERE expires_at < NOW() 
       OR last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    DELETE FROM vpc_rate_limit 
    WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //

DELIMITER ;

-- =====================================================
-- EVENT: Auto-cleanup expired sessions (runs daily)
-- =====================================================
CREATE EVENT IF NOT EXISTS cleanup_community_sessions
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    CALL CleanExpiredCommunitySessions();

-- Enable event scheduler if not already enabled (removed: requires SUPER privilege on Hostinger)

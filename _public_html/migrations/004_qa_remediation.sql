-- Migration 004: QA Remediation
-- Fixes all schema gaps identified by the QA audit

-- =====================================================
-- SEC-H2: Track last seen IP hash in community_sessions for mismatch alerting
-- =====================================================
ALTER TABLE community_sessions
    ADD COLUMN IF NOT EXISTS last_ip_hash VARCHAR(64) NULL COMMENT 'SHA-256+pepper hash of most recently seen IP for mismatch detection';

-- =====================================================
-- SEC-H1: Ensure rate_limit_ip.ip_address column is large enough for SHA-256 hex
-- Rate limiter now stores hashed IPs (64 chars) not raw IPs (max 45 chars)
-- =====================================================
ALTER TABLE rate_limit_ip
    MODIFY COLUMN ip_address VARCHAR(64) NOT NULL COMMENT 'SHA-256+pepper hash of raw IP address';

-- =====================================================
-- FIX 2.1: Make declared_date nullable in age_verification_log
-- (DOB must be NULL for community routing — COPPA compliance)
-- =====================================================
ALTER TABLE age_verification_log
    MODIFY declared_date DATE NULL COMMENT 'NULL for community routing (COPPA: DOB is PII for children)';

-- =====================================================
-- FIX 2.5: Add missing columns to lockdown_appeals
-- (AppealWorkflow.php references these columns)
-- =====================================================
ALTER TABLE lockdown_appeals
    ADD COLUMN IF NOT EXISTS auto_processed    BOOLEAN DEFAULT FALSE COMMENT 'Set TRUE after workflow automation runs',
    ADD COLUMN IF NOT EXISTS flagged_for_review BOOLEAN DEFAULT FALSE COMMENT 'Set TRUE if workflow flags for manual admin review',
    ADD COLUMN IF NOT EXISTS flag_reason       TEXT    NULL COMMENT 'Reason the workflow flagged this appeal';

-- Also add admin_notes if not already present (referenced in admin/api/appeals.php)
ALTER TABLE lockdown_appeals
    ADD COLUMN IF NOT EXISTS admin_notes  TEXT NULL COMMENT 'Admin decision notes',
    ADD COLUMN IF NOT EXISTS reviewed_by  INT  NULL COMMENT 'Admin user ID who reviewed';

-- =====================================================
-- FIX 3.2: Self-deletion support columns on users table
-- =====================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deletion_scheduled_at TIMESTAMP NULL COMMENT '30-day grace period end; NULL = not scheduled',
    ADD COLUMN IF NOT EXISTS deletion_requested_at TIMESTAMP NULL COMMENT 'When user requested deletion';

-- =====================================================
-- FIX 3.0: Ensure account_status column exists with correct ENUM
-- (Both system_mailer and compliance system use this column)
-- =====================================================
ALTER TABLE users
    MODIFY COLUMN IF EXISTS account_status
        ENUM('active','restricted','suspended','terminated','pending_deletion')
        DEFAULT 'active'
        COMMENT 'Compliance system status; separate from legacy is_suspended/status columns';

-- Add IF NOT EXISTS fallback for platforms where MODIFY fails silently
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_status
        ENUM('active','restricted','suspended','terminated','pending_deletion')
        DEFAULT 'active'
        COMMENT 'Compliance system status';

-- =====================================================
-- INDEX ADDITIONS for performance
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_deletion_scheduled ON users(deletion_scheduled_at);
CREATE INDEX IF NOT EXISTS idx_age_routing ON age_verification_log(routing_decision, timestamp);

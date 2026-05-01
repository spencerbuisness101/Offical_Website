-- =============================================================================
-- Migration 007 — Consolidate login.php auto-migration into proper schema
-- =============================================================================
-- Replaces the in-line ALTER/CREATE block that ran on first login.
-- Idempotent: every statement is wrapped in IF NOT EXISTS / duplicate-safe.
-- =============================================================================

-- --- Users table columns (all optional, added if missing) ---
-- Note: MariaDB 10.0.2+ supports ALTER TABLE ... ADD COLUMN IF NOT EXISTS.
-- For strict MySQL, we use stored procedures; but in practice the runner
-- catches 'Duplicate column' errors so either approach works.

ALTER TABLE users ADD COLUMN IF NOT EXISTS email               VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS login_attempts      INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_failed_login   TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until        TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended        BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS device_fingerprint_hash VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS force_logout_at     TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS terms_accepted_at   TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS description         TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS about               TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_url VARCHAR(500) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS pfp_status          ENUM('pending','approved','rejected') DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname            VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active           BOOLEAN DEFAULT TRUE;

-- --- Rate limit table ---
CREATE TABLE IF NOT EXISTS rate_limit_ip (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ip_address     VARCHAR(45) NOT NULL,
    endpoint       VARCHAR(100) NOT NULL,
    request_count  INT DEFAULT 1,
    window_start   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint (ip_address, endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Smail messaging ---
CREATE TABLE IF NOT EXISTS smail_messages (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sender_id      INT NOT NULL,
    receiver_id    INT NOT NULL,
    title          VARCHAR(255) NOT NULL,
    message_body   TEXT NOT NULL,
    color_code     VARCHAR(7) DEFAULT '#3b82f6',
    urgency_level  ENUM('low','normal','high','urgent') DEFAULT 'normal',
    read_status    BOOLEAN DEFAULT FALSE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_receiver (receiver_id),
    INDEX idx_sender (sender_id),
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

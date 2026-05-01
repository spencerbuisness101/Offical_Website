-- =============================================================================
-- Migration 015 — Create user_sessions table (referenced by 20+ files, never created)
-- =============================================================================

CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    last_activity INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp',
    current_page VARCHAR(500) NULL,
    page_views INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add composite index for online-user queries (most-frequent query pattern)
CREATE INDEX IF NOT EXISTS idx_sessions_activity_user ON user_sessions(last_activity, user_id);

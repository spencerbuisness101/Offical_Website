-- =============================================================================
-- Migration 014 — Create page_views table (was inline in admin.php)
-- =============================================================================

CREATE TABLE IF NOT EXISTS page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(500) NULL,
    session_id VARCHAR(255) NULL COMMENT 'PHP session ID',
    user_id INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    load_time INT DEFAULT 0,
    INDEX idx_timestamp (timestamp),
    INDEX idx_page (page_url(191)),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

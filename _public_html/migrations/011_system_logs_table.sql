-- =============================================================================
-- Migration 011 — Create system_logs table for structured logging
-- =============================================================================
-- Supports the Logger class for database-backed log storage.
-- Logs with level >= WARNING are stored here for admin viewing.
-- =============================================================================

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(50) NOT NULL DEFAULT 'app',
    level VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    request_id VARCHAR(32),
    user_id INT UNSIGNED,
    ip VARCHAR(45),
    uri VARCHAR(500),
    method VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_level (level),
    INDEX idx_channel (channel),
    INDEX idx_user_id (user_id),
    INDEX idx_request_id (request_id),
    INDEX idx_created_level (created_at, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Migration 018 — Resize rate_limit_log.ip_address for SHA-256 hashed IPs
-- =============================================================================
-- IPs are now stored as SHA-256 hex (64 chars) instead of raw IPv4/IPv6 (45 chars).
-- PHP-side hashing was updated in includes/RateLimit.php.
-- =============================================================================

ALTER TABLE rate_limit_log
    MODIFY COLUMN ip_address VARCHAR(64) NULL
    COMMENT 'SHA-256+pepper hash of raw IP address';

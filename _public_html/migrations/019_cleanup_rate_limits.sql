-- =============================================================================
-- Migration 019 — Clean up stale rate limit data
-- =============================================================================
-- Removes old rate limit entries from both tables to prevent accumulated
-- records from causing false positives after limit adjustments.
-- =============================================================================

-- Remove entries older than 7 days from rate_limit_log
DELETE FROM rate_limit_log WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Remove expired windows from rate_limit_ip (windows older than 1 hour)
DELETE FROM rate_limit_ip WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);

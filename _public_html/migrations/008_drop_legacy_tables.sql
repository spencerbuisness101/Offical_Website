-- =============================================================================
-- Migration 008 — Drop legacy movie/video tables (content removed for legal safety)
-- =============================================================================
-- Replaces the one-time cleanup that ran from auth/login.php's movie drop block.
-- Safe / idempotent: DROP ... IF EXISTS.
-- =============================================================================

DROP TABLE IF EXISTS movies;
DROP TABLE IF EXISTS movie_analytics;
DROP TABLE IF EXISTS movie_progress;
DROP TABLE IF EXISTS movie_tracking;
DROP TABLE IF EXISTS user_movie_history;
DROP TABLE IF EXISTS video_analytics;

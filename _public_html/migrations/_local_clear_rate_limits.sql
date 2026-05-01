-- ============================================================================
-- Local Dev Only: Clear stuck rate-limit state
-- ----------------------------------------------------------------------------
-- Use this when a developer or QA account is stuck behind the
-- "Too many login attempts" message. Run via phpMyAdmin or:
--     mysql -u <user> -p <database> < migrations/_local_clear_rate_limits.sql
--
-- DO NOT run on production unless an incident has wedged real users out.
-- This file is INTENTIONALLY excluded from the migration runner because
-- it is operational, not schema.
--
-- v7.1 fix is in includes/RateLimit.php + auth/login.php:
--   - check() no longer auto-logs when blocked (no more sliding-window self-extension)
--   - login.php only ->log()s on actual credential failure
--   - per-account login_attempts counter is now actually persisted to the users table
-- ============================================================================

-- Wipe IP-based short-window burst counter
TRUNCATE TABLE rate_limit_ip;

-- Wipe long-window failure counter
TRUNCATE TABLE rate_limit_log;

-- Reset every account's per-user lockout counter
UPDATE users
   SET login_attempts    = 0,
       last_failed_login = NULL,
       locked_until      = NULL
 WHERE login_attempts > 0
    OR last_failed_login IS NOT NULL
    OR locked_until IS NOT NULL;

-- Optional: wipe the historical login_attempts ledger too (keeps audit trail
-- in production; fine to clear locally). Comment out if you want history.
-- TRUNCATE TABLE login_attempts;

SELECT 'rate_limit_ip rows after clear:'  AS step, COUNT(*) AS rows_remaining FROM rate_limit_ip
UNION ALL
SELECT 'rate_limit_log rows after clear:', COUNT(*) FROM rate_limit_log
UNION ALL
SELECT 'users with non-zero attempts:',    COUNT(*) FROM users WHERE login_attempts > 0 OR locked_until IS NOT NULL;

-- Payment System v2 Migration - Tiered Pricing, Subscriptions, Refunds, Security
-- Spencer's Website v6.0
-- Run these statements against the database. Each statement is independent (safe to re-run).

-- ============================================================
-- ALTER existing tables
-- ============================================================

-- users: Add suspension fields
ALTER TABLE users ADD COLUMN is_suspended BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN suspended_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN suspension_reason VARCHAR(255) NULL;

-- payment_sessions: Add plan type, idempotency, IP tracking, HMAC, refunded status
ALTER TABLE payment_sessions ADD COLUMN plan_type ENUM('monthly','lifetime') NOT NULL DEFAULT 'lifetime';
ALTER TABLE payment_sessions ADD COLUMN idempotency_key VARCHAR(64) NULL;
ALTER TABLE payment_sessions ADD COLUMN ip_address VARCHAR(45) NULL;
ALTER TABLE payment_sessions ADD COLUMN callback_hmac VARCHAR(128) NULL;
ALTER TABLE payment_sessions MODIFY COLUMN status ENUM('pending','paid','failed','expired','refunded') DEFAULT 'pending';

-- user_premium: Add subscription tracking
ALTER TABLE user_premium ADD COLUMN plan_type ENUM('monthly','lifetime') NOT NULL DEFAULT 'lifetime';
ALTER TABLE user_premium ADD COLUMN stripe_subscription_id VARCHAR(255) NULL;
ALTER TABLE user_premium ADD COLUMN paypal_subscription_id VARCHAR(255) NULL;
ALTER TABLE user_premium ADD COLUMN subscription_status ENUM('active','past_due','cancelled','expired') NULL;
ALTER TABLE user_premium ADD COLUMN current_period_end TIMESTAMP NULL;
ALTER TABLE user_premium ADD COLUMN provider ENUM('stripe','paypal','admin_manual') NULL;
ALTER TABLE user_premium ADD COLUMN last_payment_at TIMESTAMP NULL;

-- ============================================================
-- NEW tables
-- ============================================================

-- subscriptions: Authoritative subscription ledger
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_type ENUM('monthly','lifetime') NOT NULL,
    provider ENUM('stripe','paypal','admin_manual') NOT NULL,
    provider_subscription_id VARCHAR(255) NULL,
    provider_customer_id VARCHAR(255) NULL,
    status ENUM('active','past_due','cancelled','expired','suspended') NOT NULL DEFAULT 'active',
    amount_cents INT NOT NULL DEFAULT 200,
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    cancelled_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscriptions_user_id (user_id),
    INDEX idx_subscriptions_status (status),
    INDEX idx_subscriptions_provider_sub_id (provider_subscription_id),
    INDEX idx_subscriptions_period_end (current_period_end),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- webhook_events: Audit log for all webhooks
CREATE TABLE IF NOT EXISTS webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('stripe','paypal') NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    payload_hash VARCHAR(64) NULL,
    payment_token VARCHAR(64) NULL,
    user_id INT NULL,
    processing_status ENUM('received','processed','failed','skipped') DEFAULT 'received',
    error_message TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_webhook_provider_event (provider, event_id),
    INDEX idx_webhook_payment_token (payment_token),
    INDEX idx_webhook_user_id (user_id),
    INDEX idx_webhook_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- refund_requests: Refund tracking with user feedback
CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NULL,
    payment_session_id INT NULL,
    provider ENUM('stripe','paypal') NULL,
    provider_payment_id VARCHAR(255) NULL,
    provider_refund_id VARCHAR(255) NULL,
    amount_cents INT NULL,
    reason VARCHAR(100) NOT NULL,
    feedback TEXT NULL,
    status ENUM('pending','approved','processed','failed','denied') DEFAULT 'pending',
    requested_by ENUM('user','admin') DEFAULT 'user',
    processed_by INT NULL,
    processed_at TIMESTAMP NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_refund_user_id (user_id),
    INDEX idx_refund_status (status),
    INDEX idx_refund_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- rate_limit_ip: DB-backed IP rate limiting
CREATE TABLE IF NOT EXISTS rate_limit_ip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_ip_endpoint (ip_address, endpoint),
    INDEX idx_rate_limit_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- admin_payment_actions: Admin action audit trail
CREATE TABLE IF NOT EXISTS admin_payment_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    target_user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_payment_admin (admin_user_id),
    INDEX idx_admin_payment_target (target_user_id),
    INDEX idx_admin_payment_action (action_type),
    INDEX idx_admin_payment_created (created_at),
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- payment_nonces: Single-use nonce tokens for sensitive actions
CREATE TABLE IF NOT EXISTS payment_nonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nonce VARCHAR(64) NOT NULL UNIQUE,
    action VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_nonce_action (nonce, action),
    INDEX idx_nonce_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

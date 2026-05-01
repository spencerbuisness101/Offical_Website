-- Spencer's Website v8.0 Migrations
-- Payment system overhaul: Products table, donation support, Payment Intents
-- Run this in phpMyAdmin or via admin migration tool

-- ============================================
-- Products Table (new)
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    price_cents INT NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'usd',
    type ENUM('subscription', 'one-time', 'donation') NOT NULL DEFAULT 'subscription',
    plan_type ENUM('monthly', 'yearly', 'lifetime', 'donation') NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Seed Products
-- ============================================
INSERT IGNORE INTO products (name, slug, description, price_cents, currency, type, plan_type, is_active, sort_order) VALUES
    ('Monthly Membership', 'monthly', 'Full access to all member benefits. Billed monthly.', 200, 'usd', 'subscription', 'monthly', TRUE, 1),
    ('Yearly Membership', 'yearly', 'Full access to all member benefits. Billed yearly — save over 15%!', 2000, 'usd', 'subscription', 'yearly', TRUE, 2),
    ('Lifetime Membership', 'lifetime', 'One-time payment for permanent full access. Best value.', 10000, 'usd', 'one-time', 'lifetime', TRUE, 3),
    ('Donation', 'donation', 'Support the website with a custom donation ($1–$100).', 0, 'usd', 'donation', 'donation', TRUE, 4);

-- ============================================
-- Donations Table (new)
-- ============================================
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_name VARCHAR(100) NULL,
    amount_cents INT NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'usd',
    feedback TEXT NULL,
    payment_intent_id VARCHAR(255) NULL,
    payment_session_id INT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Alter payment_sessions for v8.0
-- ============================================
-- Add product_id reference
ALTER TABLE payment_sessions ADD COLUMN product_id INT NULL AFTER user_id;
ALTER TABLE payment_sessions ADD COLUMN is_donation BOOLEAN DEFAULT FALSE AFTER product_id;
ALTER TABLE payment_sessions ADD COLUMN donation_feedback TEXT NULL AFTER is_donation;
ALTER TABLE payment_sessions ADD COLUMN payment_intent_id VARCHAR(255) NULL AFTER provider_session_id;

-- Add yearly to plan_type enum if not already present
ALTER TABLE payment_sessions MODIFY COLUMN plan_type ENUM('monthly','yearly','lifetime','donation') NOT NULL DEFAULT 'lifetime';

-- Update user_premium plan_type to include yearly
ALTER TABLE user_premium MODIFY COLUMN plan_type ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'lifetime';

-- Update subscriptions plan_type to include yearly
ALTER TABLE subscriptions MODIFY COLUMN plan_type ENUM('monthly','yearly','lifetime') NOT NULL;

-- ============================================
-- Log migration
-- ============================================
INSERT INTO system_changelog (version, section, type, impact, details)
VALUES ('8.0', 'Payment System', 'feature', 'high', 'Added products table, donations table, Payment Intents support, updated pricing (yearly $30→$20). Stripe is sole payment processor.')
ON DUPLICATE KEY UPDATE details = VALUES(details), applied_at = NOW();

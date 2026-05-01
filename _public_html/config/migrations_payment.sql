-- Payment System Migration
-- Spencer's Website v6.0 - Secure Payment System
-- Run this migration to add payment support

CREATE TABLE IF NOT EXISTS payment_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    provider ENUM('stripe', 'paypal') NOT NULL,
    provider_session_id VARCHAR(255) NULL,
    amount_cents INT NOT NULL DEFAULT 200,
    status ENUM('pending', 'paid', 'failed', 'expired') DEFAULT 'pending',
    user_id INT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_token (token),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add premium tracking to users
CREATE TABLE IF NOT EXISTS user_premium (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    is_premium BOOLEAN DEFAULT TRUE,
    premium_since TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_session_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

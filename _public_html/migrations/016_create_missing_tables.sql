-- =============================================================================
-- Migration 016 — Create tables that were only created inline in PHP files
-- =============================================================================
-- Tables covered:
--   announcements   — ALTERed by add_performance_indexes.sql, never created
--   contributor_ideas  — inline in contributor_panel.php
--   designer_backgrounds — inline in designer_panel.php
--   ai_chat_history     — inline in ai_panel.php
--   yaps_chat_messages  — inline in yaps.php and post_yaps_message.php
-- =============================================================================

-- =====================================================
-- announcements
-- =====================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    message TEXT,
    created_by INT NULL,
    created_by_role VARCHAR(20) DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    priority INT DEFAULT 0,
    target_audience VARCHAR(50) DEFAULT 'all',
    expiry_date DATETIME NULL,
    color VARCHAR(7) DEFAULT '#7B6EF6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_target_audience (target_audience),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_created_at (created_at),
    INDEX idx_created_by_role (created_by_role),
    INDEX idx_active_priority (is_active, priority, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- contributor_ideas
-- =====================================================
CREATE TABLE IF NOT EXISTS contributor_ideas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('improvement', 'feature', 'bug_fix', 'design', 'content', 'other') DEFAULT 'improvement',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estimated_effort ENUM('quick', 'small', 'medium', 'large', 'xlarge') DEFAULT 'small',
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_user_status (user_id, status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- designer_backgrounds
-- =====================================================
CREATE TABLE IF NOT EXISTS designer_backgrounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    is_active TINYINT(1) DEFAULT 0,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_user_status (user_id, status),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ai_chat_history (legacy single-table system)
-- =====================================================
CREATE TABLE IF NOT EXISTS ai_chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    model_used VARCHAR(100) DEFAULT 'groq',
    persona VARCHAR(20) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_persona (persona)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- yaps_chat_messages
-- =====================================================
CREATE TABLE IF NOT EXISTS yaps_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

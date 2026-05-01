-- Phase 5 Appeals System & Notifications Tables

-- Ephemeral notifications (for SYSTEM Tray / Community Accounts)
-- These expire after 7 days and are delivered via WebSocket
CREATE TABLE IF NOT EXISTS ephemeral_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255),
    message TEXT,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin audit log: moved to migrations/012_consolidate_admin_audit_log.sql

-- Notification queue for batch processing
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255),
    message TEXT,
    channel VARCHAR(20) NOT NULL, -- 'email', 'sms', 'push', 'websocket'
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'sent', 'failed'
    attempts INT DEFAULT 0,
    scheduled_at DATETIME,
    sent_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System notifications (for paid accounts - persistent)
-- Already exists but ensure columns are correct
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'system',
    title VARCHAR(255),
    message TEXT,
    sender_id INT DEFAULT 0,
    is_system BOOLEAN DEFAULT TRUE,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for performance
ALTER TABLE lockdown_appeals 
ADD INDEX IF NOT EXISTS idx_status_created (status, created_at),
ADD INDEX IF NOT EXISTS idx_user_status (user_id, status);

-- WebSocket session tracking (for SYSTEM Tray delivery)
CREATE TABLE IF NOT EXISTS websocket_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255) NOT NULL,
    connection_time DATETIME NOT NULL,
    last_ping DATETIME,
    is_community BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    disconnected_at DATETIME,
    UNIQUE KEY unique_session (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_connection_time (connection_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automated workflow rules for appeals
CREATE TABLE IF NOT EXISTS appeal_workflow_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rule_type VARCHAR(50) NOT NULL, -- 'auto_approve', 'auto_deny', 'flag'
    conditions JSON NOT NULL, -- JSON object with conditions
    action VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_priority (is_active, priority),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default workflow rules
INSERT INTO appeal_workflow_rules (name, rule_type, conditions, action, priority) VALUES
('Auto-deny empty appeals', 'auto_deny', '{"min_length": 50}', 'deny', 1),
('Flag repeat offenders', 'flag', '{"previous_appeals_denied": 2}', 'manual_review', 5),
('Auto-approve first-time minor', 'auto_approve', '{"first_appeal": true, "rule": "B1"}', 'approve', 10);

-- Appeal templates for admin responses
CREATE TABLE IF NOT EXISTS appeal_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50), -- 'approval', 'denial', 'request_info'
    subject VARCHAR(255),
    body TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default templates
INSERT INTO appeal_templates (name, category, subject, body) VALUES
('Standard Approval', 'approval', 'Appeal Approved', 'Your appeal has been reviewed and approved. Your account lockdown has been removed and you may now use all platform features.'),
('Standard Denial', 'denial', 'Appeal Denied', 'After reviewing your appeal, we have determined that the lockdown should remain in place. You may submit another appeal after 7 days.'),
('Insufficient Information', 'denial', 'Appeal Denied - More Info Needed', 'Your appeal did not provide sufficient information about the incident. Please submit a new appeal with more details about what happened and how you will prevent future violations.'),
('Repeat Violation', 'denial', 'Appeal Denied - Pattern of Behavior', 'Your account shows a pattern of violations. Given this history, we cannot approve your appeal at this time.');

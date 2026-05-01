-- Phase 1.3: Beta-Tester Removal Migration
-- Migrates all beta-tester users to 'user' role and drops beta-related tables

-- Start transaction for safety
START TRANSACTION;

-- Create system_changelog table if it doesn't exist
CREATE TABLE IF NOT EXISTS system_changelog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL DEFAULT '7.0',
    section VARCHAR(50) NOT NULL,
    type ENUM('feature', 'fix', 'removal', 'security', 'performance', 'other') DEFAULT 'update',
    impact ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    details TEXT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT NULL,
    INDEX idx_section (section),
    INDEX idx_version (version),
    INDEX idx_applied_at (applied_at),
    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Migrate all beta-tester users to 'user' role
UPDATE users SET role = 'user' WHERE role = 'beta-tester';

-- 2. Drop beta-related tables
DROP TABLE IF EXISTS beta_tester_slots;
DROP TABLE IF EXISTS beta_submissions;

-- Commit the transaction
COMMIT;

-- Log the migration
INSERT INTO system_changelog (version, section, details, applied_at) 
VALUES ('7.0', 'Phase 1.3', 'Removed beta-tester role and migrated all beta-tester users to user role. Dropped beta_tester_slots and beta_submissions tables.', NOW())
ON DUPLICATE KEY UPDATE details = VALUES(details), applied_at = NOW();

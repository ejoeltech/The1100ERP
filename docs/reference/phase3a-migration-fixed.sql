-- Phase 3A Database Migration - CORRECTED
-- User Management & Advanced Permissions
-- Fixed to work with the actual database structure

USE 1100erp;

-- ============================================
-- 1. ENHANCE USERS TABLE
-- ============================================

-- Add user roles
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS role ENUM('admin', 'manager', 'sales_rep') NOT NULL DEFAULT 'sales_rep' AFTER full_name;

-- Add phone and active status
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER phone,
    ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER is_active;

-- Update existing admin user to have admin role
UPDATE users SET role = 'admin' WHERE username = 'admin' LIMIT 1;

-- ============================================
-- 2. ENHANCE DOCUMENTS TABLE
-- ============================================

-- Add edit tracking to documents table
ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS last_edited_by INT UNSIGNED AFTER created_by,
    ADD COLUMN IF NOT EXISTS last_edited_at TIMESTAMP NULL AFTER updated_at;

-- Add foreign key for edit tracking (drop first if exists)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = '1100erp' 
                  AND TABLE_NAME = 'documents' 
                  AND CONSTRAINT_NAME = 'fk_documents_editor');

SET @sql = IF(@fk_exists > 0,
    'SELECT "FK already exists" AS status',
    'ALTER TABLE documents ADD CONSTRAINT fk_documents_editor FOREIGN KEY (last_edited_by) REFERENCES users(id) ON DELETE SET NULL'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. CREATE EMAIL LOG TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS email_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('quote', 'invoice', 'receipt') NOT NULL,
    document_id INT UNSIGNED NOT NULL,
    sent_by INT UNSIGNED,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_document (document_type, document_id),
    INDEX idx_sent_by (sent_by),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Email send history for documents';

-- ============================================
-- 4. CREATE AUDIT LOG TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT UNSIGNED,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='System audit trail';

-- ============================================
-- 5. CREATE SETTINGS TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='System configuration settings';

-- ============================================
-- 6. TEST USERS (DISABLED BY DEFAULT)
-- ============================================
-- Former demo accounts (manager1 / salesrep1 / salesrep2) removed:
-- they used a publicly-known password hash and must never exist in production.
-- To recreate for local testing only, insert users via the app's user management.

-- ============================================
-- 7. INITIAL AUDIT LOG ENTRIES
-- ============================================

INSERT IGNORE INTO audit_log (user_id, action, resource_type, resource_id, ip_address, user_agent) VALUES
(1, 'system_migration', 'database', NULL, '127.0.0.1', 'Phase 3A Migration Script'),
(1, 'user_created', 'user', 2, '127.0.0.1', 'Migration Script'),
(1, 'user_created', 'user', 3, '127.0.0.1', 'Migration Script'),
(1, 'user_created', 'user', 4, '127.0.0.1', 'Migration Script');

-- ============================================
-- MIGRATION COMPLETE
-- ============================================

SELECT 'Phase 3A Migration Complete!' AS status,
       COUNT(*) AS total_users 
FROM users;

SELECT 'Users by Role' AS info,
       role,
       COUNT(*) AS count
FROM users
GROUP BY role;

SELECT 'New Tables Created' AS info,
       COUNT(*) AS email_logs
FROM email_log;

SELECT 'Audit Trail' AS info,
       COUNT(*) AS audit_entries
FROM audit_log;

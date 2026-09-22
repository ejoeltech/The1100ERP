-- Phase 3A Database Migration
-- User Management & Advanced Permissions
-- Run this in phpMyAdmin after backup

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

-- Add edit tracking
ALTER TABLE quotes
    ADD COLUMN IF NOT EXISTS last_edited_by INT UNSIGNED AFTER created_by,
    ADD COLUMN IF NOT EXISTS last_edited_at TIMESTAMP NULL AFTER updated_at;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS last_edited_by INT UNSIGNED AFTER created_by,
    ADD COLUMN IF NOT EXISTS last_edited_at TIMESTAMP NULL AFTER updated_at;

ALTER TABLE receipts
    ADD COLUMN IF NOT EXISTS last_edited_by INT UNSIGNED AFTER created_by,
    ADD COLUMN IF NOT EXISTS last_edited_at TIMESTAMP NULL AFTER updated_at;

-- Add foreign keys for edit tracking
ALTER TABLE quotes
    ADD CONSTRAINT fk_quotes_editor FOREIGN KEY (last_edited_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE invoices
    ADD CONSTRAINT fk_invoices_editor FOREIGN KEY (last_edited_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE receipts
    ADD CONSTRAINT fk_receipts_editor FOREIGN KEY (last_edited_by) REFERENCES users(id) ON DELETE SET NULL;

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
-- 4. TEST USERS (DISABLED BY DEFAULT)
-- ============================================
-- Former demo accounts (manager1 / salesrep1 / salesrep2) removed:
-- they used a publicly-known password hash and must never exist in production.
-- To recreate for local testing only, insert users via the app's user management.

-- ============================================
-- 5. SAMPLE AUDIT LOG ENTRIES
-- ============================================

-- Note: audit_log table already exists from security phase
-- Add sample entries for testing
INSERT IGNORE INTO audit_log (user_id, action, resource_type, resource_id, ip_address, user_agent) VALUES
(1, 'system_migration', 'database', NULL, '127.0.0.1', 'Migration Script'),
(1, 'user_created', 'user', 2, '127.0.0.1', 'Migration Script'),
(1, 'user_created', 'user', 3, '127.0.0.1', 'Migration Script');

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

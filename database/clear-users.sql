-- Clear User Information from 1100-ERP Database
-- This script removes all user data while preserving the database structure
-- Created: 2026-01-16
-- Updated: 2026-01-16 - Enhanced to fully recreate users table

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop and recreate users table for complete cleanup
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'sales', 'viewer') DEFAULT 'sales',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clear audit log (contains user activity)
TRUNCATE TABLE audit_log;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Display confirmation
SELECT 'User information cleared successfully' AS Status;

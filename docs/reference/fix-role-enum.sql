-- Fix role ENUM mismatch
-- Align users table role enum with application permissions & user management:
-- Adds 'sales_rep' and 'accountant' while retaining 'admin', 'manager', 'viewer'
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'sales_rep', 'accountant', 'viewer') DEFAULT 'sales_rep';

-- Update any legacy 'sales' role records if present
UPDATE users SET role = 'sales_rep' WHERE role = 'sales' OR role = '' OR role IS NULL;

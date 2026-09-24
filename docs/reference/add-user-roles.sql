-- Add user_role column to users table
-- Roles: 'user', 'admin', 'super_admin'

ALTER TABLE users ADD COLUMN user_role VARCHAR(20) DEFAULT 'user' AFTER password;

-- Update existing admin user to super_admin
UPDATE users SET user_role = 'super_admin' WHERE username = 'admin';

-- Add index for performance
CREATE INDEX idx_user_role ON users(user_role);

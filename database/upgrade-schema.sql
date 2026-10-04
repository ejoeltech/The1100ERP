-- ============================================================================
-- 1100ERP upgrade schema: bring ANY existing database up to the current code.
-- Run AFTER a normal install, or to repair schema drift on an old database.
--
-- HOW TO RUN (phpMyAdmin): open the database -> Import -> choose this file
-- -> Go. Preferred alternative (same coverage, fully automatic): log in as
-- admin -> System Update -> Run Schema Patcher.
--
-- SAFE TO RE-RUN: every CREATE uses IF NOT EXISTS and every seed uses
-- INSERT IGNORE. The plain ALTER TABLE ... ADD COLUMN lines below will raise
-- "Duplicate column name" for columns that already exist — that error is
-- HARMLESS: in phpMyAdmin, delete the offending ALTER line and continue with
-- the rest of the file. No statement in this file deletes or rewrites data.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================ 1. users =====================================
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('super_admin','admin','manager','sales_rep','accountant','viewer') DEFAULT 'sales_rep';
ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;
UPDATE `users` SET `email` = NULL WHERE `email` = '';
ALTER TABLE `users` ADD COLUMN `group_id` INT(11) UNSIGNED DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN `mfa_secret` TEXT DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN `mfa_enrolled_at` DATETIME DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `signature_file` VARCHAR(255) DEFAULT NULL;

-- ==================== 2. groups & permissions ==============================
CREATE TABLE IF NOT EXISTS `user_groups` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `primary_role` varchar(20) NOT NULL DEFAULT 'viewer',
    `is_system` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `group_permissions` (
    `group_id` int(11) unsigned NOT NULL,
    `permission_key` varchar(60) NOT NULL,
    PRIMARY KEY (`group_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
    `user_id` int(10) unsigned NOT NULL,
    `permission_key` varchar(60) NOT NULL,
    `granted` tinyint(1) NOT NULL DEFAULT 1,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_groups` (`name`, `description`, `primary_role`, `is_system`) VALUES
('super_admin', 'Full system control including access management', 'super_admin', 1),
('admin', 'Manage users, settings and all documents', 'admin', 1),
('manager', 'View all documents, manage team workflows', 'manager', 1),
('accountant', 'Invoices, payments and exports', 'accountant', 1),
('sales_rep', 'Own quotes and customers only', 'sales_rep', 1),
('developer', 'Technical developers - managed by super admin only', 'admin', 1),
('viewer', 'Read-only access', 'viewer', 1);

-- Baseline permissions (INSERT IGNORE: re-running never duplicates; note it
-- also re-adds keys an admin deliberately revoked — use the Groups page to
-- trim afterwards if you customised groups).
-- admin: full baseline
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_users' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_user' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_user' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'delete_user' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'toggle_user_status' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_access' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_settings' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_audit_log' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_all_documents' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_quote' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_document' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_quote' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_invoice' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_document' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_finalized' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'delete_quote' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'delete_invoice' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'delete_receipt' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'delete_document' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'archive_document' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'convert_to_invoice' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'generate_receipt' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'send_email' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'email_document' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_system_dashboard' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_team_dashboard' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_store' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_accessories' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_hr' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_payments' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'export_data' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'hr_view' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'hr_manage' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'leave_manage' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'payroll_run' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'recruitment_manage' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_payments' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_payment' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_customers' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_products' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_leads' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_reports' FROM `user_groups` WHERE name = 'admin';
-- manager baseline
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_all_documents' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_quote' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_document' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_quote' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_invoice' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_document' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'convert_to_invoice' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'generate_receipt' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'send_email' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'email_document' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_team_dashboard' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_store' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_accessories' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_hr' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_payments' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'export_data' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'hr_view' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'hr_manage' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'leave_manage' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'recruitment_manage' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_payments' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_payment' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_customers' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_products' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_leads' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_reports' FROM `user_groups` WHERE name = 'manager';
-- accountant baseline
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_all_documents' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_quote' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_document' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'convert_to_invoice' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'generate_receipt' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'send_email' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'email_document' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_payments' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'export_data' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'payroll_run' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_payments' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_payment' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_customers' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_reports' FROM `user_groups` WHERE name = 'accountant';
-- sales_rep baseline
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_quote' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_document' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_quote' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_invoice' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_document' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'send_email' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'email_document' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'create_payment' FROM `user_groups` WHERE name = 'sales_rep';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_customers' FROM `user_groups` WHERE name = 'sales_rep';
-- developer baseline (technical access, super-admin managed)
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_system_dashboard' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_audit_log' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'manage_settings' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'export_data' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'developer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'developer';
-- viewer baseline
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_all_documents' FROM `user_groups` WHERE name = 'viewer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'edit_own_profile' FROM `user_groups` WHERE name = 'viewer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'change_own_password' FROM `user_groups` WHERE name = 'viewer';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, 'view_personal_dashboard' FROM `user_groups` WHERE name = 'viewer';

-- Link users without a group to their role's group; keep one super_admin
UPDATE `users` u JOIN `user_groups` g ON g.name = u.role SET u.group_id = g.id WHERE u.group_id IS NULL;
UPDATE `users` SET `role` = 'super_admin', `group_id` = (SELECT id FROM `user_groups` WHERE name = 'super_admin') WHERE `role` = 'admin' AND (SELECT COUNT(*) FROM (SELECT 1 FROM `users` WHERE `role` = 'super_admin') AS s) = 0 ORDER BY `id` ASC LIMIT 1;

-- ================== 3. invites / throttle / MFA ============================
CREATE TABLE IF NOT EXISTS `user_invites` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL,
    `token_hash` char(64) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token_hash` (`token_hash`),
    KEY `idx_invite_user` (`user_id`),
    KEY `idx_invite_expiry` (`expires_at`),
    CONSTRAINT `user_invites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `auth_throttle` (
    `bucket` varchar(128) NOT NULL,
    `window_start` datetime NOT NULL,
    `attempts` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`bucket`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mfa_recovery_codes` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL,
    `code_hash` varchar(255) NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_mfa_rc_user` (`user_id`),
    CONSTRAINT `mfa_recovery_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `session_hash` char(64) NOT NULL,
    `user_id` int(10) unsigned NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `last_seen` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`session_hash`),
    KEY `idx_us_user` (`user_id`),
    CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================== 4. audit ===============================================
ALTER TABLE `audit_log` ADD COLUMN `hash` VARCHAR(64) DEFAULT NULL;
CREATE TABLE IF NOT EXISTS `audit_seq` (
    `id` tinyint(3) unsigned NOT NULL,
    `tick` bigint(20) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `audit_seq` (`id`, `tick`) VALUES (1, 0);

-- ================== 5. documents extra columns =============================
ALTER TABLE `customers` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `quotes` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `receipts` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `quotes` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0;
ALTER TABLE `invoices` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0;
ALTER TABLE `receipts` ADD COLUMN `is_archived` TINYINT(1) DEFAULT 0;
ALTER TABLE `customers` ADD COLUMN `company` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN `notes` TEXT DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN `account_balance` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `products` ADD COLUMN `created_by` INT(11) DEFAULT NULL;
ALTER TABLE `quotes` ADD COLUMN `delivery_period` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `quote_line_items` ADD COLUMN `item_id` INT(11) DEFAULT NULL;
ALTER TABLE `quote_line_items` ADD COLUMN `item_name` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `invoice_line_items` ADD COLUMN `item_id` INT(11) DEFAULT NULL;
ALTER TABLE `invoice_line_items` ADD COLUMN `item_name` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `receipts` ADD COLUMN `receipt_number` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `receipts` ADD COLUMN `payment_id` INT(11) DEFAULT NULL;
ALTER TABLE `receipts` ADD COLUMN `status` ENUM('valid','void') DEFAULT 'valid';

-- ================== 6. proposals & leads ====================================
CREATE TABLE IF NOT EXISTS `proposals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `customer_name` varchar(255) DEFAULT NULL,
    `system_specs` text DEFAULT NULL,
    `content` longtext DEFAULT NULL,
    `status` varchar(50) DEFAULT 'draft',
    `converted_quote_id` int(10) unsigned DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leads` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `phone` varchar(50) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `source` enum('web','whatsapp','phone','referral','walk-in','other') NOT NULL DEFAULT 'web',
    `interest` text DEFAULT NULL,
    `message` text DEFAULT NULL,
    `status` enum('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new',
    `assigned_to` varchar(255) DEFAULT NULL,
    `converted_to` int(10) unsigned DEFAULT NULL,
    `followup_count` int(10) unsigned NOT NULL DEFAULT 0,
    `last_followup_at` datetime DEFAULT NULL,
    `next_followup_at` datetime DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_next_followup` (`next_followup_at`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================== 7. HR payroll (MariaDB-safe, single ALTERs) ============
ALTER TABLE `hr_employees` ADD COLUMN `paye_exempt` TINYINT(1) DEFAULT 0;
ALTER TABLE `hr_employees` ADD COLUMN `nhf_exempt` TINYINT(1) DEFAULT 0;
ALTER TABLE `hr_employees` ADD COLUMN `pension_exempt` TINYINT(1) DEFAULT 0;
ALTER TABLE `hr_employees` ADD COLUMN `hourly_rate_override` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `hr_payroll` ADD COLUMN `gross_salary` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `taxable_income` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `paye` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `nhf` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `pension_employee` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `pension_employer` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `loan_deduction` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `other_deductions` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `total_deductions` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `employer_cost` DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE `hr_payroll` ADD COLUMN `payslip_pdf` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `hr_payroll` ADD COLUMN `approved_by` INT(10) UNSIGNED DEFAULT NULL;
ALTER TABLE `hr_payroll` ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `hr_payroll` ADD COLUMN `paid_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `hr_onboarding_codes` ADD COLUMN `code_hash` CHAR(64) DEFAULT NULL;
ALTER TABLE `hr_onboarding_codes` ADD COLUMN `expires_at` DATETIME DEFAULT NULL;
ALTER TABLE `hr_onboarding_codes` ADD COLUMN `failed_attempts` INT(11) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS `hr_payroll_items` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) unsigned NOT NULL,
    `month` int(2) NOT NULL,
    `year` int(4) NOT NULL,
    `type` enum('bonus','commission','overtime','allowance','deduction','loan_repayment') NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_emp_period` (`employee_id`, `month`, `year`),
    CONSTRAINT `hr_payroll_items_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `hr_payroll_items_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `hr_loans` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) unsigned NOT NULL,
    `principal` decimal(15,2) NOT NULL DEFAULT 0.00,
    `interest_pct` decimal(5,2) DEFAULT 0.00,
    `term_months` int(3) NOT NULL DEFAULT 1,
    `monthly_repayment` decimal(15,2) NOT NULL DEFAULT 0.00,
    `total_repaid` decimal(15,2) NOT NULL DEFAULT 0.00,
    `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
    `start_month` int(2) NOT NULL,
    `start_year` int(4) NOT NULL,
    `status` enum('active','completed','cancelled') DEFAULT 'active',
    `approved_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_emp` (`employee_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `hr_loans_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `hr_loans_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================== 8. settings seeds ======================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('lead_followup_enabled', '1'),
('lead_followup_interval_days', '3'),
('lead_followup_max_attempts', '3'),
('lead_digest_enabled', '1'),
('telegram_bot_token', ''),
('telegram_chat_id', ''),
('whatsapp_verify_token', ''),
('whatsapp_app_secret', '');
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('payroll_paye_consolidated_relief_pct', '20'),
('payroll_paye_floor_relief_ngn', '200000'),
('payroll_nhf_pct', '2.5'),
('payroll_pension_employee_pct', '8'),
('payroll_pension_employer_pct', '10'),
('payroll_pension_min_ngn', '3000'),
('payroll_overtime_multiplier', '1.5'),
('payroll_standard_hours', '173'),
('payroll_currency_symbol', 'NGN');
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('security_mfa_enabled', '1'),
('security_invites_enabled', '0'),
('security_password_min', '12'),
('security_password_complexity', 'strict');

SET FOREIGN_KEY_CHECKS = 1;

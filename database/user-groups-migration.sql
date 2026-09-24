-- User Groups & Permissions migration
-- Standard groups, group permissions, per-user overrides. Adds super_admin role.

ALTER TABLE `users` MODIFY COLUMN `role` ENUM('super_admin','admin','manager','sales_rep','accountant','viewer') DEFAULT 'sales_rep';

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `group_id` INT(11) UNSIGNED DEFAULT NULL AFTER `role`;

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
  PRIMARY KEY (`group_id`, `permission_key`),
  CONSTRAINT `group_permissions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
  `user_id` int(10) unsigned NOT NULL,
  `permission_key` varchar(60) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`, `permission_key`),
  CONSTRAINT `user_permission_overrides_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard groups (system-locked names, permissions below)
INSERT IGNORE INTO `user_groups` (`name`, `description`, `primary_role`, `is_system`) VALUES
('super_admin', 'Full system control including access management', 'super_admin', 1),
('admin', 'Manage users, settings and all documents', 'admin', 1),
('manager', 'View all documents, manage team workflows', 'manager', 1),
('accountant', 'Invoices, payments and exports', 'accountant', 1),
('sales_rep', 'Own quotes and customers only', 'sales_rep', 1),
('viewer', 'Read-only access', 'viewer', 1);

-- Group permission seeds (mirrors PERMISSION_CATALOG in includes/permissions.php)
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT g.id, p.perm FROM `user_groups` g CROSS JOIN (
  SELECT 'manage_users' AS perm UNION ALL SELECT 'create_user' UNION ALL SELECT 'edit_user'
  UNION ALL SELECT 'delete_user' UNION ALL SELECT 'toggle_user_status' UNION ALL SELECT 'manage_access'
  UNION ALL SELECT 'manage_settings' UNION ALL SELECT 'view_audit_log'
  UNION ALL SELECT 'view_all_documents' UNION ALL SELECT 'create_quote' UNION ALL SELECT 'create_document'
  UNION ALL SELECT 'edit_quote' UNION ALL SELECT 'edit_invoice' UNION ALL SELECT 'edit_document'
  UNION ALL SELECT 'edit_finalized' UNION ALL SELECT 'delete_quote' UNION ALL SELECT 'delete_invoice'
  UNION ALL SELECT 'delete_receipt' UNION ALL SELECT 'delete_document' UNION ALL SELECT 'archive_document'
  UNION ALL SELECT 'convert_to_invoice' UNION ALL SELECT 'generate_receipt'
  UNION ALL SELECT 'send_email' UNION ALL SELECT 'email_document'
  UNION ALL SELECT 'edit_own_profile' UNION ALL SELECT 'change_own_password'
  UNION ALL SELECT 'view_system_dashboard' UNION ALL SELECT 'view_team_dashboard' UNION ALL SELECT 'view_personal_dashboard'
  UNION ALL SELECT 'manage_store' UNION ALL SELECT 'manage_accessories' UNION ALL SELECT 'manage_hr'
  UNION ALL SELECT 'manage_payments' UNION ALL SELECT 'export_data'
) p WHERE g.name = 'admin';

INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT g.id, p.perm FROM `user_groups` g CROSS JOIN (
  SELECT 'view_all_documents' AS perm UNION ALL SELECT 'create_quote' UNION ALL SELECT 'create_document'
  UNION ALL SELECT 'edit_quote' UNION ALL SELECT 'edit_invoice' UNION ALL SELECT 'edit_document'
  UNION ALL SELECT 'convert_to_invoice' UNION ALL SELECT 'generate_receipt'
  UNION ALL SELECT 'send_email' UNION ALL SELECT 'email_document'
  UNION ALL SELECT 'edit_own_profile' UNION ALL SELECT 'change_own_password'
  UNION ALL SELECT 'view_team_dashboard' UNION ALL SELECT 'view_personal_dashboard'
  UNION ALL SELECT 'manage_store' UNION ALL SELECT 'manage_accessories' UNION ALL SELECT 'manage_hr'
  UNION ALL SELECT 'manage_payments' UNION ALL SELECT 'export_data'
) p WHERE g.name = 'manager';

INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT g.id, p.perm FROM `user_groups` g CROSS JOIN (
  SELECT 'view_all_documents' AS perm UNION ALL SELECT 'create_quote' UNION ALL SELECT 'create_document'
  UNION ALL SELECT 'convert_to_invoice' UNION ALL SELECT 'generate_receipt'
  UNION ALL SELECT 'send_email' UNION ALL SELECT 'email_document'
  UNION ALL SELECT 'edit_own_profile' UNION ALL SELECT 'change_own_password'
  UNION ALL SELECT 'view_personal_dashboard'
  UNION ALL SELECT 'manage_payments' UNION ALL SELECT 'export_data'
) p WHERE g.name = 'accountant';

INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT g.id, p.perm FROM `user_groups` g CROSS JOIN (
  SELECT 'create_quote' AS perm UNION ALL SELECT 'create_document'
  UNION ALL SELECT 'edit_quote' UNION ALL SELECT 'edit_invoice' UNION ALL SELECT 'edit_document'
  UNION ALL SELECT 'send_email' UNION ALL SELECT 'email_document'
  UNION ALL SELECT 'edit_own_profile' UNION ALL SELECT 'change_own_password'
  UNION ALL SELECT 'view_personal_dashboard'
) p WHERE g.name = 'sales_rep';

INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT g.id, p.perm FROM `user_groups` g CROSS JOIN (
  SELECT 'view_all_documents' AS perm UNION ALL SELECT 'edit_own_profile'
  UNION ALL SELECT 'change_own_password' UNION ALL SELECT 'view_personal_dashboard'
) p WHERE g.name = 'viewer';

-- super_admin needs no rows (bypass in code). Link existing users to matching group,
-- then promote the earliest admin to super_admin so the system always has one.
UPDATE `users` u JOIN `user_groups` g ON g.name = u.role SET u.group_id = g.id WHERE u.group_id IS NULL;
UPDATE `users` SET `role` = 'super_admin', `group_id` = (SELECT id FROM `user_groups` WHERE name = 'super_admin')
WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1;

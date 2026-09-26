-- WP3 permissions seed (APPLY MANUALLY, e.g. phpMyAdmin).
-- Covers HR suite + payment UI keys (view_payments/create_payment, which the
-- payments pages require but no seed ever granted).
-- Additive only (INSERT IGNORE). The schema patcher applies the same rows.
-- Rollback: DELETE FROM `group_permissions` WHERE `permission_key` IN (...).

-- admin: full HR suite
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'hr_view' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'hr_manage' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'leave_manage' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'payroll_run' FROM `user_groups` WHERE name = 'admin';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'recruitment_manage' FROM `user_groups` WHERE name = 'admin';

-- manager: directory, team management, leave approvals, payroll viewing, recruitment
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'hr_view' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'hr_manage' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'leave_manage' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'manager';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'recruitment_manage' FROM `user_groups` WHERE name = 'manager';

-- accountant: payroll viewing + runs (no hiring/firing, no leave approvals)
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'payroll_view' FROM `user_groups` WHERE name = 'accountant';
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'payroll_run' FROM `user_groups` WHERE name = 'accountant';

-- sales_rep / viewer: no HR permissions (self-service leave + own payslip
-- are ownership-scoped in code, not permission-gated).

-- payments UI keys (pages/payments/* require these; previously ungranted,
-- so the pages denied everyone but super_admin).
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'view_payments' FROM `user_groups` WHERE name IN ('admin', 'manager', 'accountant');
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'create_payment' FROM `user_groups` WHERE name IN ('admin', 'manager', 'accountant', 'sales_rep');

-- Resurrected dead permissions (used across the app but never defined or
-- seeded, so they denied everyone but super_admin).
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'manage_customers' FROM `user_groups` WHERE name IN ('admin', 'manager', 'accountant', 'sales_rep');
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'manage_products' FROM `user_groups` WHERE name IN ('admin', 'manager');
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'manage_leads' FROM `user_groups` WHERE name IN ('admin', 'manager');
INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`)
SELECT id, 'view_reports' FROM `user_groups` WHERE name IN ('admin', 'manager', 'accountant');

-- ================= ROLLBACK (run manually) =================
-- DELETE FROM `group_permissions` WHERE `permission_key` IN
-- ('hr_view','hr_manage','leave_manage','payroll_view','payroll_run','recruitment_manage');

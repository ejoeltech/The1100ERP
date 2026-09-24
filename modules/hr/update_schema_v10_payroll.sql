-- HR Module Schema Update v10 — Nigeria-Compliant Payroll
-- Generated: 2026-07-26
-- Extends the existing modules/hr payroll WITHOUT changing existing columns
-- (backward compatible). All rates are config-driven via the settings table
-- so finance can tune them without touching code.
--
-- Apply AFTER hr_schema.sql and update_schema_v2..v9 migrations.
-- Safe to re-run (IF NOT EXISTS / additive ALTERs).

SET FOREIGN_KEY_CHECKS=0;

-- ============================================================
-- 1. Payroll configuration (config-driven statutory rates)
-- ============================================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `category`, `description`) VALUES
('payroll_paye_consolidated_relief_pct', '20', 'payroll', 'Consolidated relief allowance % of gross income (Nigeria PAYE uses 20% or ₦200,000, whichever is higher, plus ₦1 statutory)'),
('payroll_paye_floor_relief_ngn', '200000', 'payroll', 'Minimum consolidated relief floor (₦) per annum'),
('payroll_nhf_pct', '2.5', 'payroll', 'NHF contribution % of gross (employee)'),
('payroll_pension_employee_pct', '8', 'payroll', 'Pension contribution % of gross (employee)'),
('payroll_pension_employer_pct', '10', 'payroll', 'Pension contribution % of gross (employer)'),
('payroll_pension_min_ngn', '3000', 'payroll', 'Minimum monthly employer pension (₦)'),
('payroll_overtime_multiplier', '1.5', 'payroll', 'Overtime rate multiplier of hourly pay'),
('payroll_standard_hours', '173', 'payroll', 'Standard monthly working hours (≈ 40h/week * 52 / 12) used to derive hourly rate'),
('payroll_currency_symbol', '₦', 'payroll', 'Currency symbol for payslips');

-- ============================================================
-- 2. Extend hr_employees with statutory/contribution basics
--    (additive; existing basic_salary etc. are preserved)
-- ============================================================
ALTER TABLE `hr_employees`
    ADD COLUMN IF NOT EXISTS `paye_exempt` tinyint(1) DEFAULT 0 COMMENT '1 = exempt from PAYE (rare; e.g. below threshold)',
    ADD COLUMN IF NOT EXISTS `nhf_exempt` tinyint(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `pension_exempt` tinyint(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `hourly_rate_override` decimal(15,2) DEFAULT NULL COMMENT 'Optional explicit hourly rate; else derived from basic/173h';

-- ============================================================
-- 3. Per-period payroll adjustments (one-off bonus/commission/deduction)
--    Lets finance add items without editing the employee master.
-- ============================================================
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
    FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. Salary advances / loans with amortized repayment
-- ============================================================
CREATE TABLE IF NOT EXISTS `hr_loans` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `employee_id` int(11) unsigned NOT NULL,
    `principal` decimal(15,2) NOT NULL DEFAULT 0.00,
    `interest_pct` decimal(5,2) DEFAULT 0.00 COMMENT 'Flat or per-annum %, simple',
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
    FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. Backfill hr_payroll with full breakdown columns
--    (existing columns basic_salary, allowances, commission, bonus,
--     overtime, deductions, tax, net_salary, status are kept.)
-- ============================================================
ALTER TABLE `hr_payroll`
    ADD COLUMN IF NOT EXISTS `gross_salary` decimal(15,2) DEFAULT 0.00 COMMENT 'basic + taxable allowances + overtime + bonus + commission',
    ADD COLUMN IF NOT EXISTS `taxable_income` decimal(15,2) DEFAULT 0.00 COMMENT 'gross minus approved exemptions',
    ADD COLUMN IF NOT EXISTS `paye` decimal(15,2) DEFAULT 0.00 COMMENT 'Nigeria PAYE (progressive, monthly)',
    ADD COLUMN IF NOT EXISTS `nhf` decimal(15,2) DEFAULT 0.00 COMMENT 'NHF 2.5%',
    ADD COLUMN IF NOT EXISTS `pension_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'Employee pension 8%',
    ADD COLUMN IF NOT EXISTS `pension_employer` decimal(15,2) DEFAULT 0.00 COMMENT 'Employer pension 10% (cost, not deducted from net)',
    ADD COLUMN IF NOT EXISTS `loan_deduction` decimal(15,2) DEFAULT 0.00 COMMENT 'Active loan repayment this period',
    ADD COLUMN IF NOT EXISTS `other_deductions` decimal(15,2) DEFAULT 0.00 COMMENT 'Manual deductions from hr_payroll_items',
    ADD COLUMN IF NOT EXISTS `total_deductions` decimal(15,2) DEFAULT 0.00 COMMENT 'paye + nhf + pension_employee + loan + other',
    ADD COLUMN IF NOT EXISTS `employer_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'gross + pension_employer (true staff cost)',
    ADD COLUMN IF NOT EXISTS `payslip_pdf` varchar(255) DEFAULT NULL COMMENT 'Generated payslip file path',
    ADD COLUMN IF NOT EXISTS `approved_by` int(10) unsigned DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `approved_at` timestamp NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `paid_at` timestamp NULL DEFAULT NULL;

-- Keep the legacy `deductions`/`tax` columns in sync-friendly state:
-- we no longer rely on them; `total_deductions` is the source of truth.
-- (No data migration of old rows needed; they will be recomputed on regen.)

SET FOREIGN_KEY_CHECKS=1;

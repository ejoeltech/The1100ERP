-- HR Module Schema Migration
-- Generated: 2026-02-02
-- Version 1.0.0

SET FOREIGN_KEY_CHECKS=0;

-- 1. Departments
CREATE TABLE IF NOT EXISTS `hr_departments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Designations (Job Roles)
CREATE TABLE IF NOT EXISTS `hr_designations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Employees (Extension of Users table)
CREATE TABLE IF NOT EXISTS `hr_employees` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL, -- Links to main users table
  `employee_code` varchar(50) UNIQUE NOT NULL,
  `department_id` int(11) unsigned DEFAULT NULL,
  `designation_id` int(11) unsigned DEFAULT NULL,
  `join_date` date NOT NULL,
  `termination_date` date DEFAULT NULL,
  `employment_status` enum('full_time', 'part_time', 'contract', 'intern') DEFAULT 'full_time',
  -- Personal Details
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male', 'female', 'other') DEFAULT NULL,
  `address` text,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  -- Bank Details (For Payroll)
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL, -- Tax Info
  -- Salary Structure
  `basic_salary` decimal(15,2) DEFAULT 0.00,
  `housing_allowance` decimal(15,2) DEFAULT 0.00,
  `transport_allowance` decimal(15,2) DEFAULT 0.00,
  `other_allowances` decimal(15,2) DEFAULT 0.00,
  `tax_deduction` decimal(15,2) DEFAULT 0.00, -- PAYE
  `pension_deduction` decimal(15,2) DEFAULT 0.00,
  
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Attendance
CREATE TABLE IF NOT EXISTS `hr_attendance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` enum('present', 'absent', 'late', 'half_day', 'on_leave') DEFAULT 'present',
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_date` (`date`),
  FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Leave Requests
CREATE TABLE IF NOT EXISTS `hr_leave_requests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned NOT NULL,
  `leave_type` enum('annual', 'sick', 'casual', 'maternity', 'paternity', 'unpaid') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text,
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `rejection_reason` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Payroll
CREATE TABLE IF NOT EXISTS `hr_payroll` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(15,2) NOT NULL DEFAULT 0.00,
  `commission` decimal(15,2) NOT NULL DEFAULT 0.00,
  `bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
  `overtime` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('generated', 'approved', 'paid') DEFAULT 'generated',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payroll` (`employee_id`, `month`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Recruitment Candidates
CREATE TABLE IF NOT EXISTS `hr_recruitment_candidates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `applied_for_role` varchar(255) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `status` enum('new', 'shortlisted', 'interviewed', 'hired', 'rejected') DEFAULT 'new',
  `interview_date` datetime DEFAULT NULL,
  `interview_notes` text,
  `ai_screening_score` int(3) DEFAULT NULL, -- Placeholder for AI analysis
  `ai_screening_summary` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Generated HR Documents (AI)
CREATE TABLE IF NOT EXISTS `hr_documents` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` enum('offer_letter', 'termination_letter', 'query', 'recommendation', 'contract', 'other') NOT NULL,
  `related_employee_id` int(11) unsigned DEFAULT NULL, -- Can be null for general docs
  `related_candidate_id` int(11) unsigned DEFAULT NULL, 
  `content` mediumtext, -- HTML or Markdown content
  `generated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`related_employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`related_candidate_id`) REFERENCES `hr_recruitment_candidates` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. HR Settings specific
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `category`, `description`) VALUES 
('hr_work_start_time', '09:00', 'hr', 'Default work start time'),
('hr_work_end_time', '17:00', 'hr', 'Default work end time'),
('hr_currency_symbol', '₦', 'hr', 'Currency for payroll'),
('hr_recruitment_email_template', 'Dear {name},\n\nThank you for applying...', 'hr', 'Default recruitment email');

SET FOREIGN_KEY_CHECKS=1;

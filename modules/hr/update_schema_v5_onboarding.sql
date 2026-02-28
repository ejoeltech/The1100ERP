-- HR Module Schema Update v5
-- Adds support for Employee Onboarding (Self-Service)

SET FOREIGN_KEY_CHECKS=0;

-- 1. Table for Signup Codes
CREATE TABLE IF NOT EXISTS `hr_onboarding_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `role` varchar(50) DEFAULT 'employee', -- intended role
  `is_used` tinyint(1) DEFAULT 0,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table for Onboarding Entries (Temp Employee Data)
CREATE TABLE IF NOT EXISTS `hr_onboarding_entries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code_id` int(11) unsigned NOT NULL,
  `signup_code` varchar(20) NOT NULL, -- Cached for easy lookups
  
  -- Personal
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text,
  
  -- Identity & Files
  `passport_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `nin_number` varchar(50) DEFAULT NULL,
  `bvn_number` varchar(50) DEFAULT NULL,
  
  -- Kin & Refs
  `next_of_kin_name` varchar(255) DEFAULT NULL,
  `next_of_kin_phone` varchar(50) DEFAULT NULL,
  `next_of_kin_relationship` varchar(100) DEFAULT NULL,
  
  -- Status
  `status` enum('pending','submitted','rejected','imported') DEFAULT 'pending',
  `admin_feedback` text,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code_entry` (`code_id`),
  FOREIGN KEY (`code_id`) REFERENCES `hr_onboarding_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

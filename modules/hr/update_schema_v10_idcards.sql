-- HR Module Schema Update v10
-- Integrated ID Card Maker (cards + saved templates + per-template defaults).
-- Runtime prefill comes from hr_employees / settings; no demo data seeded.

CREATE TABLE IF NOT EXISTS `hr_id_cards` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned DEFAULT NULL,
  `org_name` varchar(120) NOT NULL DEFAULT '',
  `org_address` varchar(255) NOT NULL DEFAULT '',
  `staff_name` varchar(120) NOT NULL DEFAULT '',
  `staff_id` varchar(60) NOT NULL DEFAULT '',
  `dob` varchar(30) NOT NULL DEFAULT '',
  `department` varchar(120) NOT NULL DEFAULT '',
  `job_title` varchar(120) NOT NULL DEFAULT '',
  `hire_date` varchar(30) NOT NULL DEFAULT '',
  `employment_type` varchar(40) NOT NULL DEFAULT '',
  `emergency_contact` varchar(60) NOT NULL DEFAULT '',
  `phone` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(120) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `principal` varchar(120) NOT NULL DEFAULT '',
  `layout_json` mediumtext,
  `labels_json` mediumtext,
  `back_json` mediumtext,
  `code_json` mediumtext,
  `template` tinyint NOT NULL DEFAULT 1,
  `template_id` int(11) unsigned DEFAULT NULL,
  `layout` varchar(12) NOT NULL DEFAULT 'horizontal',
  `color1` varchar(10) NOT NULL DEFAULT '#14b8a6',
  `color2` varchar(10) NOT NULL DEFAULT '#0f766e',
  `photo_path` varchar(255) NOT NULL DEFAULT '',
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_staff_name` (`staff_name`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_employee_id` (`employee_id`),
  FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_id_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `base` tinyint NOT NULL DEFAULT 1,
  `color1` varchar(10) NOT NULL DEFAULT '#0d6b3f',
  `color2` varchar(10) NOT NULL DEFAULT '#8fd14f',
  `layout_json` mediumtext,
  `labels_json` mediumtext,
  `back_json` mediumtext,
  `code_json` mediumtext,
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_id_template_defaults` (
  `base` tinyint NOT NULL,
  `color1` varchar(10) NOT NULL DEFAULT '#0d6b3f',
  `color2` varchar(10) NOT NULL DEFAULT '#8fd14f',
  `layout_json` mediumtext,
  `labels_json` mediumtext,
  `back_json` mediumtext,
  `code_json` mediumtext,
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`base`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

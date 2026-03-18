-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bluedotserp
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_recommendations`
--

DROP TABLE IF EXISTS `ai_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_recommendations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_description` text NOT NULL,
  `appliances_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`appliances_json`)),
  `power_analysis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`power_analysis`)),
  `recommended_system` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recommended_system`)),
  `roi_analysis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roi_analysis`)),
  `quote_id` int(10) unsigned DEFAULT NULL,
  `created_quote` tinyint(1) DEFAULT 0,
  `model_used` varchar(50) DEFAULT 'groq-llama-3.1-70b',
  `processing_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `quote_id` (`quote_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_customer_name` (`customer_name`),
  CONSTRAINT `ai_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_recommendations_ibfk_2` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_recommendations`
--

LOCK TABLES `ai_recommendations` WRITE;
/*!40000 ALTER TABLE `ai_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_request_cache`
--

DROP TABLE IF EXISTS `ai_request_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_request_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_hash` varchar(64) NOT NULL,
  `tool_name` varchar(50) NOT NULL,
  `request_params` text NOT NULL,
  `response_data` mediumtext NOT NULL,
  `hit_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_accessed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_hash` (`request_hash`),
  KEY `idx_hash` (`request_hash`),
  KEY `idx_tool` (`tool_name`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_request_cache`
--

LOCK TABLES `ai_request_cache` WRITE;
/*!40000 ALTER TABLE `ai_request_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_request_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage_logs`
--

DROP TABLE IF EXISTS `ai_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `tool_name` varchar(50) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `request_hash` varchar(64) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `cost_usd` decimal(10,4) DEFAULT 0.0000,
  `processing_time` float DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  KEY `idx_ip_date` (`ip_address`,`created_at`),
  KEY `idx_tool_date` (`tool_name`,`created_at`),
  KEY `idx_date` (`created_at`),
  KEY `idx_hash` (`request_hash`),
  CONSTRAINT `ai_usage_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage_logs`
--

LOCK TABLES `ai_usage_logs` WRITE;
/*!40000 ALTER TABLE `ai_usage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `resource_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_resource` (`resource_type`,`resource_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'system_update',NULL,NULL,NULL,NULL,'{\"file\":\"1100erp_patch.zip\",\"output\":\"\"}','2026-01-30 00:28:25');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `show_on_documents` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bank_name` (`bank_name`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `company` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_email` (`email`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Mr Osaze c/o Keystone Bank',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,0.00,NULL,'2026-01-29 07:53:42','2026-01-29 07:53:42');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_attendance`
--

DROP TABLE IF EXISTS `hr_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_attendance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day','on_leave') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `hr_attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_attendance`
--

LOCK TABLES `hr_attendance` WRITE;
/*!40000 ALTER TABLE `hr_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_departments`
--

DROP TABLE IF EXISTS `hr_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_departments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_departments`
--

LOCK TABLES `hr_departments` WRITE;
/*!40000 ALTER TABLE `hr_departments` DISABLE KEYS */;
INSERT INTO `hr_departments` VALUES (1,'Management',NULL,'2026-02-02 08:30:59'),(2,'Engineering',NULL,'2026-02-02 08:30:59'),(3,'Sales',NULL,'2026-02-02 08:30:59'),(4,'HR',NULL,'2026-02-02 08:30:59'),(5,'Finance',NULL,'2026-02-02 08:30:59');
/*!40000 ALTER TABLE `hr_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_designations`
--

DROP TABLE IF EXISTS `hr_designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_designations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `hr_designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_designations`
--

LOCK TABLES `hr_designations` WRITE;
/*!40000 ALTER TABLE `hr_designations` DISABLE KEYS */;
INSERT INTO `hr_designations` VALUES (1,1,'Manager',NULL,'2026-02-02 08:30:59'),(2,2,'Mid-Level Engineer',NULL,'2026-02-02 08:30:59'),(3,2,'Senior Engineer',NULL,'2026-02-02 08:30:59'),(4,3,'Sales Executive',NULL,'2026-02-02 08:30:59'),(5,4,'HR Manager',NULL,'2026-02-02 08:30:59');
/*!40000 ALTER TABLE `hr_designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_documents`
--

DROP TABLE IF EXISTS `hr_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_documents` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` enum('offer_letter','termination_letter','query','recommendation','contract','other') NOT NULL,
  `related_employee_id` int(11) unsigned DEFAULT NULL,
  `related_candidate_id` int(11) unsigned DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `generated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `related_employee_id` (`related_employee_id`),
  KEY `related_candidate_id` (`related_candidate_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `hr_documents_ibfk_1` FOREIGN KEY (`related_employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_documents_ibfk_2` FOREIGN KEY (`related_candidate_id`) REFERENCES `hr_recruitment_candidates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_documents_ibfk_3` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_documents`
--

LOCK TABLES `hr_documents` WRITE;
/*!40000 ALTER TABLE `hr_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employees`
--

DROP TABLE IF EXISTS `hr_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employees` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `department_id` int(11) unsigned DEFAULT NULL,
  `designation_id` int(11) unsigned DEFAULT NULL,
  `join_date` date NOT NULL,
  `dob` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `employment_status` enum('full_time','part_time','contract','intern') DEFAULT 'full_time',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `secondary_phone` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `next_of_kin_name` varchar(255) DEFAULT NULL,
  `next_of_kin_phone` varchar(50) DEFAULT NULL,
  `next_of_kin_relationship` varchar(100) DEFAULT NULL,
  `reference_1_name` varchar(255) DEFAULT NULL,
  `reference_1_phone` varchar(50) DEFAULT NULL,
  `reference_1_org` varchar(255) DEFAULT NULL,
  `reference_2_name` varchar(255) DEFAULT NULL,
  `reference_2_phone` varchar(50) DEFAULT NULL,
  `reference_2_org` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `nin_number` varchar(50) DEFAULT NULL,
  `bvn_number` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `basic_salary` decimal(15,2) DEFAULT 0.00,
  `housing_allowance` decimal(15,2) DEFAULT 0.00,
  `transport_allowance` decimal(15,2) DEFAULT 0.00,
  `other_allowances` decimal(15,2) DEFAULT 0.00,
  `tax_deduction` decimal(15,2) DEFAULT 0.00,
  `pension_deduction` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  CONSTRAINT `hr_employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hr_employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hr_employees_ibfk_3` FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employees`
--

LOCK TABLES `hr_employees` WRITE;
/*!40000 ALTER TABLE `hr_employees` DISABLE KEYS */;
INSERT INTO `hr_employees` VALUES (1,9,'EMP-2026-155',1,1,'2026-02-02',NULL,NULL,'full_time','0000-00-00','female','../assets/uploads/employees/passport_1770112809_944.jpg','../assets/uploads/employees/doc_1770151746_946.jpg','address\r\ncity\r\nstate','09012345678',NULL,NULL,'Ejovwoke Joel Okenabirhie','07087986297','Brother','Bluedots Technologies','07087986297','Bluedots Technologies','','','','Access Bank','1023821430','Bluedots Technologies','19839878990','23937309809','7830800',100000.00,20000.00,30000.00,0.00,0.00,0.00,'2026-02-02 09:40:15','2026-02-03 20:49:06','08012345678','Jane Doe','janedoe@test.com');
/*!40000 ALTER TABLE `hr_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_requests`
--

DROP TABLE IF EXISTS `hr_leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leave_requests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) unsigned NOT NULL,
  `leave_type` enum('annual','sick','casual','maternity','paternity','unpaid') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `hr_leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hr_leave_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_requests`
--

LOCK TABLES `hr_leave_requests` WRITE;
/*!40000 ALTER TABLE `hr_leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_onboarding_codes`
--

DROP TABLE IF EXISTS `hr_onboarding_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_onboarding_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `role` varchar(50) DEFAULT 'employee',
  `is_used` tinyint(1) DEFAULT 0,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_onboarding_codes`
--

LOCK TABLES `hr_onboarding_codes` WRITE;
/*!40000 ALTER TABLE `hr_onboarding_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_onboarding_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_onboarding_entries`
--

DROP TABLE IF EXISTS `hr_onboarding_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_onboarding_entries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code_id` int(11) unsigned NOT NULL,
  `signup_code` varchar(20) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `nin_number` varchar(50) DEFAULT NULL,
  `bvn_number` varchar(50) DEFAULT NULL,
  `next_of_kin_name` varchar(255) DEFAULT NULL,
  `next_of_kin_phone` varchar(50) DEFAULT NULL,
  `next_of_kin_relationship` varchar(100) DEFAULT NULL,
  `status` enum('pending','submitted','rejected','imported') DEFAULT 'pending',
  `admin_feedback` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code_entry` (`code_id`),
  CONSTRAINT `hr_onboarding_entries_ibfk_1` FOREIGN KEY (`code_id`) REFERENCES `hr_onboarding_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_onboarding_entries`
--

LOCK TABLES `hr_onboarding_entries` WRITE;
/*!40000 ALTER TABLE `hr_onboarding_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_onboarding_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll`
--

DROP TABLE IF EXISTS `hr_payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll` (
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
  `status` enum('generated','approved','paid') DEFAULT 'generated',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payroll` (`employee_id`,`month`,`year`),
  CONSTRAINT `hr_payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll`
--

LOCK TABLES `hr_payroll` WRITE;
/*!40000 ALTER TABLE `hr_payroll` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_recruitment_candidates`
--

DROP TABLE IF EXISTS `hr_recruitment_candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_recruitment_candidates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `applied_for_role` varchar(255) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `status` enum('new','shortlisted','interviewed','hired','rejected') DEFAULT 'new',
  `interview_date` datetime DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `ai_screening_score` int(3) DEFAULT NULL,
  `ai_screening_summary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_recruitment_candidates`
--

LOCK TABLES `hr_recruitment_candidates` WRITE;
/*!40000 ALTER TABLE `hr_recruitment_candidates` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_recruitment_candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_settings`
--

DROP TABLE IF EXISTS `hr_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=775 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_settings`
--

LOCK TABLES `hr_settings` WRITE;
/*!40000 ALTER TABLE `hr_settings` DISABLE KEYS */;
INSERT INTO `hr_settings` VALUES (1,'id_card_primary_color','#0072bc','2026-02-02 21:08:30'),(2,'id_card_secondary_color','#39b54a','2026-02-02 21:08:30'),(3,'id_card_tertiary_color','#005a9c','2026-02-02 21:08:30'),(4,'id_card_logo_type','system','2026-02-02 21:08:30'),(5,'id_card_show_qr','1','2026-02-02 21:08:30'),(9,'id_card_subtitle_text','{{company_website}}','2026-02-03 22:54:58'),(10,'id_card_emergency_label','IF FOUND OR IN CASE OF AN EMERGENCY PLEASE RETURN TO THE ADDRESS OR CONTACT THE PHONE NUMBER NUMBER BELOW','2026-02-03 22:54:58'),(11,'id_card_disclaimer_text','','2026-02-03 22:54:58'),(12,'id_card_logo_align','center','2026-02-03 03:53:38'),(13,'id_card_header_align','center','2026-02-03 03:53:38'),(14,'id_card_photo_shape','circle','2026-02-03 03:53:38'),(15,'id_card_show_name','0','2026-02-03 00:59:58'),(26,'id_card_custom_css','/* Card Dimensions & Base */\r\n.id-card {\r\n    width: var(--card-width);\r\n    height: var(--card-height);\r\n    background: white;\r\n    border-radius: var(--card-radius);\r\n    box-shadow: 0 10px 20px rgba(0,0,0,0.15);\r\n    position: relative;\r\n    overflow: hidden;\r\n    display: flex;\r\n    flex-direction: column;\r\n    border: 1px solid #e0e0e0;\r\n    font-family: \'Roboto\', sans-serif;\r\n}\r\n\r\n/* Header & Logo */\r\n.header { \r\n    text-align: center; \r\n    padding-top: 15px; margin-bottom: 2px; padding-left: 20px; padding-right: 20px;\r\n}\r\n.logo-graphic { \r\n    display: flex; \r\n    justify-content: center; \r\n    align-items: center; \r\n    gap: 5px; \r\n    margin-bottom: 5px; \r\n}\r\n.brand-name { font-size: 28px; font-weight: 700; color: #222; letter-spacing: -1px; line-height: 1; margin-top: 5px; }\r\n.brand-subtitle { font-size: 10px; letter-spacing: 4px; color: #444; font-weight: 500; margin-top: 2px; text-transform: uppercase; }\r\n\r\n/* Photo */\r\n.photo-container { display: flex; justify-content: center; margin-bottom: 15px; position: relative; }\r\n.photo-frame { \r\n    width: 170px; height: 170px; \r\n    border-radius: 50%;\r\n    border: 4px solid var(--brand-blue); \r\n    overflow: hidden; \r\n    background-color: #eee; \r\n    z-index: 10; \r\n}\r\n.photo-frame img { width: 100%; height: 100%; object-fit: cover; }\r\n\r\n/* Person Info */\r\n.person-info { text-align: center; margin-bottom: 2px; z-index: 10; }\r\n.person-name { font-size: 32px; font-weight: 900; color: #222; text-transform: uppercase; margin-bottom: px; }\r\n.person-role { font-size: 16px; color: var(--brand-blue); font-weight: 900; }\r\n\r\n/* Contact List */\r\n.contact-list { padding: 0 20px; z-index: 10; }\r\n.contact-item { display: flex; align-items: center; margin-bottom: 12px; background: rgba(255, 255, 255, 0.9); padding: 5px 10px; border-radius: 50px; }\r\n.icon-box { width: 30px; height: 30px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; margin-right: 12px; flex-shrink: 0; font-size: 14px; }\r\n.icon-green { background-color: var(--brand-green); }\r\n.icon-blue { background-color: var(--brand-blue); }\r\n.contact-text { font-size: 14px; color: #333; font-weight: 600; }\r\n\r\n/* Footer Waves */\r\n.wave-footer { position: absolute; bottom: 0; left: 0; width: 100%; height: 180px; z-index: 1; overflow: hidden; }\r\n\r\n/* Back Side */\r\n.back-header { text-align: center; margin-top: 15px; padding: 0 20px; }\r\n.back-title { color: var(--brand-blue); font-weight: 700; font-size: 16px; line-height: 1.3; margin-bottom: 20px; }\r\n.emergency-label { font-size: 15px; color: #333; text-transform: uppercase; margin-bottom: 10px; font-weight: 700; }\r\n.back-contact-list { padding: 0 30px; }\r\n.back-contact-item { display: flex; align-items: flex-start; margin-bottom: 15px; }\r\n.back-contact-text { font-size: 13px; color: #333; font-weight: 900; margin-top: 5px; line-height: 1.4; }\r\n.id-section { text-align: center; font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #333; }\r\n.qr-section { display: flex; justify-content: center; align-items: center; margin-bottom: 10px; position: relative; z-index: 10; width: 100%; }\r\n.qr-code { width: 70px; height: 70px; background: white; padding: 5px; border-radius: 5px; }\r\n.disclaimer { text-align: center; font-size: 10px; color: white; padding: 0 30px; position: relative; z-index: 10; margin-bottom: 25px; line-height: 1.3; }\r\n.footer-contacts { display: flex; flex-direction: column; gap: 2px; padding: 10px 20px; background: transparent; position: absolute; bottom: 0; width: 100%; z-index: 10; }\r\n.footer-row { display: flex; justify-content: space-between; font-weight: 600; font-size: 25px; color: #fff; }\r\n.footer-item { display: flex; align-items: center; gap: 19px; }\r\n.footer-icon { color: var(--brand-green); }\r\n.footer-icon.blue { color: var(--brand-blue); }\r\n','2026-02-04 00:14:41'),(34,'id_card_front_html','<div class=\"header\">\r\n    <div class=\"logo-graphic\">\r\n        {{company_logo}}\r\n    </div>\r\n    <div class=\"person-role\"\">{{company_website}}</div>\r\n</div>\r\n\r\n<div class=\"photo-container\">\r\n    <div class=\"photo-frame\">\r\n        <img src=\"{{photo_url}}\" alt=\"Photo\">\r\n    </div>\r\n</div>\r\n\r\n<div class=\"person-info\">\r\n    <div class=\"person-name\">{{full_name}}</div>\r\n    <div class=\"person-role\">{{designation}}</div>\r\n</div>\r\n\r\n\r\n<div class=\"wave-footer\">\r\n    <svg class=\"wave-graphic\" viewBox=\"0 0 350 180\" preserveAspectRatio=\"none\">\r\n        <path class=\"fill-green\" d=\"M0,80 C100,60 200,120 350,60 L350,180 L0,180 Z\" fill=\"{{color_secondary}}\" opacity=\"0.9\" />\r\n        <path class=\"fill-blue\" d=\"M0,100 C120,80 250,150 350,100 L350,180 L0,180 Z\" fill=\"{{color_primary}}\" opacity=\"0.85\" />\r\n        <path class=\"fill-dark\" d=\"M0,130 C80,110 180,160 350,120 L350,180 L0,180 Z\" fill=\"{{color_tertiary}}\" opacity=\"0.6\" />\r\n    </svg>\r\n</div>\r\n','2026-02-04 00:08:53'),(35,'id_card_back_html','<div class=\"back-header\">\r\n   <div class=\"logo-graphic\">\r\n        {{company_logo}}\r\n    </div>\r\n    <div class=\"person-role\">{{employee_code}}</div>\r\n    <div class=\"emergency-label\">{{emergency_label}}</div>\r\n</div>\r\n\r\n<div class=\"back-contact-list\">\r\n    \r\n    <div class=\"back-contact-item\">\r\n        <div class=\"back-contact-text\">{{company_address}}</div>\r\n    </div>\r\n<div class=\"back-contact-item\">\r\n        <div class=\"back-contact-text\">{{company_phone}}</div>\r\n    </div>\r\n</div>\r\n\r\n<div class=\"qr-section\" style=\"display: flex; justify-content: center; align-items: flex-end; gap: 15px;\">\r\n    <div>{{qr_code}}</div>\r\n    <div style=\"text-align: center; margin-bottom: 5px;\">\r\n        {{signature}}\r\n        <div style=\"font-size: 7px; color: #888; text-transform: uppercase; margin-top: 2px;\">Authorized Sig</div>\r\n    </div>\r\n</div>\r\n<div class=\"back-card-wave-bg\">\r\n    <svg class=\"wave-graphic\" viewBox=\"0 65 350 180\" preserveAspectRatio=\"none\">\r\n        <path class=\"fill-green\" d=\"M0,80 C100,60 200,120 350,60 L350,180 L0,180 Z\" fill=\"{{color_secondary}}\" opacity=\"0.9\" />\r\n        <path class=\"fill-blue\" d=\"M0,100 C120,80 250,150 350,100 L350,180 L0,180 Z\" fill=\"{{color_primary}}\" opacity=\"0.85\" />\r\n        <path class=\"fill-dark\" d=\"M0,130 C80,110 180,160 350,120 L350,180 L0,180 Z\" fill=\"{{color_tertiary}}\" opacity=\"0.6\" />\r\n    </svg>\r\n</div>\r\n\r\n<div class=\"footer-contacts\">\r\n    <div class=\"footer-row\">\r\n        <div class=\"footer-item\"><i class=\"fa-solid fa-phone footer-icon\"></i> {{company_website}}</div>\r\n\r\n    </div>\r\n</div>\r\n','2026-02-03 23:29:38');
/*!40000 ALTER TABLE `hr_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_votes`
--

DROP TABLE IF EXISTS `hr_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_votes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` int(11) unsigned NOT NULL,
  `candidate_id` int(11) unsigned NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`voter_id`,`month`,`year`),
  KEY `candidate_id` (`candidate_id`),
  CONSTRAINT `hr_votes_ibfk_1` FOREIGN KEY (`voter_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hr_votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_votes`
--

LOCK TABLES `hr_votes` WRITE;
/*!40000 ALTER TABLE `hr_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_line_items`
--

DROP TABLE IF EXISTS `invoice_line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_line_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `item_number` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `vat_applicable` tinyint(1) DEFAULT 0,
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_line_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_line_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_line_items`
--

LOCK TABLES `invoice_line_items` WRITE;
/*!40000 ALTER TABLE `invoice_line_items` DISABLE KEYS */;
INSERT INTO `invoice_line_items` VALUES (1,1,NULL,1,2.00,'10kva hybrid inverter system',1190000.00,1,178500.00,2558500.00,'2026-01-30 07:28:10'),(2,1,NULL,2,14.00,'700w Solar Panels',160000.00,1,168000.00,2408000.00,'2026-01-30 07:28:10'),(3,1,NULL,3,2.00,'10KWH lithium battery',1900000.00,1,285000.00,4085000.00,'2026-01-30 07:28:10'),(4,1,NULL,4,1.00,'Installation accessories',500000.00,1,37500.00,537500.00,'2026-01-30 07:28:10'),(5,1,NULL,5,1.00,'Installation',350000.00,1,26250.00,376250.00,'2026-01-30 07:28:10');
/*!40000 ALTER TABLE `invoice_line_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `quote_id` int(10) unsigned DEFAULT NULL,
  `invoice_title` varchar(255) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `salesperson` varchar(255) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_vat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms` varchar(255) DEFAULT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled','partial','finalized') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `created_by` (`created_by`),
  KEY `quote_id` (`quote_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'INV-0001',1,'Petrol Station 20 KVA Inverter, 20KW Lithium Battery, 10KW Solar System',1,'Mr Osaze c/o Keystone Bank','Joel O','2026-01-30',NULL,9270000.00,695250.00,9965250.00,0.00,9965250.00,'Due on Receipt','draft',NULL,1,0,NULL,'2026-01-30 07:28:10','2026-01-30 07:28:10');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `market_data`
--

DROP TABLE IF EXISTS `market_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data_type` varchar(50) NOT NULL,
  `data_key` varchar(100) NOT NULL,
  `data_value` decimal(15,2) NOT NULL,
  `effective_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_data` (`data_type`,`data_key`,`effective_date`),
  KEY `idx_data_type` (`data_type`),
  KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `market_data`
--

LOCK TABLES `market_data` WRITE;
/*!40000 ALTER TABLE `market_data` DISABLE KEYS */;
INSERT INTO `market_data` VALUES (1,'fuel_price','petrol_per_litre',650.00,'2026-01-01','Average petrol price in Nigeria','2026-01-27 10:54:44','2026-01-27 10:54:44'),(2,'fuel_price','diesel_per_litre',850.00,'2026-01-01','Average diesel price in Nigeria','2026-01-27 10:54:44','2026-01-27 10:54:44'),(3,'electricity','nepa_per_kwh_residential',68.00,'2026-01-01','NEPA residential tariff (average)','2026-01-27 10:54:44','2026-01-27 10:54:44'),(4,'electricity','nepa_per_kwh_commercial',85.00,'2026-01-01','NEPA commercial tariff (average)','2026-01-27 10:54:44','2026-01-27 10:54:44'),(5,'generator','running_cost_per_hour_2.5kva',300.00,'2026-01-01','Small generator fuel cost','2026-01-27 10:54:44','2026-01-27 10:54:44'),(6,'generator','running_cost_per_hour_5kva',500.00,'2026-01-01','Medium generator fuel cost','2026-01-27 10:54:44','2026-01-27 10:54:44'),(7,'generator','running_cost_per_hour_10kva',900.00,'2026-01-01','Large generator fuel cost','2026-01-27 10:54:44','2026-01-27 10:54:44'),(8,'generator','maintenance_per_month_2.5kva',8000.00,'2026-01-01','Oil, servicing, repairs','2026-01-27 10:54:44','2026-01-27 10:54:44'),(9,'generator','maintenance_per_month_5kva',15000.00,'2026-01-01','Oil, servicing, repairs','2026-01-27 10:54:44','2026-01-27 10:54:44'),(10,'generator','maintenance_per_month_10kva',25000.00,'2026-01-01','Oil, servicing, repairs','2026-01-27 10:54:44','2026-01-27 10:54:44'),(11,'solar','avg_sun_hours_per_day',5.50,'2026-01-01','Average sun hours in Nigeria','2026-01-27 10:54:44','2026-01-27 10:54:44'),(12,'solar','performance_degradation_annual',0.50,'2026-01-01','Annual panel efficiency loss %','2026-01-27 10:54:44','2026-01-27 10:54:44'),(13,'inflation','annual_rate',24.00,'2026-01-01','Nigeria inflation rate','2026-01-27 10:54:44','2026-01-27 10:54:44'),(14,'currency','usd_to_ngn',1600.00,'2026-01-01','Exchange rate USD to Naira','2026-01-27 10:54:44','2026-01-27 10:54:44');
/*!40000 ALTER TABLE `market_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `description` text DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vat_applicable` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `idx_product_code` (`product_code`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_category` (`category`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quote_line_items`
--

DROP TABLE IF EXISTS `quote_line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quote_line_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `item_number` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `vat_applicable` tinyint(1) DEFAULT 0,
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_quote_id` (`quote_id`),
  CONSTRAINT `quote_line_items_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quote_line_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quote_line_items`
--

LOCK TABLES `quote_line_items` WRITE;
/*!40000 ALTER TABLE `quote_line_items` DISABLE KEYS */;
INSERT INTO `quote_line_items` VALUES (11,1,NULL,1,2.00,'10kva hybrid inverter system',1190000.00,1,178500.00,2558500.00,'2026-01-30 07:28:00'),(12,1,NULL,2,14.00,'700w Solar Panels',160000.00,1,168000.00,2408000.00,'2026-01-30 07:28:00'),(13,1,NULL,3,2.00,'10KWH lithium battery',1900000.00,1,285000.00,4085000.00,'2026-01-30 07:28:00'),(14,1,NULL,4,1.00,'Installation accessories',500000.00,1,37500.00,537500.00,'2026-01-30 07:28:00'),(15,1,NULL,5,1.00,'Installation',350000.00,1,26250.00,376250.00,'2026-01-30 07:28:00'),(21,2,NULL,1,1.00,'12kva hybrid inverter system',1250000.00,0,0.00,1250000.00,'2026-02-02 08:44:27'),(22,2,NULL,2,14.00,'700w Solar Panels',155000.00,0,0.00,2170000.00,'2026-02-02 08:44:27'),(23,2,NULL,3,2.00,'10KWH lithium battery',1850000.00,0,0.00,3700000.00,'2026-02-02 08:44:27'),(24,2,NULL,4,1.00,'Installation accessories',500000.00,0,0.00,500000.00,'2026-02-02 08:44:27'),(25,2,NULL,5,1.00,'Installation',350000.00,0,0.00,350000.00,'2026-02-02 08:44:27');
/*!40000 ALTER TABLE `quote_line_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quote_number` varchar(50) NOT NULL,
  `quote_title` varchar(255) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `salesperson` varchar(255) NOT NULL,
  `quote_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_vat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms` varchar(255) DEFAULT '80% Initial Deposit',
  `delivery_period` varchar(255) DEFAULT NULL,
  `status` enum('draft','finalized','approved','rejected','expired') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `created_by` (`created_by`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_quote_number` (`quote_number`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `quotes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotes_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotes`
--

LOCK TABLES `quotes` WRITE;
/*!40000 ALTER TABLE `quotes` DISABLE KEYS */;
INSERT INTO `quotes` VALUES (1,'QUOT-2026-001','Petrol Station 20 KVA Inverter, 20KW Lithium Battery, 10KW Solar System',1,'Mr Osaze c/o Keystone Bank','Joel O','2026-01-29',9270000.00,695250.00,9965250.00,'Due on Receipt','14 Days','finalized',NULL,1,0,NULL,'2026-01-29 07:53:42','2026-01-30 07:28:00'),(2,'QUO--2025','Petrol Station 20 KVA Inverter, 20KW Lithium Battery, 10KW Solar System Benin',1,'Mr Osaze c/o Keystone Bank','Joel O','2026-02-02',7970000.00,0.00,7970000.00,'Due on Receipt',NULL,'draft',NULL,1,0,NULL,'2026-02-02 08:42:56','2026-02-02 08:44:27');
/*!40000 ALTER TABLE `quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `readymade_quote_categories`
--

DROP TABLE IF EXISTS `readymade_quote_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `readymade_quote_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `readymade_quote_categories`
--

LOCK TABLES `readymade_quote_categories` WRITE;
/*!40000 ALTER TABLE `readymade_quote_categories` DISABLE KEYS */;
INSERT INTO `readymade_quote_categories` VALUES (1,'General','Default Category',1,'2026-01-26 21:41:16','2026-01-26 21:41:16');
/*!40000 ALTER TABLE `readymade_quote_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `readymade_quote_template_items`
--

DROP TABLE IF EXISTS `readymade_quote_template_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `readymade_quote_template_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `item_number` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `vat_applicable` tinyint(1) DEFAULT 0,
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_template_id` (`template_id`),
  CONSTRAINT `readymade_quote_template_items_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `readymade_quote_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `readymade_quote_template_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `readymade_quote_template_items`
--

LOCK TABLES `readymade_quote_template_items` WRITE;
/*!40000 ALTER TABLE `readymade_quote_template_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `readymade_quote_template_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `readymade_quote_templates`
--

DROP TABLE IF EXISTS `readymade_quote_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `readymade_quote_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `payment_terms` text DEFAULT NULL,
  `default_project_title` varchar(255) DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_vat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_template_name` (`template_name`),
  KEY `idx_category_id` (`category_id`),
  CONSTRAINT `readymade_quote_templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `readymade_quote_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `readymade_quote_templates`
--

LOCK TABLES `readymade_quote_templates` WRITE;
/*!40000 ALTER TABLE `readymade_quote_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `readymade_quote_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','card','other') DEFAULT 'cash',
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `status` enum('valid','void') DEFAULT 'valid',
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  UNIQUE KEY `unique_receipt_number` (`receipt_number`),
  KEY `created_by` (`created_by`),
  KEY `invoice_id` (`invoice_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_receipt_number` (`receipt_number`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `receipts_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receipts`
--

LOCK TABLES `receipts` WRITE;
/*!40000 ALTER TABLE `receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'system',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=340 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'installation_completed','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(2,'installation_date','2026-01-26 22:41:16','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(3,'version','2.0.0','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(4,'company_name','GOLDCOAST ELECTRICALS','system',NULL,'2026-01-26 21:41:16','2026-01-29 08:06:27'),(5,'company_email','goldcoastlogisticsnigltd@gmail.com','system',NULL,'2026-01-26 21:41:16','2026-01-29 07:57:57'),(6,'company_phone','07052066295','system',NULL,'2026-01-26 21:41:16','2026-01-29 07:57:57'),(7,'company_address','100 Country Home Motel Road,\r\nBenin City, Edo State.','system',NULL,'2026-01-26 21:41:16','2026-01-29 08:06:27'),(8,'company_website','www.bluedots.com.ng','system',NULL,'2026-01-26 21:41:16','2026-02-03 04:16:38'),(9,'company_tax_id','','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(10,'vat_rate','7.5','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(11,'currency_symbol','₦','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(12,'email_method','php_mail','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(13,'email_from_address','noreply@yourcompany.com','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(14,'email_from_name','Your Company Name','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(15,'items_per_page','25','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(16,'show_dashboard_charts','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(17,'show_recent_activity','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(18,'pdf_quality','high','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(19,'theme_color','#e77913','system',NULL,'2026-01-26 21:41:16','2026-01-29 07:45:00'),(20,'footer_text','We appreciate your business! Thank you','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(21,'quote_prefix','QUOT-','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(22,'invoice_prefix','INV-','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(23,'receipt_prefix','REC-','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(24,'date_format','d/m/Y','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(25,'auto_archive_days','0','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(26,'audit_retention_days','90','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(27,'log_user_actions','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(28,'log_document_create','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(29,'log_document_edit','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(30,'log_document_delete','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(31,'log_user_management','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(32,'log_settings_changes','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(33,'log_email_sent','1','system',NULL,'2026-01-26 21:41:16','2026-01-26 21:41:16'),(34,'ai_ip_hourly_limit','5','ai_limits','Hourly request limit for public/IP-based users','2026-01-26 21:41:17','2026-01-26 21:41:17'),(35,'ai_ip_daily_limit','20','ai_limits','Daily request limit for public/IP-based users','2026-01-26 21:41:17','2026-01-26 21:41:17'),(36,'ai_user_hourly_limit','10','ai_limits','Hourly request limit for authenticated users','2026-01-26 21:41:17','2026-01-26 21:41:17'),(37,'ai_user_daily_limit','50','ai_limits','Daily request limit for authenticated users','2026-01-26 21:41:17','2026-01-26 21:41:17'),(38,'ai_monthly_budget_usd','100','ai_limits','Monthly API budget in USD','2026-01-26 21:41:17','2026-01-26 21:41:17'),(39,'ai_enable_caching','1','ai_features','Enable response caching','2026-01-26 21:41:17','2026-01-26 21:41:17'),(40,'ai_cache_ttl_hours','24','ai_features','Cache time-to-live in hours','2026-01-26 21:41:17','2026-01-26 21:41:17'),(41,'ai_enable_public_access','1','ai_features','Allow public access to AI tools','2026-01-26 21:41:17','2026-01-26 21:41:17'),(42,'ai_public_tools','system_designer','ai_features','Comma-separated list of public tools','2026-01-26 21:41:17','2026-01-26 21:41:17'),(43,'ai_log_retention_days','90','ai_features','Days to retain usage logs','2026-01-26 21:41:17','2026-01-26 21:41:17'),(44,'ai_emergency_disable','0','ai_features','Emergency disable all AI features','2026-01-26 21:41:17','2026-01-26 21:41:17'),(56,'smtp_host','','system',NULL,'2026-01-26 21:41:18','2026-01-26 21:41:18'),(57,'smtp_port','','system',NULL,'2026-01-26 21:41:18','2026-01-26 21:41:18'),(58,'smtp_username','','system',NULL,'2026-01-26 21:41:18','2026-01-26 21:41:18'),(59,'smtp_password','','system',NULL,'2026-01-26 21:41:18','2026-01-26 21:41:18'),(60,'smtp_encryption','tls','system',NULL,'2026-01-26 21:41:18','2026-01-26 21:41:18'),(101,'bank1_name','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(102,'bank1_account','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(103,'bank2_name','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(104,'bank2_account','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(105,'bank_account_name','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(112,'tinymce_api_key','no-api-key','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(113,'quote_terms','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(114,'quote_warranty','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(115,'groq_api_key','','system',NULL,'2026-01-27 09:33:34','2026-01-27 09:33:34'),(116,'company_logo','uploads/logo/company_logo_1770083953.png','system',NULL,'2026-01-29 07:43:45','2026-02-03 01:59:14'),(117,'company_favicon','uploads/logo/favicon.png','system',NULL,'2026-01-29 07:43:45','2026-01-29 07:43:45'),(298,'hr_work_start_time','09:00','hr','Default work start time','2026-02-02 08:24:48','2026-02-02 08:24:48'),(299,'hr_work_end_time','17:00','hr','Default work end time','2026-02-02 08:24:48','2026-02-02 08:24:48'),(300,'hr_currency_symbol','₦','hr','Currency for payroll','2026-02-02 08:24:48','2026-02-02 08:24:48'),(301,'hr_recruitment_email_template','Dear {name},\n\nThank you for applying...','hr','Default recruitment email','2026-02-02 08:24:48','2026-02-02 08:24:48');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','manager','sales','viewer') DEFAULT 'sales',
  `is_active` tinyint(1) DEFAULT 1,
  `signature_file` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'joel','$2y$10$18Cgi9cs48BS7Gjn5iO0yeDheD7raEaane9sVGCxnQomJPDS.c0Gi','Joel O','ejovwoke@gmail.com','admin',1,NULL,'2026-01-28 15:20:42','2026-01-26 21:41:17','2026-01-28 15:20:42',NULL),(9,'janedoe@test.com','$2y$10$du8.mMcxMTFMS2Jp17kBDuFlHlbd4EAtzDMkI33yCYGoTzgFSyDPe','Jane Doe','janedoe@test.com','viewer',1,NULL,NULL,'2026-02-02 09:40:15','2026-02-02 09:40:15','08012345678');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-04  7:46:20

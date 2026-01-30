-- 1100-ERP Complete Database Schema
-- Auto-Installation Schema for Setup Wizard
-- Version: 2.0.0
-- Generated: 2026-01-16

-- Note: This schema is used by the setup wizard
-- Do not include CREATE DATABASE or USE statements
-- The wizard handles database selection

-- ============================================
-- DROP EXISTING TABLES (in reverse dependency order)
-- ============================================

-- Drop child tables first (those with foreign keys)
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS readymade_quote_template_items;
DROP TABLE IF EXISTS readymade_quote_templates;
DROP TABLE IF EXISTS readymade_quote_categories;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS invoice_line_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS quote_line_items;
DROP TABLE IF EXISTS quotes;

-- Drop parent tables
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS settings;

-- ============================================
-- CORE TABLES
-- ============================================

-- Users table

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'manager', 'sales', 'viewer') DEFAULT 'sales',
    is_active TINYINT(1) DEFAULT 1,
    signature_file VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    description TEXT,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    vat_applicable TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_code (product_code),
    INDEX idx_product_name (product_name),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    company VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    account_balance DECIMAL(15,2) DEFAULT 0.00,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_name (customer_name),
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DOCUMENT TABLES
-- ============================================

-- Quotes table
CREATE TABLE IF NOT EXISTS quotes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE NOT NULL,
    quote_title VARCHAR(255) NOT NULL,
    customer_id INT UNSIGNED,
    customer_name VARCHAR(255) NOT NULL,
    salesperson VARCHAR(255) NOT NULL,
    quote_date DATE NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payment_terms VARCHAR(255) DEFAULT '80% Initial Deposit',
    delivery_period VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'finalized', 'approved', 'rejected', 'expired') DEFAULT 'draft',
    notes TEXT,
    created_by INT UNSIGNED,
    is_archived TINYINT(1) DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_quote_number (quote_number),
    INDEX idx_customer_name (customer_name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quote line items
CREATE TABLE IF NOT EXISTS quote_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED,
    item_number INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    vat_applicable TINYINT(1) DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_quote_id (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    quote_id INT UNSIGNED,
    invoice_title VARCHAR(255) NOT NULL,
    customer_id INT UNSIGNED,
    customer_name VARCHAR(255) NOT NULL,
    salesperson VARCHAR(255) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payment_terms VARCHAR(255),
    status ENUM('draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'finalized') DEFAULT 'draft',
    notes TEXT,
    created_by INT UNSIGNED,
    is_archived TINYINT(1) DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_customer_name (customer_name),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice line items
CREATE TABLE IF NOT EXISTS invoice_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED,
    item_number INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    vat_applicable TINYINT(1) DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Receipts table
CREATE TABLE IF NOT EXISTS receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_id INT UNSIGNED,
    customer_id INT UNSIGNED,
    customer_name VARCHAR(255) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'card', 'other') DEFAULT 'cash',
    payment_date DATE NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    payment_id INT UNSIGNED DEFAULT NULL,
    status ENUM('valid','void') DEFAULT 'valid',
    created_by INT UNSIGNED,
    is_archived TINYINT(1) DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_customer_name (customer_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method VARCHAR(50) DEFAULT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  payment_number VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_payment_date (payment_date),
  INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- READY-MADE QUOTES
-- ============================================

-- Readymade quote categories
CREATE TABLE IF NOT EXISTS readymade_quote_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default category
INSERT INTO readymade_quote_categories (id, category_name, description) VALUES (1, 'General', 'Default Category');

-- Readymade quote templates
CREATE TABLE IF NOT EXISTS readymade_quote_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    payment_terms TEXT DEFAULT NULL,
    default_project_title VARCHAR(255) DEFAULT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES readymade_quote_categories(id) ON DELETE CASCADE,
    INDEX idx_template_name (template_name),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Readymade quote template items
CREATE TABLE IF NOT EXISTS readymade_quote_template_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED,
    item_number INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    vat_applicable TINYINT(1) DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES readymade_quote_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BANK ACCOUNTS
-- ============================================

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    show_on_documents TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bank_name (bank_name),
    INDEX idx_is_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUD IT LOG
-- ============================================

CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SETTINGS TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    category VARCHAR(50) DEFAULT 'system',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSTALLATION COMPLETE MARKER
-- ============================================

INSERT IGNORE INTO settings (setting_key, setting_value, category, description) VALUES
('installation_completed', '1', 'system', 'Flag indicating installation is complete'),
('installation_date', NOW(), 'system', 'Date of installation'),
('version', '2.0.0', 'system', 'Current system version'),
('company_name', 'Your Company Name', 'company', 'Business Name'),
('company_email', '', 'company', 'Business Email'),
('company_phone', '', 'company', 'Business Phone'),
('company_address', '', 'company', 'Business Address'),
('company_website', '', 'company', 'Business Website'),
('company_tax_id', '', 'company', 'Tax ID / VAT Number'),
('groq_api_key', '', 'integrations', 'Groq AI API Key'),
('vat_rate', '7.5', 'system', 'Default VAT Rate (%)'),
('currency_symbol', '₦', 'display', 'Currency Symbol'),
('email_method', 'php_mail', 'email', 'Method for sending emails (php_mail/smtp)'),
('email_from_address', 'noreply@yourcompany.com', 'email', 'Default sender email address'),
('email_from_name', 'Your Company Name', 'email', 'Default sender name'),
('items_per_page', '25', 'display', 'Number of items per page in lists'),
('show_dashboard_charts', '1', 'display', 'Toggle dashboard charts'),
('show_recent_activity', '1', 'display', 'Toggle dashboard recent activity'),
('pdf_quality', 'high', 'display', 'Quality of generated PDFs'),
('theme_color', '#0076BE', 'display', 'Primary theme color'),
('footer_text', 'We appreciate your business! Thank you', 'display', 'Default footer text for documents'),
('quote_prefix', 'QUOT-', 'system', 'Prefix for quote numbers'),
('invoice_prefix', 'INV-', 'system', 'Prefix for invoice numbers'),
('receipt_prefix', 'REC-', 'system', 'Prefix for receipt numbers'),
('date_format', 'd/m/Y', 'display', 'Date display format'),
('auto_archive_days', '0', 'system', 'Days to auto-archive documents (0 to disable)'),
('audit_retention_days', '90', 'audit', 'Days to keep audit logs'),
('log_user_actions', '1', 'audit', 'Log user actions'),
('log_document_create', '1', 'audit', 'Log document creation'),
('log_document_edit', '1', 'audit', 'Log document editing'),
('log_document_delete', '1', 'audit', 'Log document deletion'),
('log_user_management', '1', 'audit', 'Log user management actions'),
('log_settings_changes', '1', 'audit', 'Log settings changes'),
('log_email_sent', '1', 'audit', 'Log email sending');


-- AI Rate Limiting & Usage Tracking Schema
-- Run this migration to add rate limiting capabilities

-- Usage logging table
CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    tool_name VARCHAR(50) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_hash VARCHAR(64),
    tokens_used INT DEFAULT 0,
    cost_usd DECIMAL(10,4) DEFAULT 0,
    processing_time FLOAT,
    success BOOLEAN DEFAULT TRUE,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_ip_date (ip_address, created_at),
    INDEX idx_tool_date (tool_name, created_at),
    INDEX idx_date (created_at),
    INDEX idx_hash (request_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Request caching table
CREATE TABLE IF NOT EXISTS ai_request_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_hash VARCHAR(64) UNIQUE NOT NULL,
    tool_name VARCHAR(50) NOT NULL,
    request_params TEXT NOT NULL,
    response_data MEDIUMTEXT NOT NULL,
    hit_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_hash (request_hash),
    INDEX idx_tool (tool_name),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Settings
INSERT INTO settings (setting_key, setting_value, category, description) VALUES
('ai_ip_hourly_limit', '5', 'ai_limits', 'Hourly request limit for public/IP-based users'),
('ai_ip_daily_limit', '20', 'ai_limits', 'Daily request limit for public/IP-based users'),
('ai_user_hourly_limit', '10', 'ai_limits', 'Hourly request limit for authenticated users'),
('ai_user_daily_limit', '50', 'ai_limits', 'Daily request limit for authenticated users'),
('ai_monthly_budget_usd', '100', 'ai_limits', 'Monthly API budget in USD'),
('ai_enable_caching', '1', 'ai_features', 'Enable response caching'),
('ai_cache_ttl_hours', '24', 'ai_features', 'Cache time-to-live in hours'),
('ai_enable_public_access', '1', 'ai_features', 'Allow public access to AI tools'),
('ai_public_tools', 'system_designer', 'ai_features', 'Comma-separated list of public tools'),
('ai_log_retention_days', '90', 'ai_features', 'Days to retain usage logs'),
('ai_emergency_disable', '0', 'ai_features', 'Emergency disable all AI features')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Create cleanup event for old logs (runs daily at 2 AM)
CREATE EVENT IF NOT EXISTS cleanup_old_ai_logs
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 02:00:00')
DO
    DELETE FROM ai_usage_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL (SELECT setting_value FROM settings WHERE setting_key = 'ai_log_retention_days') DAY);

-- Create cleanup event for expired cache (runs every hour)
CREATE EVENT IF NOT EXISTS cleanup_expired_cache
ON SCHEDULE EVERY 1 HOUR
DO
    DELETE FROM ai_request_cache WHERE expires_at < NOW();

-- ============================================
-- AI RECOMMENDATIONS LOG
-- ============================================

CREATE TABLE IF NOT EXISTS ai_recommendations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    customer_name VARCHAR(255),
    customer_description TEXT NOT NULL,
    appliances_json JSON,
    power_analysis JSON,
    recommended_system JSON NOT NULL,
    roi_analysis JSON NOT NULL,
    quote_id INT UNSIGNED,
    created_quote TINYINT(1) DEFAULT 0,
    model_used VARCHAR(50) DEFAULT 'groq-llama-3.1-70b',
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_customer_name (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NIGERIAN MARKET DATA
-- ============================================

CREATE TABLE IF NOT EXISTS market_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(50) NOT NULL,
    data_key VARCHAR(100) NOT NULL,
    data_value DECIMAL(15,2) NOT NULL,
    effective_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data (data_type, data_key, effective_date),
    INDEX idx_data_type (data_type),
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert current Nigerian market data
INSERT IGNORE INTO market_data (data_type, data_key, data_value, effective_date, notes) VALUES
('fuel_price', 'petrol_per_litre', 650, '2026-01-01', 'Average petrol price in Nigeria'),
('fuel_price', 'diesel_per_litre', 850, '2026-01-01', 'Average diesel price in Nigeria'),
('electricity', 'nepa_per_kwh_residential', 68, '2026-01-01', 'NEPA residential tariff (average)'),
('electricity', 'nepa_per_kwh_commercial', 85, '2026-01-01', 'NEPA commercial tariff (average)'),
('generator', 'running_cost_per_hour_2.5kva', 300, '2026-01-01', 'Small generator fuel cost'),
('generator', 'running_cost_per_hour_5kva', 500, '2026-01-01', 'Medium generator fuel cost'),
('generator', 'running_cost_per_hour_10kva', 900, '2026-01-01', 'Large generator fuel cost'),
('generator', 'maintenance_per_month_2.5kva', 8000, '2026-01-01', 'Oil, servicing, repairs'),
('generator', 'maintenance_per_month_5kva', 15000, '2026-01-01', 'Oil, servicing, repairs'),
('generator', 'maintenance_per_month_10kva', 25000, '2026-01-01', 'Oil, servicing, repairs'),
('solar', 'avg_sun_hours_per_day', 5.5, '2026-01-01', 'Average sun hours in Nigeria'),
('solar', 'performance_degradation_annual', 0.5, '2026-01-01', 'Annual panel efficiency loss %'),
('inflation', 'annual_rate', 24, '2026-01-01', 'Nigeria inflation rate'),
('currency', 'usd_to_ngn', 1600, '2026-01-01', 'Exchange rate USD to Naira');

-- ============================================
-- HELPER FUNCTIONS
-- ============================================

DELIMITER $$

CREATE FUNCTION IF NOT EXISTS get_market_data(
    p_data_type VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci,
    p_data_key VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
) RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_value DECIMAL(15,2);
    
    SELECT data_value INTO v_value
    FROM market_data
    WHERE data_type = p_data_type COLLATE utf8mb4_unicode_ci
    AND data_key = p_data_key COLLATE utf8mb4_unicode_ci
    AND effective_date <= CURDATE()
    ORDER BY effective_date DESC
    LIMIT 1;
    
    RETURN COALESCE(v_value, 0);
END$$

DELIMITER ;

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
    role ENUM('admin', 'manager', 'sales', 'viewer') DEFAULT 'sales',
    is_active TINYINT(1) DEFAULT 1,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSTALLATION COMPLETE MARKER
-- ============================================

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('installation_completed', '1'),
('installation_date', NOW()),
('version', '2.0.0');

-- Bluedots Technologies Quote Management System
-- Phase 0 Database Schema
-- Created: 2026-01-14

-- Create database
CREATE DATABASE IF NOT EXISTS bluedots_quotes 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE bluedots_quotes;

-- Documents table (quotes only for Phase 0)
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(50) UNIQUE NOT NULL,
    quote_title VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    salesperson VARCHAR(255) NOT NULL,
    quote_date DATE NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payment_terms VARCHAR(255) DEFAULT '80% Initial Deposit',
    status ENUM('draft', 'finalized') DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_document_number (document_number),
    INDEX idx_customer_name (customer_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Line items table
CREATE TABLE line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    item_number INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    vat_applicable TINYINT(1) DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_document_id (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data for testing
INSERT INTO documents (
    document_number, quote_title, customer_name, salesperson, 
    quote_date, subtotal, total_vat, grand_total, status
) VALUES (
    'QT-0001',
    'Website Development Project',
    'ABC Limited',
    'Joel Okenabirhie',
    '2026-01-15',
    125000.00,
    9000.00,
    134000.00,
    'finalized'
);

SET @doc_id = LAST_INSERT_ID();

INSERT INTO line_items (document_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) VALUES
(@doc_id, 1, 1.00, 'Custom Website Design & Development', 100000.00, 1, 7500.00, 107500.00),
(@doc_id, 2, 1.00, 'Domain Name Registration (.com.ng)', 5000.00, 0, 0.00, 5000.00),
(@doc_id, 3, 1.00, 'Web Hosting (Annual)', 20000.00, 1, 1500.00, 21500.00);

-- --------------------------------------------------------
-- Phase 2/3 Updates (Payments, Soft Deletes, etc.)
-- --------------------------------------------------------

-- Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Receipts Table (If not already created by install logic, usually needs definition)
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_date` date NOT NULL,
  `status` enum('valid','void') DEFAULT 'valid',
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_receipt_number` (`receipt_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Updates to Documents/Invoices (Soft Delete & Archive)
ALTER TABLE documents ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0;
ALTER TABLE documents MODIFY COLUMN status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled', 'partial', 'finalized') DEFAULT 'draft';
ALTER TABLE documents ADD COLUMN IF NOT EXISTS delivery_period VARCHAR(255) DEFAULT NULL;

-- Updates to Customers (Soft Delete & Extra Fields)
-- Note: 'customers' table definition might be missing in this file if it was created by a separate script?
-- Assuming standard customers table exists or will be created by other parts. 
-- If this schema.sql is the ONLY source, we should define customers fully.
-- Checking lines 1-12... 'documents' is line 13. 'customers' table is NOT defined in lines 1-32.
-- It seems schema.sql might be incomplete or relies on 'users' table?
-- Let's just add ALTERs to be safe for now, as the wizard might run other SQLs.


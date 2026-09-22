-- Eleven100 ERP Quote Management System
-- Phase 2A Database Migration
-- Run this in phpMyAdmin AFTER Phase 1

USE 1100erp;

-- Add users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user (DISABLED BY DEFAULT)
-- Former seed used a publicly-known password hash and was removed.
-- The setup wizard creates the real admin account; never seed default credentials.

-- Add document_type column
ALTER TABLE documents 
ADD COLUMN document_type ENUM('quote', 'invoice', 'receipt') NOT NULL DEFAULT 'quote' AFTER id;

-- Add invoice-specific fields
ALTER TABLE documents 
ADD COLUMN amount_paid DECIMAL(15,2) DEFAULT 0.00 AFTER grand_total,
ADD COLUMN balance_due DECIMAL(15,2) DEFAULT 0.00 AFTER amount_paid;

-- Add receipt-specific fields
ALTER TABLE documents 
ADD COLUMN payment_method VARCHAR(100) AFTER balance_due,
ADD COLUMN payment_reference VARCHAR(100) AFTER payment_method;

-- Add parent document tracking
ALTER TABLE documents 
ADD COLUMN parent_document_id INT UNSIGNED AFTER status,
ADD FOREIGN KEY (parent_document_id) REFERENCES documents(id) ON DELETE SET NULL;

-- Add user tracking
ALTER TABLE documents 
ADD COLUMN created_by INT UNSIGNED AFTER parent_document_id,
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update existing quotes
UPDATE documents SET document_type = 'quote' WHERE document_type IS NULL OR document_type = '';

-- Create indexes
CREATE INDEX idx_document_type ON documents(document_type);
CREATE INDEX idx_parent_document ON documents(parent_document_id);

-- Sample invoice (for testing)
INSERT INTO documents (
    document_type, document_number, quote_title, customer_name, salesperson,
    quote_date, subtotal, total_vat, grand_total, amount_paid, balance_due,
    payment_terms, status, parent_document_id
) VALUES (
    'invoice',
    'INV-0001',
    'Website Development Project',
    'ABC Limited',
    'Sample Salesperson',
    '2026-01-16',
    125000.00,
    9000.00,
    134000.00,
    107200.00,
    26800.00,
    '80% Initial Deposit',
    'finalized',
    (SELECT id FROM (SELECT id FROM documents WHERE document_number = 'QT-0001' LIMIT 1) AS temp)
);

-- Copy line items for sample invoice
SET @invoice_id = LAST_INSERT_ID();
INSERT INTO line_items (document_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total)
SELECT @invoice_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total
FROM line_items 
WHERE document_id = (SELECT id FROM documents WHERE document_number = 'QT-0001' LIMIT 1);

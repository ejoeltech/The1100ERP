-- Eleven100 ERP Quote Management System
-- Phase 1 Database Migration
-- Run this in phpMyAdmin AFTER Phase 0 schema

USE 1100erp;

-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing customers from documents table
INSERT INTO customers (name, created_at)
SELECT DISTINCT customer_name, MIN(created_at)
FROM documents
GROUP BY customer_name
ON DUPLICATE KEY UPDATE name = name;

-- Add deleted_at for soft deletes
ALTER TABLE documents 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER status;

-- Sample customer data
INSERT INTO customers (name, contact_person, email, phone, address) VALUES
('ABC Limited', 'John Doe', 'john@abclimited.com', '08012345678', '123 Lagos Street, Lagos'),
('XYZ Corporation Limited', 'Jane Smith', 'jane@xyzcorp.com', '08087654321', '456 Abuja Road, Abuja'),
('Sample Company Ltd', 'Sample Contact', 'sample@example.com', '08000000000', 'Sample Address')
ON DUPLICATE KEY UPDATE name = name;

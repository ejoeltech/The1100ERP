-- Products & Customers Enhancement Migration
-- Creates products table and enhances customers table

USE 1100erp;

-- ============================================
-- 1. CREATE PRODUCTS TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (product_code),
    INDEX idx_name (product_name),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Product catalog';

-- ============================================
-- 2. INSERT SAMPLE PRODUCTS
-- ============================================

INSERT IGNORE INTO products (product_code, product_name, description, unit_price, category, is_active) VALUES
('WEB-001', 'Website Design & Development', 'Complete website design and development with responsive layout', 250000.00, 'Website', 1),
('WEB-002', 'E-commerce Website', 'Full-featured e-commerce platform with payment integration', 500000.00, 'Website', 1),
('WEB-003', 'Website Maintenance (Monthly)', 'Monthly website maintenance and updates', 25000.00, 'Website', 1),
('SOFT-001', 'Custom Software Development', 'Bespoke software solution development', 750000.00, 'Software', 1),
('SOFT-002', 'Mobile App Development (Android)', 'Native Android application development', 400000.00, 'Software', 1),
('SOFT-003', 'Mobile App Development (iOS)', 'Native iOS application development', 450000.00, 'Software', 1),
('SOFT-004', 'Cross-Platform Mobile App', 'Flutter/React Native mobile app', 600000.00, 'Software', 1),
('IT-001', 'Network Setup & Configuration', 'Complete network infrastructure setup', 150000.00, 'IT Services', 1),
('IT-002', 'Server Installation & Setup', 'Server hardware/software installation', 100000.00, 'IT Services', 1),
('IT-003', 'IT Support (Monthly)', 'Monthly IT support and maintenance', 50000.00, 'IT Services', 1),
('TRAIN-001', 'Software Training (Per Day)', 'Professional software training session', 75000.00, 'Training', 1),
('CONS-001', 'IT Consultation (Per Hour)', 'Expert IT consultation services', 15000.00, 'Consultation', 1),
('HOST-001', 'Web Hosting (Yearly)', 'Annual web hosting package', 30000.00, 'Hosting', 1),
('DOMAIN-001', 'Domain Registration (.com)', 'Domain name registration for one year', 5000.00, 'Domain', 1),
('SSL-001', 'SSL Certificate (Yearly)', 'Standard SSL certificate for one year', 12000.00, 'Security', 1);

-- ============================================
-- 3. ENHANCE CUSTOMERS TABLE (IF NEEDED)
-- ============================================

-- Add missing columns if they don't exist
-- Note: customers table has 'name' not 'customer_name'
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS company VARCHAR(255) AFTER name,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email,
    ADD COLUMN IF NOT EXISTS address TEXT AFTER phone,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER address,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER city,
    ADD COLUMN IF NOT EXISTS notes TEXT AFTER is_active,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add indexes for better performance  
CREATE INDEX IF NOT EXISTS idx_name ON customers(name);
CREATE INDEX IF NOT EXISTS idx_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_active ON customers(is_active);

-- ============================================
-- MIGRATION COMPLETE
-- ============================================

SELECT 'Products & Customers Migration Complete!' AS status;

SELECT 'Products Created' AS info, COUNT(*) AS count FROM products;

SELECT 'Products by Category' AS info, category, COUNT(*) AS count 
FROM products 
GROUP BY category 
ORDER BY count DESC;

SELECT 'Customers Total' AS info, COUNT(*) AS count FROM customers;

-- Payment Module Schema Updates

-- 1. Create payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_date (payment_date)
);

-- 2. Add account_balance to customers if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = 'account_balance');
SET @sql := IF(@exist > 0, 'SELECT "Column account_balance already exists"', 'ALTER TABLE customers ADD COLUMN account_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER email');
PREPARE stmt FROM @sql;
EXECUTE stmt;

-- 3. Add payment_id to receipts if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'receipts' AND column_name = 'payment_id');
SET @sql := IF(@exist > 0, 'SELECT "Column payment_id already exists"', 'ALTER TABLE receipts ADD COLUMN payment_id INT NULL AFTER invoice_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;

-- 4. Add index for payment_id in receipts
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'receipts' AND index_name = 'idx_payment');
SET @sql := IF(@exist > 0, 'SELECT "Index idx_payment already exists"', 'CREATE INDEX idx_payment ON receipts(payment_id)');
PREPARE stmt FROM @sql;
EXECUTE stmt;

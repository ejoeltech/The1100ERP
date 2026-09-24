-- ============================================
-- Dynamic Bank Accounts Table
-- ============================================

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    show_on_documents TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_display (show_on_documents, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Migrate existing bank accounts from settings
-- ============================================

-- Insert Bank 1 (if exists)
INSERT INTO bank_accounts (bank_name, account_number, account_name, show_on_documents, display_order)
SELECT 
    (SELECT setting_value FROM settings WHERE setting_key = 'bank1_name'),
    (SELECT setting_value FROM settings WHERE setting_key = 'bank1_account'),
    (SELECT setting_value FROM settings WHERE setting_key = 'bank_account_name'),
    1,
    1
FROM settings 
WHERE setting_key = 'bank1_name' 
  AND setting_value IS NOT NULL 
  AND setting_value != ''
  AND NOT EXISTS (
      SELECT 1 FROM bank_accounts WHERE bank_name = (SELECT setting_value FROM settings WHERE setting_key = 'bank1_name')
  )
LIMIT 1;

-- Insert Bank 2 (if exists)
INSERT INTO bank_accounts (bank_name, account_number, account_name, show_on_documents, display_order)
SELECT 
    (SELECT setting_value FROM settings WHERE setting_key = 'bank2_name'),
    (SELECT setting_value FROM settings WHERE setting_key = 'bank2_account'),
    (SELECT setting_value FROM settings WHERE setting_key = 'bank_account_name'),
    1,
    2
FROM settings 
WHERE setting_key = 'bank2_name' 
  AND setting_value IS NOT NULL 
  AND setting_value != ''
  AND NOT EXISTS (
      SELECT 1 FROM bank_accounts WHERE bank_name = (SELECT setting_value FROM settings WHERE setting_key = 'bank2 _name')
  )
LIMIT 1;

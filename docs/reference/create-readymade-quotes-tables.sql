-- Database schema for Readymade Quotes feature

-- Table for quote templates (readymade quotes)
CREATE TABLE IF NOT EXISTS quote_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    template_description TEXT,
    payment_terms TEXT DEFAULT 'Payment due within 30 days',
    estimated_total DECIMAL(15, 2) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for template line items
CREATE TABLE IF NOT EXISTS quote_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    item_number INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1,
    description TEXT NOT NULL,
    unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0,
    vat_applicable BOOLEAN DEFAULT FALSE,
    vat_amount DECIMAL(15, 2) DEFAULT 0,
    line_total DECIMAL(15, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES quote_templates(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

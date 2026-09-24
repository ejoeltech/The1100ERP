USE 1100erp;

CREATE TABLE IF NOT EXISTS proposals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    system_specs JSON, -- Stores inputs like inverter, battery size
    content LONGTEXT, -- The generated HTML proposal
    status ENUM('draft', 'converted', 'archived') DEFAULT 'draft',
    converted_quote_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

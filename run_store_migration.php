<?php
require 'c:\xampp\htdocs\1100erp\config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "item_categories table created or exists.\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100),
            category_id INT,
            description TEXT,
            unit VARCHAR(50),
            price DECIMAL(15,2) DEFAULT '0.00',
            cost_price DECIMAL(15,2) DEFAULT '0.00',
            stock_quantity INT DEFAULT 0,
            minimum_stock INT DEFAULT 0,
            status ENUM('active', 'archived') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "items table created or exists.\n";

    // Modifying quote_line_items
    $stmt = $pdo->query("SHOW COLUMNS FROM quote_line_items LIKE 'item_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE quote_line_items ADD COLUMN item_id INT NULL AFTER product_id");
        echo "item_id column added to quote_line_items.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM quote_line_items LIKE 'item_name'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE quote_line_items ADD COLUMN item_name VARCHAR(255) NULL AFTER item_id");
        echo "item_name column added to quote_line_items.\n";
    }

    echo "Migration complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

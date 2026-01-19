<?php
// Script to add missing columns to products table
require_once __DIR__ . '/../config.php';

try {
    echo "Updating products table schema...\n";

    // Add product_code if not exists
    try {
        $pdo->query("SELECT product_code FROM products LIMIT 1");
        echo "Column 'product_code' already exists.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN product_code VARCHAR(50) AFTER id");
        $pdo->exec("CREATE UNIQUE INDEX idx_product_code ON products(product_code)");
        // Generate codes for existing products
        $pdo->exec("UPDATE products SET product_code = CONCAT('PRD-', LPAD(id, 4, '0')) WHERE product_code IS NULL OR product_code = ''");
        $pdo->exec("ALTER TABLE products MODIFY COLUMN product_code VARCHAR(50) NOT NULL");
        echo "Added 'product_code' column.\n";
    }

    // Add category if not exists
    try {
        $pdo->query("SELECT category FROM products LIMIT 1");
        echo "Column 'category' already exists.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT 'General' AFTER product_name");
        $pdo->exec("CREATE INDEX idx_category ON products(category)");
        echo "Added 'category' column.\n";
    }

    echo "Database update completed successfully!";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
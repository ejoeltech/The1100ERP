<?php
require_once __DIR__ . '/../config.php';

try {
    echo "Adding payment_terms column...\n";
    $pdo->exec("ALTER TABLE readymade_quote_templates ADD COLUMN payment_terms TEXT AFTER description");
    echo "Column added successfully.\n";
} catch (PDOException $e) {
    echo "Error (might already exist): " . $e->getMessage() . "\n";
}
?>
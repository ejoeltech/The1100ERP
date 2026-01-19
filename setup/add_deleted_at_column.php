<?php
// Script to add deleted_at column to document tables
require_once __DIR__ . '/../config.php';

try {
    echo "Updating document tables with deleted_at column...\n";

    $tables = ['quotes', 'invoices', 'receipts'];

    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT deleted_at FROM $table LIMIT 1");
            echo "Column 'deleted_at' already exists in $table.\n";
        } catch (PDOException $e) {
            // Add column
            $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER created_by");
            echo "Added 'deleted_at' column to $table.\n";
        }
    }

    echo "Database update completed successfully!";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
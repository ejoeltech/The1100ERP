<?php
// Script to add created_by column to document tables
require_once __DIR__ . '/../config.php';

try {
    echo "Updating document tables schema...\n";

    $tables = ['quotes', 'invoices', 'receipts'];

    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT created_by FROM $table LIMIT 1");
            echo "Column 'created_by' already exists in $table.\n";
        } catch (PDOException $e) {
            // Add column
            $pdo->exec("ALTER TABLE $table ADD COLUMN created_by INT UNSIGNED AFTER notes");
            $pdo->exec("ALTER TABLE $table ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
            echo "Added 'created_by' column to $table.\n";

            // If logged in, maybe backfill? (optional, skipping for now as session might not be active in CLI)
        }
    }

    echo "Database update completed successfully!";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
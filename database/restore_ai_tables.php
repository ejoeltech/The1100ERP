<?php
// Restore AI Tables Script
// Reads ai-rate-limiting.sql and applies it to the database

require_once __DIR__ . '/../config.php';

echo "Starting AI Table Restoration...\n";

try {
    // 1. Read Schema File
    $schemaFile = __DIR__ . '/ai-rate-limiting.sql';
    if (!file_exists($schemaFile)) {
        die("Error: Schema file not found at $schemaFile\n");
    }

    $sql = file_get_contents($schemaFile);
    echo "Reading schema from: $schemaFile\n";

    // 2. Execute Schema
    // Split into statements
    $statements = explode(';', $sql);

    $count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $count++;
            } catch (PDOException $e) {
                // Ignore empty query errors or benign warnings
                echo "Note: " . substr($statement, 0, 50) . "...\n";
                echo "      " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Executed $count statements.\n";
    echo "AI Tables restored successfully.\n";

} catch (Exception $e) {
    die("CRITICAL ERROR: " . $e->getMessage() . "\n");
}
?>
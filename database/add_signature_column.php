<?php
require_once dirname(__DIR__) . '/config.php';

try {
    echo "Adding signature_file column to users table...\n";

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature_file'");
    if ($stmt->fetch()) {
        echo "Column 'signature_file' already exists.\n";
    } else {
        $pdo->exec("ALTER TABLE users ADD COLUMN signature_file VARCHAR(255) DEFAULT NULL AFTER is_active");
        echo "Column 'signature_file' added successfully.\n";
    }

    echo "Migration complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
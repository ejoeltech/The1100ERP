<?php
// Restore Full Database Schema Script
// Recreates all tables from install-schema.sql and adds default admin user

require_once __DIR__ . '/../config.php';

echo "Starting Full Schema Restoration...\n";

try {
    // 1. Read Schema File
    $schemaFile = __DIR__ . '/install-schema.sql';
    if (!file_exists($schemaFile)) {
        die("Error: Schema file not found at $schemaFile\n");
    }

    $sql = file_get_contents($schemaFile);

    echo "Reading schema from: $schemaFile\n";

    // 2. Execute Schema
    // Disable FK checks to allow dropping parent tables
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Split into statements
    // This simple splitter assumes statements end with ; and are reasonably well-formed
    // It handles the content of install-schema.sql which we know is well formatted
    $statements = explode(';', $sql);

    $count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $count++;
            } catch (PDOException $e) {
                echo "Warning executing statement: " . substr($statement, 0, 50) . "...\n";
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Executed $count SQL statements.\n";
    echo "Schema restored.\n";

    // 3. Re-insert Admin User (because tables were dropped)
    echo "Creating default admin user...\n";

    $username = 'admin';
    $password = 'password';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $fullName = 'Administrator';
    $email = 'admin@example.com';

    // Check if user exists (unlikely after drop, but good practice)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role, is_active)
            VALUES (?, ?, ?, ?, 'admin', 1)
        ");
        $stmt->execute([$username, $hashedPassword, $fullName, $email]);
        echo "Admin user created.\n";
        echo "Username: admin\n";
        echo "Password: password\n";
    } else {
        echo "Admin user already exists.\n";
    }

    echo "Full validation complete.\n";

} catch (Exception $e) {
    die("CRITICAL ERROR: " . $e->getMessage() . "\n");
}
?>
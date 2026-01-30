<?php
// Recreate Users Table Script
// Usage: Run this script via browser or CLI

require_once __DIR__ . '/../config.php';

try {
    echo "Starting users table restoration...\n";

    // 1. Drop if exists
    $pdo->exec("DROP TABLE IF EXISTS users");
    echo "Dropped existing users table (if any).\n";

    // 2. Create table
    $sql = "CREATE TABLE users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        role ENUM('admin', 'manager', 'sales', 'viewer') DEFAULT 'sales',
        is_active TINYINT(1) DEFAULT 1,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Created users table.\n";

    // 3. Insert default admin
    $username = 'admin';
    $password = 'password';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $fullName = 'Administrator';
    $email = 'admin@example.com';

    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, full_name, email, role, is_active)
        VALUES (?, ?, ?, ?, 'admin', 1)
    ");

    $stmt->execute([$username, $hashedPassword, $fullName, $email]);
    echo "Inserted default admin user.\n";
    echo "Username: admin\n";
    echo "Password: password\n";

    echo "Restoration complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
<?php
/**
 * Test Database Installation
 * Tests just the database creation and schema import
 */

// Configuration
$host = 'localhost';
$dbname = '1100erp_test';
$user = 'root';
$password = '';

echo "<h1>Database Installation Test</h1>\n";
echo "<pre>\n";

try {
    // Step 1: Connect to MySQL (without database)
    echo "Step 1: Connecting to MySQL server...\n";
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✓ Connected successfully\n\n";

    // Step 2: Create database
    echo "Step 2: Creating database '$dbname'...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created\n\n";

    // Step 3: Connect to the new database
    echo "Step 3: Connecting to database...\n";
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✓ Connected to database\n\n";

    // Step 4: Import schema
    echo "Step 4: Importing schema...\n";
    $schemaFile = dirname(__DIR__) . '/database/install-schema.sql';

    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $sql = file_get_contents($schemaFile);
    echo "Schema file size: " . strlen($sql) . " bytes\n";

    // Disable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    echo "✓ Disabled foreign key checks\n";

    // Parse and execute SQL
    $lines = explode("\n", $sql);
    $current_statement = '';
    $executed = 0;
    $errors = 0;

    foreach ($lines as $lineNum => $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if (empty($line) || substr($line, 0, 2) == '--' || substr($line, 0, 2) == '/*') {
            continue;
        }

        $current_statement .= ' ' . $line;

        // Execute when statement ends with semicolon
        if (substr(trim($line), -1) == ';') {
            $stmt_to_exec = trim($current_statement);
            if (!empty($stmt_to_exec)) {
                try {
                    $pdo->exec($stmt_to_exec);
                    $executed++;
                } catch (PDOException $e) {
                    $errors++;
                    echo "  ✗ Error on line $lineNum: " . $e->getMessage() . "\n";
                }
            }
            $current_statement = '';
        }
    }

    echo "✓ Executed $executed SQL statements\n";
    if ($errors > 0) {
        echo "⚠ $errors errors occurred\n";
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "✓ Re-enabled foreign key checks\n\n";

    // Step 5: Verify tables were created
    echo "Step 5: Verifying tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tables created (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "  • $table\n";
    }
    echo "\n";

    // Step 6: Check table structure
    echo "Step 6: Checking key tables...\n";
    $keyTables = ['users', 'customers', 'products', 'quotes', 'invoices'];

    foreach ($keyTables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "  ✓ $table (" . count($columns) . " columns)\n";
    }

    echo "\n<strong style='color: green;'>✓ DATABASE INSTALLATION TEST PASSED!</strong>\n";
    echo "\nYou can now proceed with the setup wizard.\n";
    echo "The test database '$dbname' has been created successfully.\n";

} catch (Exception $e) {
    echo "\n<strong style='color: red;'>✗ TEST FAILED</strong>\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>
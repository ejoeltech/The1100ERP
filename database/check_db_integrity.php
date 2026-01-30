<?php
// Database Integrity Check Script
// Checks if all tables defined in install-schema.sql exist in the database

require_once __DIR__ . '/../config.php';

echo "Starting Database Integrity Check...\n";
echo "Database: " . DB_NAME . "\n";

// 1. Get List of Expected Tables from Schema File
$schemaFile = __DIR__ . '/install-schema.sql';
if (!file_exists($schemaFile)) {
    die("Error: Schema file not found at $schemaFile\n");
}

$schemaContent = file_get_contents($schemaFile);
$expectedTables = [];

// Regex to find CREATE TABLE statements
// Matches: CREATE TABLE [IF NOT EXISTS] table_name
if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $schemaContent, $matches)) {
    $expectedTables = $matches[1];
}

echo "Found " . count($expectedTables) . " tables defined in schema.\n";

// 2. Check Database for Existence
$missingTables = [];
$existingTables = [];

foreach ($expectedTables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            $existingTables[] = $table;
        } else {
            $missingTables[] = $table;
        }
    } catch (PDOException $e) {
        echo "Error checking table '$table': " . $e->getMessage() . "\n";
    }
}

// 3. Report Results
echo "\n--- Results ---\n";
echo "Existing Tables: " . count($existingTables) . "\n";
echo "Missing Tables:  " . count($missingTables) . "\n";

if (!empty($missingTables)) {
    echo "\nCRITICAL: The following tables are MISSING:\n";
    foreach ($missingTables as $table) {
        echo " - $table\n";
    }
    echo "\nYou should run the schema installation or specific restoration scripts for these tables.\n";
} else {
    echo "\nSUCCESS: All expected tables exist in the database.\n";
}

// 4. Basic Data Checks (Optional)
echo "\n--- Data Sanity Checks ---\n";

// Check Admin User
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' OR username='admin'");
    $adminCount = $stmt->fetchColumn();
    echo "Admin Users found: $adminCount\n";
    if ($adminCount == 0) {
        echo "WARNING: No admin user found. You may not be able to log in.\n";
    }
} catch (PDOException $e) {
    echo "Error checking users: " . $e->getMessage() . "\n";
}

?>
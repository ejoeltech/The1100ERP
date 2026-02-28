<?php
// HR Module Installer
require_once __DIR__ . '/../../config.php';

echo "Installing HR Module Schema...\n";

$sqlFile = __DIR__ . '/hr_schema.sql';
if (!file_exists($sqlFile)) {
    die("Error: Schema file not found at $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "HR Module tables created successfully.\n";
} catch (PDOException $e) {
    die("Error executing schema: " . $e->getMessage() . "\n");
}

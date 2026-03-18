<?php
require_once 'config.php';

echo "Checking for orphaned HR employees...\n";

// Check for employees without a valid user_id
$sql = "SELECT e.id, e.full_name, e.employee_code, e.user_id 
        FROM hr_employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE u.id IS NULL";

$orphans = $pdo->query($sql)->fetchAll();

if (count($orphans) > 0) {
    echo "Found " . count($orphans) . " orphaned employees (no valid user record):\n";
    foreach ($orphans as $emp) {
        echo "- ID: {$emp['id']}, Name: {$emp['full_name']}, Code: {$emp['employee_code']}, UserID: {$emp['user_id']}\n";
    }
} else {
    echo "No orphaned employees found. All employees have valid user records.\n";
}

echo "\nChecking total counts:\n";
echo "Total HR Employees: " . $pdo->query("SELECT count(*) FROM hr_employees")->fetchColumn() . "\n";
echo "Total Users: " . $pdo->query("SELECT count(*) FROM users")->fetchColumn() . "\n";
?>
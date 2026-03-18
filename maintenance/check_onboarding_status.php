<?php
require_once 'config.php';

// Check submitted onboarding entries
$entries = $pdo->query("SELECT * FROM hr_onboarding_entries WHERE status = 'submitted'")->fetchAll();

echo "Found " . count($entries) . " submitted entries waiting for import.\n";

if (count($entries) > 0) {
    foreach ($entries as $e) {
        echo "- Entry ID: {$e['id']}, Name: {$e['full_name']}, Status: {$e['status']}\n";
    }
}
?>
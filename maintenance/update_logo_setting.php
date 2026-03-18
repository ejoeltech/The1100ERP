<?php
require_once 'config.php';

try {
    $logoPath = 'uploads/logo/company_logo_1770280404.jpg'; // Using the latest one found

    // Check if file exists relative to where this script runs (root)
    if (!file_exists($logoPath)) {
        die("Error: Logo file '$logoPath' not found.");
    }

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('company_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$logoPath, $logoPath]);

    echo "Success: Updated company_logo setting to '$logoPath'.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
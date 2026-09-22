<?php
// One-time install-time schema check. Deleted by cleanup.php after setup.
// Permanent admin equivalent: pages/system-update.php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/schema-patcher.php';

$entries = SchemaPatcher::run($pdo, dirname(__DIR__, 2));

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Schema Update</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .box { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>1100ERP Database Schema Patcher</h1>
<p>This script will update your database structure to match the latest codebase version.</p>";

$class = ['ok' => 'success', 'info' => 'info', 'error' => 'error'];
$icons = ['ok' => '✓', 'info' => 'ℹ️', 'error' => '✗'];
foreach ($entries as $e) {
    $c = $class[$e['status']] ?? 'info';
    $i = $icons[$e['status']] ?? 'ℹ️';
    echo "<div class='$c'>$i " . htmlspecialchars($e['message']) . "</div>";
}

echo "<div class='box' style='background: #fff3cd; border: 1px solid #ffeeba;'>";
echo "<h3 style='margin-top:0; color: #856404;'>Security Cleanup Recommended</h3>";
echo "<p>For security reasons, please <strong>DELETE</strong> the entire one-time installer folder from your server:</p>";
echo "<ul style='background: #fff; padding: 15px 30px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;'>";
echo "<li style='color:red; font-weight:bold;'>maintenance/ (The entire folder - wizard, tools/, factory-reset, this file)</li>";
echo "</ul>";
echo "<p>After deletion, use <strong>System Update</strong> (admin Settings) for future schema patches.</p>";
echo "<strong><a href='../../dashboard.php' style='display:inline-block; padding:10px 20px; background:#0076BE; color:white; text-decoration:none; border-radius:5px;'>Go to Dashboard</a></strong>";
echo "</div>";
echo "</body></html>";

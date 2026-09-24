<?php
// Run the Leads module schema + seed settings.
// Usage (CLI or browser, admin only):  php database/run-leads-migration.php
// Mirrors the repo's other migration runners (e.g. add_signature_column.php).
require_once dirname(__DIR__) . '/config.php';

try {
    echo "Creating leads table...\n";
    $pdo->exec(file_get_contents(__DIR__ . '/leads-schema.sql'));
    echo "Leads schema + settings seed applied.\n";
    echo "Migration complete.\n";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}

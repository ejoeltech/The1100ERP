<?php
// HR Module Installer - idempotent, runs base + all incremental updates
require_once __DIR__ . '/../../config.php';

echo "Installing HR Module Schema...\n";

$files = array_merge(
    [__DIR__ . '/hr_schema.sql'],
    glob(__DIR__ . '/update_schema_v*.sql') ?: []
);
natcasesort($files);

foreach ($files as $sqlFile) {
    if (!file_exists($sqlFile)) continue;
    $label = basename($sqlFile);
    echo "Applying $label ...\n";
    $sql = file_get_contents($sqlFile);
    // Strip single-line -- comments and block /* */ to allow mid-statement comments
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($stmts as $stmt) {
        if ($stmt === '' || stripos($stmt, 'SET FOREIGN_KEY_CHECKS') === 0) {
            try { $pdo->exec($stmt); } catch (PDOException $e) {}
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Idempotent: ignore duplicate column/table/key errors on re-run
            if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate entry') !== false) {
                continue;
            }
            // Still log unexpected errors but don't abort whole install
            echo "  Warning $label: $msg\n";
        }
    }
    echo "  Done $label\n";
}
echo "HR Module tables created/updated successfully.\n";

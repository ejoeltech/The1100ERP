<?php
// HR Module Installer - idempotent, runs base + all incremental updates.
// WP0-E: CLI-only. Anyone opening this over HTTP gets nothing (the schema it
// applies is sensitive and the runner must never be web-triggered).
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden: run via CLI as the deploy user: php modules/hr/install.php');
}
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

// Audit trail (WP0-E): record who ran the installer and when.
try {
    $runUser = get_current_user() . '@' . php_uname('n');
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, resource_type, resource_id, ip_address, user_agent, details) VALUES (NULL, 'hr_schema_install', 'system', NULL, 'cli', ?, ?)");
    $stmt->execute([$runUser, json_encode(['files' => array_map('basename', $files)])]);
    echo "Audit entry written.\n";
} catch (Exception $e) {
    // audit_log may not exist on a bare database; schema files create it.
    echo "Audit skipped: " . $e->getMessage() . "\n";
}

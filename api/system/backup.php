<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';

requirePermission('manage_settings');

// Check backup type (strict allow-list; anything else is rejected)
$type = $_GET['type'] ?? 'db'; // 'db' or 'full'
if (!in_array($type, ['db', 'full'], true)) {
    http_response_code(400);
    die('Invalid backup type.');
}

// Scoped backup: comma-separated group list, or 'whole' (default).
// Groups mirror the selective-migration categories so backup and
// restore/import stay consistent. Only these keys are accepted; table
// names always come from this internal map, never from user input.
$scopeMap = [
    'settings' => ['settings', 'bank_accounts'],
    'users' => ['users'],
    'customers' => ['customers'],
    'inventory' => ['products'],
    'financials' => ['quotes', 'quote_line_items', 'invoices', 'invoice_line_items', 'receipts', 'payments'],
    'hr' => ['hr_employees', 'hr_departments', 'hr_designations'],
];
$scopeRaw = strtolower(trim((string)($_GET['scope'] ?? 'whole')));
$scopeTables = [];
$scopeLabel = 'whole';
if ($scopeRaw !== '' && $scopeRaw !== 'whole') {
    $asked = array_unique(array_filter(array_map('trim', explode(',', $scopeRaw))));
    foreach ($asked as $g) {
        if (!isset($scopeMap[$g])) {
            http_response_code(400);
            die('Invalid backup scope.');
        }
        $scopeTables = array_merge($scopeTables, $scopeMap[$g]);
    }
    $scopeTables = array_values(array_unique($scopeTables));
    sort($scopeTables);
    $scopeLabel = implode('-', $asked);
}

// Detect OS for command paths
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$dumpCommand = 'mysqldump';
if ($isWindows) {
    $xamppMySQL = 'c:/xampp/mysql/bin/mysqldump.exe';
    if (file_exists($xamppMySQL)) {
        $dumpCommand = '"' . $xamppMySQL . '"';
    }
}

// 1. Generate SQL Dump
$sqlFilename = 'backup_' . DB_NAME . '_' . $scopeLabel . '_' . date('Y-m-d_H-i-s') . '.sql';
$sqlPath = sys_get_temp_dir() . '/' . $sqlFilename;

// Scoped dumps list explicit tables (from the internal map above).
$tableArgs = '';
foreach ($scopeTables as $t) {
    $tableArgs .= ' ' . escapeshellarg($t);
}
$command = sprintf(
    '%s --host=%s --user=%s --password=%s --routines %s%s > %s',
    $dumpCommand,
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    $tableArgs,
    $isWindows ? '"' . $sqlPath . '"' : escapeshellarg($sqlPath)
);

if ($isWindows) {
    $command = 'cmd /c "' . $command . '"';
}

exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    @unlink($sqlPath);
    error_log('Database backup failed, exit code: ' . $returnVar);
    die('Database backup failed. Check server logs.');
}

if ($type === 'db') {
    // Download SQL directly
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $sqlFilename . '"');
    header('Content-Length: ' . filesize($sqlPath));
    readfile($sqlPath);
    unlink($sqlPath);
    exit;
}

if ($type === 'full') {
    // 2. Create ZIP with SQL + Uploads
    $zipFilename = 'backup_full_' . date('Y-m-d_H-i-s') . '.zip';
    $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        @unlink($sqlPath);
        error_log('Backup zip open failed: ' . $zipPath);
        die('Cannot create backup archive. Check server logs.');
    }

    // Add SQL file
    $zip->addFile($sqlPath, 'database.sql');

    // Add Uploads directory
    $rootPath = realpath(__DIR__ . '/../../');
    $uploadsPath = $rootPath . '/uploads';

    if (is_dir($uploadsPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'uploads/' . substr($filePath, strlen($uploadsPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    $zip->close();

    // Download ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: no-cache');
    readfile($zipPath);

    // Cleanup
    unlink($sqlPath);
    unlink($zipPath);
    exit;
}
?>
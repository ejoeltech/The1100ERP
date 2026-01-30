<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';

requirePermission('manage_settings');

// Check backup type
$type = $_GET['type'] ?? 'db'; // 'db' or 'full'

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
$sqlFilename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
$sqlPath = sys_get_temp_dir() . '/' . $sqlFilename;

$command = sprintf(
    '%s --host=%s --user=%s --password=%s --routines %s > %s',
    $dumpCommand,
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    $isWindows ? '"' . $sqlPath . '"' : escapeshellarg($sqlPath)
);

if ($isWindows) {
    $command = 'cmd /c "' . $command . '"';
}

exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    die("Database backup failed. Exit code: $returnVar");
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
        die("Cannot open <$zipPath>");
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
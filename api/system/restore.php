<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';

requirePermission('manage_settings');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded.');
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['file']['error'];
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        throw new Exception(isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown upload error: ' . $error_code);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['sql', 'zip'])) {
        throw new Exception('Invalid file type. Only .sql and .zip files are allowed.');
    }

    // WP11: bound restore payloads.
    if (($file['size'] ?? 0) > 128 * 1024 * 1024) {
        throw new Exception('Restore file too large (max 128 MB).');
    }

    // Command configuration
    $mysqlCommand = 'mysql';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $xamppMySQL = 'c:/xampp/mysql/bin/mysql.exe';
        if (file_exists($xamppMySQL)) {
            $mysqlCommand = '"' . $xamppMySQL . '"';
        }
    }

    $sqlFileToRestore = null;
    $tempDir = null;

    if ($ext === 'zip') {
        // Handle ZIP extraction (WP11: entries inspected pre-extract).
        $tempDir = sys_get_temp_dir() . '/restore_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0700, true);

        $zip = inspectZipArchive($file['tmp_name']);
        try {
            $zip->extractTo($tempDir);
        } finally {
            $zip->close();
        }

            // Look for database.sql or any .sql file
            $sqlFiles = glob($tempDir . '/*.sql');
            if (empty($sqlFiles)) {
                throw new Exception('No SQL file found inside the ZIP archive.');
            }
            $sqlFileToRestore = $sqlFiles[0];

            // Restore Uploads if detected
            // Logic: Move contents of extracted 'uploads' folder to system uploads
            // Restore Uploads if detected AND requested
            $extractedUploads = $tempDir . '/uploads';
            $includeMedia = isset($_POST['include_media']) && $_POST['include_media'] === '1';

            if ($includeMedia && is_dir($extractedUploads)) {
                // WP11: containment re-checked per entry; guarded dir included.
                require_once __DIR__ . '/../../includes/security.php';
                $targetUploads = ensureUploadDir(__DIR__ . '/../../uploads');
                $targetReal = realpath($targetUploads);
                // Simple recursive copy/overwrite
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractedUploads, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $subPath = str_replace('\\', '/', $iterator->getSubPathName());
                    if ($subPath === '' || strpos($subPath, '..') !== false) {
                        continue;
                    }
                    $destination = $targetReal . '/' . $subPath;
                    if ($item->isDir()) {
                        if (!is_dir($destination)) {
                            mkdir($destination, 0755, true);
                        }
                    } else {
                        copy($item->getPathname(), $destination);
                    }
                }
            }
    } else {
        // Direct SQL file
        $sqlFileToRestore = $file['tmp_name'];
    }

    // Perform DB Restore
    $command = sprintf(
        '%s --host=%s --user=%s --password=%s %s < %s',
        $mysqlCommand,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($sqlFileToRestore) // WP11: escaped, not hand-quoted
    );

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'cmd /c "' . $command . '"';
    }

    exec($command . ' 2>&1', $output, $returnVar);

    // Cleanup temp (WP11: temp dirs are actually removed now)
    if ($tempDir) {
        if (function_exists('removeDirRecursive')) {
            removeDirRecursive($tempDir);
        }
    }

    if ($returnVar !== 0) {
        // WP8/WP11: mysql output can leak paths — log it, generic to user.
        error_log('System restore command failed.');
        throw new Exception('Restore failed. Check server logs.');
    }

    // Log the action
    $details = json_encode(['file' => $file['name']]);
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_restore', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $details]);

    echo json_encode(['success' => true, 'message' => 'System restored successfully']);

} catch (Exception $e) {
    error_log('System restore error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Restore failed. Check server logs.']);
}
?>
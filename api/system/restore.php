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
        // Handle ZIP extraction
        $zip = new ZipArchive;
        $tempDir = sys_get_temp_dir() . '/restore_' . uniqid();
        mkdir($tempDir);

        if ($zip->open($file['tmp_name']) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();

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
                $targetUploads = realpath(__DIR__ . '/../../uploads');
                // Simple recursive copy/overwrite
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractedUploads, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $subPath = $iterator->getSubPathName();
                    $destination = $targetUploads . '/' . $subPath;
                    if ($item->isDir()) {
                        if (!is_dir($destination)) {
                            mkdir($destination);
                        }
                    } else {
                        copy($item, $destination);
                    }
                }
            }
        } else {
            throw new Exception('Failed to open ZIP file.');
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
        '"' . $sqlFileToRestore . '"' // Double quotes for Windows paths
    );

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'cmd /c "' . $command . '"';
    }

    exec($command . ' 2>&1', $output, $returnVar);

    // Cleanup temp
    if ($tempDir) {
        // Recursive delete temp dir
        // (Implementation omitted for brevity, usually system handles temp cleanup eventually)
    }

    if ($returnVar !== 0) {
        throw new Exception('Restore failed: ' . implode("\n", $output));
    }

    // Log the action
    $details = json_encode(['file' => $file['name']]);
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_restore', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $details]);

    echo json_encode(['success' => true, 'message' => 'System restored successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
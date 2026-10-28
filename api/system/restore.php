<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';

requirePermission('manage_settings');

header('Content-Type: application/json');

/**
 * Validation failures the operator can act on. Anything else is logged and
 * reported generically so server paths/SQL errors never reach the browser.
 */
class RestoreValidationException extends Exception
{
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tempDir = null;

try {
    if (!isset($_FILES['file'])) {
        throw new RestoreValidationException('No file uploaded.');
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
        throw new RestoreValidationException(isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown upload error: ' . $error_code);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['sql', 'zip'])) {
        throw new RestoreValidationException('Invalid file type. Upload a .sql dump, or a full backup .zip containing database.sql.');
    }

    // WP11: bound restore payloads.
    if (($file['size'] ?? 0) > 128 * 1024 * 1024) {
        throw new RestoreValidationException('Restore file too large (max 128 MB).');
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

    if ($ext === 'zip') {
        if (!class_exists('ZipArchive')) {
            throw new RestoreValidationException('ZIP support is not enabled on this server. Upload a .sql dump instead.');
        }

        // Handle ZIP extraction (WP11: entries inspected pre-extract).
        $tempDir = sys_get_temp_dir() . '/restore_' . bin2hex(random_bytes(8));
        if (!@mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new Exception('Cannot create temporary restore directory.');
        }

        try {
            $zip = inspectZipArchive($file['tmp_name']);
        } catch (Exception $e) {
            throw new RestoreValidationException('Invalid backup archive: ' . $e->getMessage());
        }

        try {
            if (!$zip->extractTo($tempDir)) {
                throw new RestoreValidationException('Could not extract the backup archive.');
            }
        } finally {
            $zip->close();
        }

        // Full backups always contain database.sql at the archive root.
        $sqlFiles = glob($tempDir . '/*.sql');
        if (empty($sqlFiles)) {
            // Update packages carry code trees instead of a dump. Say so
            // explicitly: uploading a patch here can never work.
            $looksLikeUpdate = file_exists($tempDir . '/patch_info.txt')
                || is_dir($tempDir . '/pages')
                || is_dir($tempDir . '/api')
                || is_dir($tempDir . '/includes')
                || is_dir($tempDir . '/vendor');
            if ($looksLikeUpdate) {
                throw new RestoreValidationException('This ZIP is a code update package, not a database backup. Apply it under System Updates → Apply Update Patch.');
            }
            throw new RestoreValidationException('No database.sql found in this ZIP. Upload a .sql dump, or a full backup ZIP created by the backup tool.');
        }
        $sqlFileToRestore = $sqlFiles[0];

        // Restore uploads when the archive contains them and the operator asked.
        $extractedUploads = $tempDir . '/uploads';
        $includeMedia = isset($_POST['include_media']) && $_POST['include_media'] === '1';

        if ($includeMedia && is_dir($extractedUploads)) {
            // WP11: containment re-checked per entry; guarded dir included.
            $targetUploads = ensureUploadDir(__DIR__ . '/../../uploads');
            $targetReal = realpath($targetUploads);
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

} catch (RestoreValidationException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    // Includes Error (e.g. missing extensions) so the client always gets JSON.
    error_log('System restore error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Restore failed. Check server logs.']);
} finally {
    // WP11: temp dirs are removed on every path, including validation failure.
    if ($tempDir && function_exists('removeDirRecursive')) {
        removeDirRecursive($tempDir);
    }
}

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

    if ($ext !== 'zip') {
        throw new Exception('Invalid file type. Only .zip files are allowed.');
    }

    $zip = new ZipArchive;
    if ($zip->open($file['tmp_name']) === TRUE) {

        // Root path
        $rootPath = realpath(__DIR__ . '/../../');

        // Extract
        $zip->extractTo($rootPath);
        $zip->close();

        // Check for post-update script
        $updateScript = $rootPath . '/update_script.php';
        $scriptOutput = '';

        if (file_exists($updateScript)) {
            // Run script
            ob_start();
            include $updateScript;
            $scriptOutput = ob_get_clean();

            // Delete script after run
            unlink($updateScript);
        }

        // Log
        $details = json_encode(['file' => $file['name'], 'output' => $scriptOutput]);
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_update', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $details]);

        echo json_encode(['success' => true, 'message' => 'Patch applied successfully. ' . strip_tags($scriptOutput)]);
    } else {
        throw new Exception('Failed to open ZIP file');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
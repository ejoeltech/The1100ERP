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
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
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
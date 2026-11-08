<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/invites.php';
require_once '../../includes/update-manager.php';

requirePermission('manage_settings');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// H-11: a stolen admin session alone must not be enough to run uploaded
// code. Require the current password and a typed confirmation.
if (($_POST['confirm'] ?? '') !== 'APPLY') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type APPLY to confirm this update.']);
    exit;
}
$reauthIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!throttleCheck('reauth:' . ($_SESSION['user_id'] ?? 0) . ':' . $reauthIp, 5, 900)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many re-authentication attempts. Try again later.']);
    exit;
}
$reauthPassword = $_POST['admin_password'] ?? '';
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$reauthUser = $stmt->fetch();
if (!$reauthUser || !verifyPassword($reauthPassword, $reauthUser['password'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Incorrect admin password.']);
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

    // WP11: bound patch size.
    if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
        throw new Exception('Patch file too large (max 25 MB).');
    }

    // Root path
    $rootPath = realpath(__DIR__ . '/../../');

    // Apply through the shared manager: it creates a file backup first, then
    // inspects, extracts and runs update_script.php. Exceptions trigger an
    // automatic file rollback.
    $result = updateApplyArchive($file['tmp_name'], $rootPath, [
        'source' => 'upload',
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
        'release' => '',
    ]);
    $scriptOutput = (string)($result['output'] ?? '');

    // Log
    $details = json_encode([
        'file' => $file['name'],
        'backup_id' => $result['backup']['id'] ?? null,
        'output' => $scriptOutput,
    ]);
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_update', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $details]);

    $message = 'Patch applied successfully. ' . strip_tags($scriptOutput);
    if (!empty($result['schema_errors'])) {
        $message .= ' Schema warnings were reported — review the output or roll back to the previous files.';
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'backup_id' => $result['backup']['id'] ?? null,
    ]);

} catch (Throwable $e) {
    error_log('Apply patch error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Patch failed. Check server logs.']);
}
?>
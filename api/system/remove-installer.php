<?php
// Deletes the entire maintenance/ folder (installer + leftovers).
// Permanent admin tool used by pages/system-update.php.
// Guards: admin role + current password + CSRF.
// POST: password, csrf_token
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/security.php';
require_once '../../includes/passwords.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Administrator privileges required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$password = $_POST['password'] ?? '';
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password required']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !verifyPassword($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

$dir = dirname(__DIR__, 2) . '/maintenance';

// Safety: only delete the expected folder
if (basename($dir) !== 'maintenance' || !is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => 'Safety stop: unexpected path']);
    exit;
}

function removeTree($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            removeTree($path . '/' . $item);
        }
        @rmdir($path);
    } else {
        @chmod($path, 0777);
        @unlink($path);
    }
}

removeTree($dir);

if (is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => 'Partial delete: remove maintenance/ manually via File Manager']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'installer_removed', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], json_encode(['path' => 'maintenance/'])]);
} catch (Exception $e) {
    // audit table missing: non-fatal
}

echo json_encode(['success' => true, 'message' => 'maintenance/ deleted.']);

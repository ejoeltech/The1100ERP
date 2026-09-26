<?php
// Secure factory reset (permanent admin tool, survives cleanup).
// Wipes ALL data but KEEPS maintenance/setup/ so the wizard can reinstall
// immediately with no re-upload. Afterwards run cleanup to delete setup/.
//
// Guards: admin role + current password + CSRF + typed RESET phrase.
// POST: password, csrf_token, confirm=RESET
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/security.php';

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

if (($_POST['confirm'] ?? '') !== 'RESET') {
    echo json_encode(['success' => false, 'message' => 'Type RESET to confirm']);
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

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    foreach (['../../config.php', '../../maintenance/setup/lock'] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    session_destroy();

    echo json_encode(['success' => true, 'message' => 'System reset. maintenance/setup/ kept: reinstall via the wizard, then delete it.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
}

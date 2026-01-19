<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// 1. Security Check: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// 2. Security Check: Password Verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$password = $_POST['password'] ?? '';
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password required']);
    exit;
}

// Verify password against current user
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

try {
    // 3. WIPE DATABASE
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    // 4. DELETE CONFIG FILES
    $configPath = '../config.php';
    if (file_exists($configPath)) {
        unlink($configPath);
    }

    $lockPath = '../setup/lock';
    if (file_exists($lockPath)) {
        unlink($lockPath);
    }

    // 5. DESTROY SESSION
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'System reset successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
}
?>
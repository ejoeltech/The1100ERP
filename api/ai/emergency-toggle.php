<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Admin only
if ($_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $enable = $input['enable'] ?? false;
    setSetting('ai_emergency_disable', $enable ? '0' : '1');

    echo json_encode([
        'success' => true,
        'message' => 'AI features ' . ($enable ? 'enabled' : 'disabled')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
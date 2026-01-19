<?php
session_start();
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/permissions.php';
require_once '../../includes/audit.php';

// Check if user is logged in and has admin permission
requireLogin();
requirePermission('manage_settings');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        throw new Exception('Bank account ID is required');
    }

    // Soft delete (set is_active = 0)
    $stmt = $pdo->prepare("UPDATE bank_accounts SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Bank account not found');
    }

    logAudit('delete_bank_account', 'bank_account', $id);

    echo json_encode([
        'success' => true,
        'message' => 'Bank account deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
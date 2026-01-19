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

    // Get current state
    $stmt = $pdo->prepare("SELECT show_on_documents FROM bank_accounts WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $account = $stmt->fetch();

    if (!$account) {
        throw new Exception('Bank account not found');
    }

    $new_state = $account['show_on_documents'] ? 0 : 1;

    // If trying to uncheck, validate at least 3 will remain
    if ($new_state == 0) {
        $count = getSelectedBankAccountsCount();
        if ($count <= 3) {
            throw new Exception('You must keep at least 3 bank accounts selected for display on documents');
        }
    }

    // Toggle the state
    $stmt = $pdo->prepare("UPDATE bank_accounts SET show_on_documents = ? WHERE id = ?");
    $stmt->execute([$new_state, $id]);

    logAudit('toggle_bank_display', 'bank_account', $id, ['new_state' => $new_state]);

    echo json_encode([
        'success' => true,
        'show_on_documents' => $new_state,
        'message' => $new_state ? 'Bank account will now show on documents' : 'Bank account will not show on documents'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
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
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $show_on_documents = isset($_POST['show_on_documents']) ? 1 : 0;

    // Validation
    if (empty($bank_name)) {
        throw new Exception('Bank name is required');
    }
    if (empty($account_number)) {
        throw new Exception('Account number is required');
    }
    if (empty($account_name)) {
        throw new Exception('Account name is required');
    }

    if ($id) {
        // Update existing bank account
        $stmt = $pdo->prepare("
            UPDATE bank_accounts 
            SET bank_name = ?, account_number = ?, account_name = ?, show_on_documents = ?
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$bank_name, $account_number, $account_name, $show_on_documents, $id]);

        logAudit('update_bank_account', 'bank_account', $id, [
            'bank_name' => $bank_name
        ]);

        $message = 'Bank account updated successfully';
    } else {
        // Get next display order
        $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM bank_accounts");
        $next_order = $stmt->fetchColumn();

        // Insert new bank account
        $stmt = $pdo->prepare("
            INSERT INTO bank_accounts (bank_name, account_number, account_name, show_on_documents, display_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bank_name, $account_number, $account_name, $show_on_documents, $next_order]);

        $id = $pdo->lastInsertId();

        logAudit('create_bank_account', 'bank_account', $id, [
            'bank_name' => $bank_name
        ]);

        $message = 'Bank account added successfully';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
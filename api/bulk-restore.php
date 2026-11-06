<?php
include '../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $document_type = $input['document_type'] ?? '';
    $ids = $input['ids'] ?? [];

    $table = '';
    switch ($document_type) {
        case 'quote':
            $table = 'quotes';
            break;
        case 'invoice':
            $table = 'invoices';
            break;
        case 'receipt':
            $table = 'receipts';
            break;
        default:
            throw new Exception('Invalid document type');
    }

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('No items selected');
    }

    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        throw new Exception('Invalid item IDs');
    }

    // WP3: restoring is a delete-class action (same privilege + ownership rules).
    $deletePerms = ['quote' => 'delete_quote', 'invoice' => 'delete_invoice', 'receipt' => 'delete_receipt'];
    requirePermission($deletePerms[$document_type]);

    if ($document_type === 'receipt') {
        throw new Exception('Receipts must be restored individually so invoice balances can be reconciled.');
    }

    $pdo->beginTransaction();

    // Restore: clear deleted_at timestamp
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $check = $pdo->prepare("SELECT id, created_by, status FROM $table WHERE id IN ($placeholders) AND deleted_at IS NOT NULL");
    $check->execute($ids);
    foreach ($check->fetchAll() as $row) {
        if (!canDeleteDocument($row)) {
            throw new Exception('You do not have permission to restore one or more selected documents.');
        }
    }

    // Whitelisted table name used directly
    $stmt = $pdo->prepare("
        UPDATE $table
        SET deleted_at = NULL,
            updated_at = NOW()
        WHERE id IN ($placeholders)
        AND deleted_at IS NOT NULL
    ");

    $stmt->execute($ids);

    $restored_count = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'restored_count' => $restored_count,
        'message' => "Successfully restored $restored_count items"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Bulk restore error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
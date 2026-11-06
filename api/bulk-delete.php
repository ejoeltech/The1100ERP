<?php
include '../includes/session-check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    $document_type = $input['document_type'] ?? '';
    $ids = $input['ids'] ?? [];

    // Map document type to table name
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

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        throw new Exception('Invalid item IDs');
    }

    // WP3: per-type delete permission + ownership.
    $deletePerms = ['quote' => 'delete_quote', 'invoice' => 'delete_invoice', 'receipt' => 'delete_receipt'];
    requirePermission($deletePerms[$document_type]);

    if ($document_type === 'receipt') {
        throw new Exception('Receipts must be deleted individually so invoice balances and customer credit can be reversed.');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Soft delete: set deleted_at timestamp
    // We cannot use prepared statements for the table name, but we validated it above via whitelist.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Check every row against the same ownership rule as the single-delete API.
    $check = $pdo->prepare("SELECT id, created_by, status FROM $table WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $check->execute($ids);
    foreach ($check->fetchAll() as $row) {
        if (!canDeleteDocument($row)) {
            throw new Exception('You do not have permission to delete one or more selected documents.');
        }
    }

    $stmt = $pdo->prepare("
        UPDATE $table
        SET deleted_at = NOW()
        WHERE id IN ($placeholders)
        AND deleted_at IS NULL
    ");

    $stmt->execute($ids);

    $deleted_count = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'deleted_count' => $deleted_count,
        'message' => "Successfully deleted $deleted_count items"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Bulk delete error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
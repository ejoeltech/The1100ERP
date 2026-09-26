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

    // WP3: per-type delete permission + sales_rep ownership scoping.
    $deletePerms = ['quote' => 'delete_quote', 'invoice' => 'delete_invoice', 'receipt' => 'delete_receipt'];
    requirePermission($deletePerms[$document_type]);

    // Begin transaction
    $pdo->beginTransaction();

    // Soft delete: set deleted_at timestamp
    // We cannot use prepared statements for the table name, but we validated it above via whitelist.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $params = $ids;
    $ownerScope = '';
    if (getUserRole() === 'sales_rep') {
        $ownerScope = 'AND created_by = ?';
        $params[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare("
        UPDATE $table
        SET deleted_at = NOW()
        WHERE id IN ($placeholders)
        AND deleted_at IS NULL
        $ownerScope
    ");

    $stmt->execute($params);

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
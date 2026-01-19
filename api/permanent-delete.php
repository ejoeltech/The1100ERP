<?php
include '../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check if user is admin or super_admin
    $user_role = $current_user['user_role'] ?? 'user';

    if (!in_array($user_role, ['admin', 'super_admin'])) {
        throw new Exception('Permission denied. Only admins can permanently delete documents.');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $document_type = $input['document_type'] ?? '';
    $ids = $input['ids'] ?? [];

    $table = '';
    $lineItemTable = '';
    $foreignKey = '';

    switch ($document_type) {
        case 'quote':
            $table = 'quotes';
            $lineItemTable = 'quote_line_items';
            $foreignKey = 'quote_id';
            break;
        case 'invoice':
            $table = 'invoices';
            $lineItemTable = 'invoice_line_items';
            $foreignKey = 'invoice_id';
            break;
        case 'receipt':
            $table = 'receipts';
            // Receipts typically don't own line items in this system (they reference invoices)
            $lineItemTable = null;
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

    $pdo->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // First, delete associated line items if applicable
    if ($lineItemTable) {
        $stmt = $pdo->prepare("
            DELETE FROM $lineItemTable 
            WHERE $foreignKey IN ($placeholders)
        ");
        $stmt->execute($ids);
    }

    // Then permanently delete the documents
    $stmt = $pdo->prepare("
        DELETE FROM $table 
        WHERE id IN ($placeholders) 
        AND deleted_at IS NOT NULL
    ");

    $stmt->execute($ids);

    $deleted_count = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'deleted_count' => $deleted_count,
        'message' => "Successfully deleted $deleted_count items permanently"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Permanent delete error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
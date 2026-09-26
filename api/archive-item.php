<?php
// API to toggle is_archived status
// POST /api/archive-item.php
// Payload: { type: 'quote'|'invoice'|'receipt', id: 123, action: 'archive'|'unarchive' }

require_once '../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $type = $data['type'] ?? '';
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $action = $data['action'] ?? 'archive';

    if (!$id || !in_array($type, ['quote', 'invoice', 'receipt'])) {
        throw new Exception('Invalid parameters');
    }

    // WP3: archiving needs archive_document; sales reps only touch own rows.
    requirePermission('archive_document');

    $table = '';
    switch ($type) {
        case 'quote':
            $table = 'quotes';
            break;
        case 'invoice':
            $table = 'invoices';
            break;
        case 'receipt':
            $table = 'receipts';
            break;
    }

    // Use deleted_at column for soft delete (archive/restore)
    $params = [$id];
    $ownerScope = '';
    if (getUserRole() === 'sales_rep') {
        $ownerScope = 'AND created_by = ?';
        $params[] = $_SESSION['user_id'];
    }
    if ($action === 'archive') {
        // Archive: set deleted_at to current timestamp
        $stmt = $pdo->prepare("UPDATE $table SET deleted_at = NOW() WHERE id = ? $ownerScope");
    } else {
        // Unarchive (restore): set deleted_at to NULL
        $stmt = $pdo->prepare("UPDATE $table SET deleted_at = NULL WHERE id = ? $ownerScope");
    }

    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => "Item successfully {$action}d"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
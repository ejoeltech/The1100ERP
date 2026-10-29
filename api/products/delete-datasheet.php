<?php
require_once '../../includes/session-check.php';
requirePermission('manage_products');
require_once '../../includes/equipment-datasheets.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if ($id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a datasheet to delete.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT brand, model, category FROM equipment_datasheets WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'That datasheet no longer exists.']);
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM equipment_datasheets WHERE id = ?');
    $delete->execute([$id]);

    if (function_exists('logAudit')) {
        logAudit('delete', 'equipment_datasheet', $id, [
            'category' => $row['category'],
            'brand' => $row['brand'],
            'model' => $row['model'],
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Datasheet deleted.']);
} catch (PDOException $e) {
    error_log('Datasheet delete failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not delete the datasheet. Check the server log.']);
}

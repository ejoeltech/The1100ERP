<?php
// API to soft delete a product
// POST /api/products/delete-product.php

include '../../config.php';
include '../../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        throw new Exception('Product ID is required');
    }

    // Soft delete
    $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
include '../../includes/session-check.php';
requirePermission('manage_products');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

try {
    $id = intval($_POST['id'] ?? 0);
    $product_code = trim($_POST['product_code'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$id)
        throw new Exception('Invalid product ID');
    if (empty($product_code) || empty($product_name) || empty($category)) {
        throw new Exception('Required fields missing');
    }
    if ($unit_price < 0)
        throw new Exception('Price cannot be negative');

    // Check duplicate code (exclude current product)
    $stmt = $pdo->prepare("SELECT id FROM products WHERE product_code = ? AND id != ?");
    $stmt->execute([$product_code, $id]);
    if ($stmt->fetch()) {
        throw new Exception('Product code already exists');
    }

    // Update
    $stmt = $pdo->prepare("
        UPDATE products SET
            product_code = ?,
            product_name = ?,
            description = ?,
            unit_price = ?,
            category = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->execute([$product_code, $product_name, $description, $unit_price, $category, $is_active, $id]);

    if (function_exists('logAudit')) {
        logAudit('update', 'product', $id, ['product_code' => $product_code]);
    }

    header('Location: ../../pages/products/manage-products.php?updated=1');
    exit;

} catch (Exception $e) {
    flashOldInput('../../pages/products/edit-product.php');
    $id = $_POST['id'] ?? '';
    header('Location: ../../pages/products/edit-product.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>
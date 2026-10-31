<?php
include '../../includes/session-check.php';
requirePermission('manage_products');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

try {
    $product_code = trim($_POST['product_code'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate
    if (empty($product_code) || empty($product_name) || empty($category)) {
        throw new Exception('Required fields missing');
    }

    if ($unit_price < 0) {
        throw new Exception('Price cannot be negative');
    }

    // Check for duplicate code
    $stmt = $pdo->prepare("SELECT id FROM products WHERE product_code = ?");
    $stmt->execute([$product_code]);
    if ($stmt->fetch()) {
        throw new Exception('Product code already exists');
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO products (product_code, product_name, description, unit_price, category, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $product_code,
        $product_name,
        $description,
        $unit_price,
        $category,
        $is_active,
        $_SESSION['user_id']
    ]);

    // Log
    if (function_exists('logAudit')) {
        logAudit('create', 'product', $pdo->lastInsertId(), ['product_code' => $product_code]);
    }

    header('Location: ../../pages/products/manage-products.php?created=1');
    exit;

} catch (Exception $e) {
    flashOldInput('../../pages/products/create-product.php');
    header('Location: ../../pages/products/create-product.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
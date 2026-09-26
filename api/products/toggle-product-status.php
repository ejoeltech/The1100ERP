<?php
include '../../includes/session-check.php';
requirePermission('manage_products');

// WP4: POST only (was GET-linkable).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/products/manage-products.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ../../pages/products/manage-products.php?error=Invalid product');
    exit;
}

try {
    // Get current status
    $stmt = $pdo->prepare("SELECT is_active FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Toggle
    $new_status = $product['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);

    if (function_exists('logAudit')) {
        logAudit('status_change', 'product', $id, ['is_active' => $new_status]);
    }

    header('Location: ../../pages/products/manage-products.php?status_changed=1');
    exit;

} catch (Exception $e) {
    header('Location: ../../pages/products/manage-products.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
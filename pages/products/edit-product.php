<?php
include '../../includes/session-check.php';
requirePermission('manage_products');

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header('Location: manage-products.php?error=Invalid product');
    exit;
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: manage-products.php?error=Product not found');
    exit;
}

$pageTitle = 'Edit Product - ERP System';
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Edit Product</h2>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/products/update-product.php">
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Code <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="product_code" required
                            value="<?php echo htmlspecialchars($product['product_code']); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="category" required list="categories"
                            value="<?php echo htmlspecialchars($product['category']); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="product_name" required
                        value="<?php echo htmlspecialchars($product['product_name']); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₦) <span
                            class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01"
                        value="<?php echo $product['unit_price']; ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" <?php echo $product['is_active'] ? 'checked' : ''; ?> class="w-5 h-5 text-primary rounded">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-4 justify-end mt-8">
                <a href="manage-products.php"
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                    Cancel
                </a>
                <button type="submit"
                    class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                    Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
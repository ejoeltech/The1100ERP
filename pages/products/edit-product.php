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

    <?php if (!empty($_GET['error'])): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200">
            <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/products/update-product.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Code <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="product_code" required
                            value="<?php echo htmlspecialchars(old('product_code', $product['product_code']), ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="category" required list="categories"
                            value="<?php echo htmlspecialchars(old('category', $product['category']), ENT_QUOTES, 'UTF-8'); ?>"
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
                        value="<?php echo htmlspecialchars(old('product_name', $product['product_name']), ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars(old('description', $product['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₦) <span
                            class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01"
                        value="<?php echo htmlspecialchars(old('unit_price', $product['unit_price']), ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" <?php echo oldChecked('is_active', '1', (bool)$product['is_active']); ?> class="w-5 h-5 text-primary rounded">
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

<?php clearOldInput(); ?>
<?php include '../../includes/footer.php'; ?>
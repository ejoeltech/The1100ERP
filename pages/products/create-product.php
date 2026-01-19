<?php
include '../../includes/session-check.php';
requirePermission('manage_products');
$pageTitle = 'Create Product - Bluedots Technologies';

// Get categories for dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Create New Product</h2>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/products/save-product.php">
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Code <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="product_code" required placeholder="e.g., WEB-001"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <p class="text-xs text-gray-500 mt-1">Unique product code</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="category" required list="categories" placeholder="e.g., Software"
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
                    <input type="text" name="product_name" required placeholder="e.g., Website Design & Development"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4" placeholder="Product description..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₦) <span
                            class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01" placeholder="0.00"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" checked class="w-5 h-5 text-primary rounded">
                        <span class="text-sm font-semibold text-gray-700">Active (available for quotes)</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-4 justify-end mt-8">
                <a href="manage-products.php"
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">
                    Cancel
                </a>
                <button type="submit"
                    class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-center">
                    Create Product
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
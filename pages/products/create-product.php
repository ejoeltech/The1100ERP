<?php
include '../../includes/session-check.php';
requirePermission('manage_products');
$pageTitle = 'Create Product - ERP System';

// Get categories for dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-900">Create New Product</h2>
        <button onclick="openAiModal()"
            class="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-4 py-2 rounded-lg hover:opacity-90 transition shadow-md">
            <img src="../../assets/icons/magic.png" class="w-5 h-5" alt="Magic">
            Magic Fill
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/products/save-product.php" id="productForm">
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

<!-- AI Modal -->
<div id="aiModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full transform transition-all">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span>✨</span> AI Product Autofill
                </h3>
                <button onclick="closeAiModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <p class="text-gray-600 text-sm mb-4">
                Paste a product description, invoice line, or supplier message. The AI will extract the details.
            </p>

            <textarea id="aiInput" rows="4"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 mb-4"
                placeholder="e.g. 50 units of Jinko 550W Solar Panels at 120k each"></textarea>

            <div class="flex justify-end gap-3">
                <button onclick="closeAiModal()"
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button onclick="runAiAutofill()" id="aiBtn"
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold flex items-center gap-2">
                    <span>Autofill Form</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openAiModal() {
        document.getElementById('aiModal').classList.remove('hidden');
        document.getElementById('aiInput').focus();
    }

    function closeAiModal() {
        document.getElementById('aiModal').classList.add('hidden');
    }

    async function runAiAutofill() {
        const input = document.getElementById('aiInput').value;
        if (!input) return;

        const btn = document.getElementById('aiBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Processing...';
        btn.disabled = true;

        try {
            const response = await fetch('../../api/ai/parse-product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: input })
            });

            const res = await response.json();

            if (res.success) {
                const data = res.data;
                const form = document.querySelector('form');

                if (data.name) form.querySelector('[name="product_name"]').value = data.name;
                if (data.description) form.querySelector('[name="description"]').value = data.description;
                if (data.price_ngn) form.querySelector('[name="unit_price"]').value = data.price_ngn;
                if (data.category) form.querySelector('[name="category"]').value = data.category;

                // Auto generate code if missing? allow user to edit

                closeAiModal();
            } else {
                alert('AI Error: ' + res.error);
            }
        } catch (err) {
            alert('Network Error: ' + err.message);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>
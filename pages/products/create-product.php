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

    <?php if (!empty($_GET['error'])): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200">
            <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form method="POST" action="../../api/products/save-product.php" id="productForm">
            <?php echo csrfField(); ?>
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Code <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="product_code" required placeholder="e.g., WEB-001"
                            value="<?php echo htmlspecialchars(old('product_code'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <p class="text-xs text-gray-500 mt-1">Unique product code</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="category" required list="categories" placeholder="e.g., Software"
                            value="<?php echo htmlspecialchars(old('category'), ENT_QUOTES, 'UTF-8'); ?>"
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
                        value="<?php echo htmlspecialchars(old('product_name'), ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4" placeholder="Product description..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars(old('description'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₦) <span
                            class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01" placeholder="0.00"
                        value="<?php echo htmlspecialchars(old('unit_price'), ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" <?php echo oldChecked('is_active', '1', true); ?> class="w-5 h-5 text-primary rounded">
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

    // Fill a field by name without assuming it exists. Missing or renamed
    // fields must never abort the rest of the autofill.
    function aiSetField(name, value) {
        if (value === null || value === undefined || value === '') return false;
        const form = document.getElementById('productForm');
        if (!form) return false;
        const field = form.querySelector('[name="' + name + '"]');
        if (!field) {
            console.warn('AI autofill: no field named "' + name + '"');
            return false;
        }
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    async function runAiAutofill() {
        const input = document.getElementById('aiInput').value.trim();
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

            let res;
            try {
                res = await response.json();
            } catch (parseErr) {
                throw new Error('Server returned an invalid response (HTTP ' + response.status + ').');
            }

            if (!response.ok || res.success === false || res.error) {
                throw new Error(res.error || res.message || ('Request failed (HTTP ' + response.status + ').'));
            }

            const data = res.data || {};
            const filled = [
                aiSetField('product_name', data.name),
                aiSetField('description', data.description),
                aiSetField('unit_price', data.price_ngn),
                aiSetField('category', data.category),
                aiSetField('product_code', data.product_code)
            ].filter(Boolean).length;

            closeAiModal();
            if (filled === 0) {
                alert('The AI could not find any usable product details in that text. Please add more detail and try again.');
            }
        } catch (err) {
            alert('AI Error: ' + err.message);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>

<?php clearOldInput(); ?>
<?php include '../../includes/footer.php'; ?>
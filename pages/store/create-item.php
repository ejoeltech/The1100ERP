<?php
require_once '../../config.php';
include '../../includes/session-check.php';

$pageTitle = 'Add New Item - Store';

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM item_categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="items.php" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900">Add New Store Item</h2>
    </div>

    <form id="itemForm" class="space-y-6">
        <div class="bg-white rounded-xl shadow-md p-8 border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-6 border-b pb-2">Basic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Item Name <span class="text-red-500">*</span></label>
                    <input type="text" id="itemName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="e.g. Jinko 550W Mono Solar Panel">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SKU / Item Code</label>
                    <input type="text" id="itemSku" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="e.g. SOL-PAN-550">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select id="itemCategory" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea id="itemDescription" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all resize-none" placeholder="Detailed item description..."></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-8 border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-6 border-b pb-2">Pricing & Logistics</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Selling Price (₦) <span class="text-red-500">*</span></label>
                    <input type="text" id="itemPrice" inputmode="decimal" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-bold" placeholder="0.00" onfocus="unformatInput(this)" onblur="formatInput(this)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cost Price (₦)</label>
                    <input type="text" id="itemCostPrice" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all text-gray-600" placeholder="0.00" onfocus="unformatInput(this)" onblur="formatInput(this)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit</label>
                    <input type="text" id="itemUnit" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="e.g. pcs, meters, hours">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-8 border border-gray-100">
            <h3 class="text-lg font-bold text-gray-800 mb-6 border-b pb-2">Stock Management</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Current Stock Quantity</label>
                    <input type="text" id="itemStock" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" value="0" onfocus="unformatInput(this)" onblur="formatInput(this)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Minimum Stock Level</label>
                    <input type="text" id="itemMinStock" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" value="5" onfocus="unformatInput(this)" onblur="formatInput(this)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select id="itemStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4 pb-12">
            <a href="items.php" class="px-8 py-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-bold transition-all shadow-sm">Cancel</a>
            <button type="submit" id="saveBtn" class="px-10 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-bold shadow-lg transition-all transform hover:-translate-y-1">
                Save Item
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Check for duplicate data
    const dupData = localStorage.getItem('duplicate_item');
    if (dupData) {
        const item = JSON.parse(dupData);
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemCategory').value = item.category_id || '';
        document.getElementById('itemDescription').value = item.description || '';
        document.getElementById('itemPrice').value = formatNumber(item.price);
        document.getElementById('itemCostPrice').value = formatNumber(item.cost_price);
        document.getElementById('itemUnit').value = item.unit || '';
        document.getElementById('itemStock').value = formatNumber(item.stock_quantity);
        document.getElementById('itemMinStock').value = formatNumber(item.minimum_stock);
        document.getElementById('itemStatus').value = item.status || 'active';
        
        localStorage.removeItem('duplicate_item');
        Swal.fire({ icon: 'info', title: 'Editing Duplicate', text: 'You are creating a new item based on a copy.', timer: 2000, showConfirmButton: false });
    }
});

document.getElementById('itemForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const data = {
        name: document.getElementById('itemName').value,
        sku: document.getElementById('itemSku').value,
        category_id: document.getElementById('itemCategory').value,
        description: document.getElementById('itemDescription').value,
        price: parseNumber(document.getElementById('itemPrice').value),
        cost_price: parseNumber(document.getElementById('itemCostPrice').value),
        unit: document.getElementById('itemUnit').value,
        stock_quantity: parseNumber(document.getElementById('itemStock').value),
        minimum_stock: parseNumber(document.getElementById('itemMinStock').value),
        status: document.getElementById('itemStatus').value
    };

    try {
        const response = await fetch('../../api/store/items.php?action=save', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', text: 'Item has been added to the store.', showConfirmButton: false, timer: 1500 });
            setTimeout(() => window.location.href = 'items.php', 1500);
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to save item', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Save Item';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

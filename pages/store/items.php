<?php
require_once '../../config.php';
include '../../includes/session-check.php';

$pageTitle = 'Manage Items - Store';
include '../../includes/header.php';

// Get categories for filtering
$stmt = $pdo->query("SELECT * FROM item_categories ORDER BY name ASC");
$categories = $stmt->fetchAll();
?>

<div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Inventory Items</h2>
        <p class="text-gray-500 mt-1">Manage your store stock and pricing</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <button onclick="openAiPopulateModal()" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:opacity-90 font-semibold shadow-md transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-10h-7z"></path></svg>
            AI Bulk Populate
        </button>
        <a href="create-item.php" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold shadow-md transition-all">
            + Add New Item
        </a>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="relative">
            <input type="text" id="searchInput" placeholder="Search by name or SKU..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none text-gray-600">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none text-gray-600">
            <option value="active">Active Items</option>
            <option value="archived">Archived Items</option>
            <option value="">All Status</option>
        </select>
        <button onclick="loadItems()" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 font-semibold transition-all">
            Filter
        </button>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 mb-8 flex items-center justify-between shadow-sm animate-pulse-slow">
    <div class="flex items-center gap-3">
        <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-full" id="selectedCount">0</span>
        <span class="text-red-800 font-semibold">Items Selected</span>
    </div>
    <div class="flex gap-3">
        <button onclick="bulkDelete()" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-bold transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Delete Selected
        </button>
        <button onclick="clearSelection()" class="px-4 py-2 text-gray-500 hover:text-gray-700 font-semibold">Cancel</button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-left">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" onclick="toggleSelectAll(this)">
                    </th>
                    <th class="px-4 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Item Name</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Price (₦)</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody" class="divide-y divide-gray-200">
                <!-- Loaded via AJAX -->
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                            <span class="text-gray-500 text-sm">Fetching items...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- AI Bulk Populate Modal -->
<div id="aiPopulateModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden animate-slide-up">
        <div class="p-8">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-6 mx-auto">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-10h-7z"></path></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 text-center mb-2">AI Inventory Generator</h3>
            <p class="text-gray-500 text-center mb-8 text-sm">Describe your business, and our AI will suggest a professional inventory list with pricing.</p>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 px-1">What do you sell?</label>
                    <input type="text" id="businessTypeInput" placeholder="e.g. Computer spares, Medical supplies..." 
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-purple-500 focus:ring-0 outline-none transition-all placeholder-gray-300">
                </div>
                <div class="grid grid-cols-2 gap-3 pt-4">
                    <button onclick="closeAiPopulateModal()" class="px-6 py-3 bg-gray-50 text-gray-600 rounded-xl hover:bg-gray-100 font-bold transition-all">Cancel</button>
                    <button id="aiGenBtn" onclick="generateAiInventory()" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold shadow-lg shadow-purple-200 transition-all flex items-center justify-center gap-2">
                        <span>Generate</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Preview Modal -->
<div id="aiPreviewModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-scale-up">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-purple-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900 uppercase tracking-tight">AI Suggested Inventory</h3>
                    <p class="text-xs text-purple-600 font-semibold uppercase tracking-wider">Review and confirm items below</p>
                </div>
            </div>
            <button onclick="closeAiPreviewModal()" class="text-gray-400 hover:text-gray-600 p-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-0">
            <table class="w-full">
                <thead class="bg-gray-50/80 sticky top-0 backdrop-blur-md">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">Item Details</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">Category</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-widest">Price (₦)</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-widest">Unit</th>
                    </tr>
                </thead>
                <tbody id="aiPreviewBody" class="divide-y divide-gray-100">
                    <!-- AI Data -->
                </tbody>
            </table>
        </div>
        
        <div class="p-6 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <div class="text-sm text-gray-500 italic">
                Tip: These are estimates. You can edit them after saving.
            </div>
            <div class="flex gap-3">
                <button onclick="closeAiPreviewModal()" class="px-6 py-3 text-gray-500 font-bold hover:text-gray-700">Discard</button>
                <button id="bulkSaveBtn" onclick="saveBulkItems()" class="px-8 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold shadow-lg shadow-purple-200 transition-all">
                    Save All to Inventory
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', loadItems);

// Debounce search
let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadItems, 500);
});

async function loadItems() {
    const search = document.getElementById('searchInput').value;
    const catId = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;

    try {
        const response = await fetch(`../../api/store/items.php?action=list&search=${encodeURIComponent(search)}&category_id=${catId}&status=${status}`);
        const res = await response.json();
        
        const tbody = document.getElementById('itemsTableBody');
        tbody.innerHTML = '';

        if (res.success && res.data.length > 0) {
            res.data.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors cursor-pointer group';
                tr.onclick = (e) => {
                    if (e.target.type === 'checkbox' || e.target.closest('button') || e.target.closest('a')) return;
                    const cb = tr.querySelector('.item-checkbox');
                    cb.checked = !cb.checked;
                    updateBulkActions();
                };
                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <input type="checkbox" class="item-checkbox w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" value="${item.id}" onchange="updateBulkActions()">
                    </td>
                    <td class="px-4 py-4 text-sm font-mono text-gray-500">${escapeHtml(item.sku || '-')}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-900">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-gray-500 truncate max-w-[200px]">${escapeHtml(item.description || 'No description')}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-md">
                            ${escapeHtml(item.category_name || 'Uncategorized')}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-bold text-gray-900">
                        ${formatCurrency(item.price)}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm ${item.stock_quantity <= item.minimum_stock ? 'text-red-600 font-bold' : 'text-gray-600'}">
                            ${formatNumber(item.stock_quantity)} ${escapeHtml(item.unit || '')}
                        </span>
                        ${item.stock_quantity <= item.minimum_stock && item.status === 'active' ? '<div class="text-[10px] text-red-500 font-semibold uppercase tracking-tighter">Low Stock</div>' : ''}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2.5 py-1 ${item.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} text-xs font-bold rounded-full uppercase">
                            ${item.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="edit-item.php?id=${item.id}" class="text-primary hover:text-blue-700 p-1" title="Edit Item">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <button onclick="duplicateItem(${JSON.stringify(item).replace(/'/g, "&apos;")})" class="text-secondary hover:text-green-700 p-1" title="Duplicate Item">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                            </button>
                            <button onclick="archiveItem(${item.id})" class="text-orange-600 hover:text-orange-800 p-1" title="Archive Item">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No items found matching your filters.</td></tr>';
        }
        updateBulkActions();
    } catch (err) {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-red-500 font-semibold">Error loading items. Please try again.</td></tr>';
    }
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bar = document.getElementById('bulkActionsBar');
    const count = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');
    const total = document.querySelectorAll('.item-checkbox').length;

    if (checkboxes.length > 0) {
        bar.classList.remove('hidden');
        count.innerText = checkboxes.length;
    } else {
        bar.classList.add('hidden');
    }

    if (total > 0 && selectAll) {
        selectAll.checked = checkboxes.length === total;
    }
}

function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkActions();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActions();
}

async function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    
    const result = await Swal.fire({
        title: 'Delete Selected Items?',
        text: `You are about to delete ${selected.length} items permanently. This cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete them!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('../../api/store/items.php?action=bulk_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: selected })
            });
            const res = await response.json();
            if (res.success) {
                Swal.fire('Deleted!', 'The items have been removed.', 'success');
                loadItems();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Failed to delete items', 'error');
        }
    }
}

async function archiveItem(id) {
    const result = await Swal.fire({
        title: 'Archive Item?',
        text: "It will be moved to the archive list.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e67e22',
        confirmButtonText: 'Yes, archive it!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('../../api/store/items.php?action=archive', {
                method: 'POST',
                body: JSON.stringify({ id })
            });
            const res = await response.json();
            if (res.success) {
                loadItems();
                Swal.fire('Archived!', 'Item has been archived.', 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Failed to archive item', 'error');
        }
    }
}

function duplicateItem(item) {
    const dupItem = {...item};
    delete dupItem.id;
    delete dupItem.sku;
    dupItem.name = dupItem.name + ' (Copy)';
    
    localStorage.setItem('duplicate_item', JSON.stringify(dupItem));
    window.location.href = 'create-item.php';
}

// Helpers - Redundant ones removed as they are now global in helpers.js

// AI Populate logic
let generatedItems = [];

function openAiPopulateModal() {
    document.getElementById('aiPopulateModal').classList.remove('hidden');
    document.getElementById('businessTypeInput').focus();
}

function closeAiPopulateModal() {
    document.getElementById('aiPopulateModal').classList.add('hidden');
}

function closeAiPreviewModal() {
    document.getElementById('aiPreviewModal').classList.add('hidden');
}

async function generateAiInventory() {
    const businessType = document.getElementById('businessTypeInput').value;
    if (!businessType) {
        Swal.fire('Required', 'Please enter your business type.', 'info');
        return;
    }

    const btn = document.getElementById('aiGenBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div> <span>Thinking...</span>';
    btn.disabled = true;

    try {
        const response = await fetch('../../api/ai/generate-store-inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ business_type: businessType })
        });
        const res = await response.json();

        if (res.success) {
            generatedItems = res.data;
            showPreview();
            closeAiPopulateModal();
        } else {
            Swal.fire('AI Error', res.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to connect to AI server.', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function showPreview() {
    const tbody = document.getElementById('aiPreviewBody');
    tbody.innerHTML = '';

    generatedItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-blue-50/50';
        tr.innerHTML = `
            <td class="px-3 py-3">
                <div class="font-bold text-gray-900">${escapeHtml(item.name)}</div>
                <div class="text-xs text-gray-500">${escapeHtml(item.description)}</div>
            </td>
            <td class="px-3 py-3">
                <span class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-600">${escapeHtml(item.category_name)}</span>
            </td>
            <td class="px-3 py-3 text-right font-mono font-bold">
                ₦${formatNumber(item.price)}
            </td>
            <td class="px-3 py-3 text-center text-gray-500 italic">
                ${escapeHtml(item.unit)}
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('aiPreviewModal').classList.remove('hidden');
}

async function saveBulkItems() {
    const btn = document.getElementById('bulkSaveBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    try {
        const response = await fetch('../../api/store/items.php?action=bulk_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: generatedItems })
        });
        const res = await response.json();

        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Inventory Populated!',
                text: `${generatedItems.length} items have been added to your store.`,
                confirmButtonText: 'Great!'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
            btn.disabled = false;
            btn.innerText = 'Save All to Inventory';
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to save items.', 'error');
        btn.disabled = false;
        btn.innerText = 'Save All to Inventory';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>


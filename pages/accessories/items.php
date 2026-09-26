<?php
require_once '../../config.php';
include '../../includes/session-check.php';
requirePermission('manage_accessories');

$pageTitle = 'Accessories Store - Internal Use';
include '../../includes/header.php';
?>

<div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Accessories Store</h2>
        <p class="text-gray-500 mt-1">Tools & consumables owned by the business for servicing and installations — not for sale</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <button onclick="openAiModal()" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:opacity-90 font-semibold shadow-md transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-10h-7z"></path></svg>
            AI Suggest
        </button>
        <button onclick="openItemModal()" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold shadow-md transition-all">
            + Add Accessory
        </button>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="relative">
            <input type="text" id="searchInput" placeholder="Search by name or code..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none text-gray-600">
            <option value="">All Categories</option>
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

<div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Accessory</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Condition</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody" class="divide-y divide-gray-200">
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                            <span class="text-gray-500 text-sm">Fetching accessories...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="itemModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white">
            <h3 id="itemModalTitle" class="text-xl font-bold text-gray-900">Add Accessory</h3>
            <button onclick="closeItemModal()" class="text-gray-400 hover:text-gray-600 p-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" id="f_id">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name *</label>
                <input type="text" id="f_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Code / SKU</label>
                <input type="text" id="f_code" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                <input type="text" id="f_category" list="categoryList" placeholder="e.g. Hand Tools" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                <datalist id="categoryList"></datalist>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
                <textarea id="f_description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
                <input type="text" id="f_unit" value="pcs" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit Cost (₦)</label>
                <input type="number" id="f_unit_cost" min="0" step="0.01" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stock Quantity</label>
                <input type="number" id="f_stock" min="0" step="1" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reorder Level</label>
                <input type="number" id="f_minimum" min="0" step="1" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Storage Location</label>
                <input type="text" id="f_location" placeholder="e.g. Shelf A1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Condition</label>
                <select id="f_condition" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    <option value="good">Good</option>
                    <option value="fair">Fair</option>
                    <option value="faulty">Faulty</option>
                </select>
            </div>
        </div>
        <div class="p-6 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white">
            <button onclick="closeItemModal()" class="px-6 py-3 text-gray-500 font-bold hover:text-gray-700">Cancel</button>
            <button id="saveItemBtn" onclick="saveItem()" class="px-8 py-3 bg-primary text-white rounded-xl hover:bg-blue-700 font-bold shadow-md transition-all">Save Accessory</button>
        </div>
    </div>
</div>

<!-- Issue / Return Modal -->
<div id="adjustModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 id="adjustTitle" class="text-xl font-bold text-gray-900">Issue Accessory</h3>
            <button onclick="closeAdjustModal()" class="text-gray-400 hover:text-gray-600 p-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="a_id">
            <input type="hidden" id="a_type" value="out">
            <p class="text-sm text-gray-600">Item: <strong id="a_name"></strong> (<span id="a_stock"></span> in stock)</p>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Quantity</label>
                <input type="number" id="a_quantity" min="1" step="1" value="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Technician</label>
                <input type="text" id="a_technician" placeholder="Who is taking it?" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Purpose / Job</label>
                <input type="text" id="a_purpose" placeholder="e.g. Installation at Lekki site" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Notes</label>
                <textarea id="a_notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"></textarea>
            </div>
        </div>
        <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="closeAdjustModal()" class="px-6 py-3 text-gray-500 font-bold hover:text-gray-700">Cancel</button>
            <button id="adjustBtn" onclick="submitAdjust()" class="px-8 py-3 bg-primary text-white rounded-xl hover:bg-blue-700 font-bold shadow-md transition-all">Confirm</button>
        </div>
    </div>
</div>

<!-- History Drawer -->
<div id="historyDrawer" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50" onclick="closeHistory()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Movement History</h3>
                <p id="historySub" class="text-sm text-gray-500"></p>
            </div>
            <button onclick="closeHistory()" class="text-gray-400 hover:text-gray-600 p-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div id="historyBody" class="flex-1 overflow-y-auto p-6 space-y-3"></div>
    </div>
</div>

<!-- AI Suggest Modal -->
<div id="aiModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="p-8">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-6 mx-auto">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-10h-7z"></path></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 text-center mb-2">AI Accessory Suggestions</h3>
            <p class="text-gray-500 text-center mb-8 text-sm">Describe what you service/install, and AI will suggest tools and consumables to stock.</p>
            <input type="text" id="aiBusinessInput" placeholder="e.g. Solar installations and inverter servicing..."
                class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-purple-500 focus:ring-0 outline-none transition-all placeholder-gray-300">
            <div class="grid grid-cols-2 gap-3 pt-4">
                <button onclick="closeAiModal()" class="px-6 py-3 bg-gray-50 text-gray-600 rounded-xl hover:bg-gray-100 font-bold transition-all">Cancel</button>
                <button id="aiGenBtn" onclick="generateAiAccessories()" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold shadow-lg shadow-purple-200 transition-all">Generate</button>
            </div>
        </div>
    </div>
</div>

<!-- AI Preview Modal -->
<div id="aiPreviewModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-purple-50/50">
            <div>
                <h3 class="text-xl font-bold text-gray-900 uppercase tracking-tight">AI Suggested Accessories</h3>
                <p class="text-xs text-purple-600 font-semibold uppercase tracking-wider">Review and confirm items below</p>
            </div>
            <button onclick="closeAiPreviewModal()" class="text-gray-400 hover:text-gray-600 p-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto p-0">
            <table class="w-full">
                <thead class="bg-gray-50/80 sticky top-0">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">Item Details</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">Category</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-widest">Unit Cost (₦)</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-widest">Stock</th>
                    </tr>
                </thead>
                <tbody id="aiPreviewBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <div class="p-6 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <div class="text-sm text-gray-500 italic">Tip: These are estimates. You can edit them after saving.</div>
            <div class="flex gap-3">
                <button onclick="closeAiPreviewModal()" class="px-6 py-3 text-gray-500 font-bold hover:text-gray-700">Discard</button>
                <button id="bulkSaveBtn" onclick="saveBulkItems()" class="px-8 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold shadow-lg shadow-purple-200 transition-all">Save All to Store</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => { loadItems(); loadCategories(); });

let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadItems, 500);
});

async function loadCategories() {
    try {
        const res = await (await fetch('../../api/accessories/items.php?action=categories')).json();
        if (res.success) {
            const sel = document.getElementById('categoryFilter');
            const dl = document.getElementById('categoryList');
            res.data.forEach(c => {
                const o = document.createElement('option');
                o.value = c; o.textContent = c;
                sel.appendChild(o);
                const d = document.createElement('option');
                d.value = c;
                dl.appendChild(d);
            });
        }
    } catch (err) { console.error(err); }
}

async function loadItems() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;

    try {
        const response = await fetch(`../../api/accessories/items.php?action=list&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&status=${status}`);
        const res = await response.json();
        const tbody = document.getElementById('itemsTableBody');
        tbody.innerHTML = '';

        if (res.success && res.data.length > 0) {
            res.data.forEach(item => {
                const low = parseInt(item.stock_quantity) <= parseInt(item.minimum_stock);
                const condColors = { good: 'bg-green-100 text-green-800', fair: 'bg-yellow-100 text-yellow-800', faulty: 'bg-red-100 text-red-800' };
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-4 py-4 text-sm font-mono text-gray-500">${escapeHtml(item.code || '-')}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-900">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-gray-500 truncate max-w-[220px]">${escapeHtml(item.description || 'No description')}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-md">${escapeHtml(item.category || 'General')}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">${escapeHtml(item.location || '-')}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm ${low && item.status === 'active' ? 'text-red-600 font-bold' : 'text-gray-600'}">
                            ${formatNumber(item.stock_quantity)} ${escapeHtml(item.unit || '')}
                        </span>
                        ${low && item.status === 'active' ? '<div class="text-[10px] text-red-500 font-semibold uppercase tracking-tighter">Low Stock</div>' : ''}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2.5 py-1 ${condColors[item.condition_status] || condColors.good} text-xs font-bold rounded-full uppercase">${escapeHtml(item.condition_status)}</span>
                    </td>
                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        <button onclick='openAdjustModal(${item.id}, "out", ${JSON.stringify(item.name)})' class="px-3 py-1.5 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 text-xs font-bold" title="Issue out">Issue</button>
                        <button onclick='openAdjustModal(${item.id}, "in", ${JSON.stringify(item.name)})' class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs font-bold" title="Restock / return">In</button>
                        <button onclick='openHistory(${item.id}, ${JSON.stringify(item.name)})' class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-xs font-bold" title="History">Log</button>
                        <button onclick='openItemModal(${item.id})' class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs font-bold" title="Edit">Edit</button>
                        <button onclick='archiveItem(${item.id})' class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-xs font-bold" title="Archive">Archive</button>
                    </td>
                `;
                tbody.appendChild(tr);
                tr.dataset.item = JSON.stringify(item);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No accessories found. Add your first one above.</td></tr>';
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-red-500 font-semibold">Error loading accessories. Please try again.</td></tr>';
    }
}

let editingId = null;

async function openItemModal(id = null) {
    editingId = id;
    document.getElementById('itemModalTitle').textContent = id ? 'Edit Accessory' : 'Add Accessory';
    document.getElementById('f_id').value = id || '';
    if (id) {
        const row = [...document.querySelectorAll('#itemsTableBody tr')].find(tr => {
            try { return tr.dataset.item && JSON.parse(tr.dataset.item).id == id; } catch (e) { return false; }
        });
        if (row && row.dataset.item) {
            const item = JSON.parse(row.dataset.item);
            document.getElementById('f_name').value = item.name || '';
            document.getElementById('f_code').value = item.code || '';
            document.getElementById('f_category').value = item.category || '';
            document.getElementById('f_description').value = item.description || '';
            document.getElementById('f_unit').value = item.unit || 'pcs';
            document.getElementById('f_unit_cost').value = item.unit_cost || 0;
            document.getElementById('f_stock').value = item.stock_quantity || 0;
            document.getElementById('f_minimum').value = item.minimum_stock || 0;
            document.getElementById('f_location').value = item.location || '';
            document.getElementById('f_condition').value = item.condition_status || 'good';
        }
    } else {
        ['f_name','f_code','f_category','f_description','f_unit_cost','f_stock','f_minimum','f_location'].forEach(fid => document.getElementById(fid).value = '');
        document.getElementById('f_unit').value = 'pcs';
        document.getElementById('f_condition').value = 'good';
    }
    document.getElementById('itemModal').classList.remove('hidden');
}

function closeItemModal() {
    document.getElementById('itemModal').classList.add('hidden');
}

async function saveItem() {
    const btn = document.getElementById('saveItemBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';
    try {
        const payload = {
            id: document.getElementById('f_id').value || null,
            name: document.getElementById('f_name').value,
            code: document.getElementById('f_code').value,
            category: document.getElementById('f_category').value,
            description: document.getElementById('f_description').value,
            unit: document.getElementById('f_unit').value,
            unit_cost: document.getElementById('f_unit_cost').value,
            stock_quantity: document.getElementById('f_stock').value,
            minimum_stock: document.getElementById('f_minimum').value,
            location: document.getElementById('f_location').value,
            condition_status: document.getElementById('f_condition').value
        };
        const response = await fetch('../../api/accessories/items.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
            closeItemModal();
            loadItems();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        alert('Failed to save accessory.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Save Accessory';
    }
}

function openAdjustModal(id, type, name) {
    const rows = document.querySelectorAll('#itemsTableBody tr');
    let stock = 0;
    rows.forEach(tr => {
        try {
            const item = tr.dataset.item ? JSON.parse(tr.dataset.item) : null;
            if (item && item.id == id) stock = item.stock_quantity;
        } catch (e) {}
    });
    document.getElementById('a_id').value = id;
    document.getElementById('a_type').value = type;
    document.getElementById('a_name').textContent = name;
    document.getElementById('a_stock').textContent = stock;
    document.getElementById('a_quantity').value = 1;
    document.getElementById('a_technician').value = '';
    document.getElementById('a_purpose').value = '';
    document.getElementById('a_notes').value = '';
    document.getElementById('adjustTitle').textContent = type === 'out' ? 'Issue Accessory' : 'Restock / Return';
    document.getElementById('adjustBtn').textContent = type === 'out' ? 'Issue Out' : 'Add Stock';
    document.getElementById('adjustModal').classList.remove('hidden');
}

function closeAdjustModal() {
    document.getElementById('adjustModal').classList.add('hidden');
}

async function submitAdjust() {
    const btn = document.getElementById('adjustBtn');
    btn.disabled = true;
    try {
        const response = await fetch('../../api/accessories/items.php?action=adjust', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: document.getElementById('a_id').value,
                type: document.getElementById('a_type').value,
                quantity: document.getElementById('a_quantity').value,
                technician: document.getElementById('a_technician').value,
                purpose: document.getElementById('a_purpose').value,
                notes: document.getElementById('a_notes').value
            })
        });
        const res = await response.json();
        if (res.success) {
            closeAdjustModal();
            loadItems();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        alert('Failed to update stock.');
    } finally {
        btn.disabled = false;
    }
}

async function openHistory(id, name) {
    document.getElementById('historySub').textContent = name;
    const body = document.getElementById('historyBody');
    body.innerHTML = '<p class="text-gray-500 text-sm">Loading...</p>';
    document.getElementById('historyDrawer').classList.remove('hidden');
    try {
        const res = await (await fetch(`../../api/accessories/items.php?action=transactions&id=${id}`)).json();
        body.innerHTML = '';
        if (res.success && res.data.length > 0) {
            res.data.forEach(t => {
                const isIn = t.type === 'in';
                const div = document.createElement('div');
                div.className = 'border border-gray-200 rounded-lg p-4';
                div.innerHTML = `
                    <div class="flex items-center justify-between mb-1">
                        <span class="px-2 py-0.5 ${isIn ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'} text-xs font-bold rounded-full uppercase">${isIn ? 'Stock In' : 'Issued Out'}</span>
                        <span class="text-xs text-gray-500">${escapeHtml(t.created_at || '')}</span>
                    </div>
                    <p class="text-sm font-bold text-gray-900">${isIn ? '+' : '-'}${escapeHtml(String(t.quantity))} → balance ${escapeHtml(String(t.balance_after))}</p>
                    <p class="text-xs text-gray-600 mt-1">${escapeHtml(t.technician || '')} ${escapeHtml(t.purpose || '')}</p>
                    ${t.notes ? `<p class="text-xs text-gray-500 italic mt-1">${escapeHtml(t.notes)}</p>` : ''}
                `;
                body.appendChild(div);
            });
        } else {
            body.innerHTML = '<p class="text-gray-500 text-sm">No movements recorded yet.</p>';
        }
    } catch (err) {
        body.innerHTML = '<p class="text-red-500 text-sm">Failed to load history.</p>';
    }
}

function closeHistory() {
    document.getElementById('historyDrawer').classList.add('hidden');
}

// AI Suggest logic
let generatedItems = [];

function openAiModal() {
    document.getElementById('aiModal').classList.remove('hidden');
}

function closeAiModal() {
    document.getElementById('aiModal').classList.add('hidden');
}

function closeAiPreviewModal() {
    document.getElementById('aiPreviewModal').classList.add('hidden');
}

async function generateAiAccessories() {
    const input = document.getElementById('aiBusinessInput').value.trim() || 'solar installations and electrical servicing';
    const btn = document.getElementById('aiGenBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div> <span>Thinking...</span>';
    btn.disabled = true;

    try {
        const response = await fetch('../../api/ai/generate-accessories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ business_type: input })
        });
        const res = await response.json();

        if (res.success) {
            generatedItems = res.data;
            showAiPreview();
            closeAiModal();
        } else {
            alert('AI Error: ' + res.message);
        }
    } catch (err) {
        alert('Error: Failed to connect to AI server.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function showAiPreview() {
    const tbody = document.getElementById('aiPreviewBody');
    tbody.innerHTML = '';

    generatedItems.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-blue-50/50';
        tr.innerHTML = `
            <td class="px-3 py-3">
                <div class="font-bold text-gray-900">${escapeHtml(item.name)}</div>
                <div class="text-xs text-gray-500">${escapeHtml(item.description || '')}</div>
            </td>
            <td class="px-3 py-3">
                <span class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-600">${escapeHtml(item.category || 'General')}</span>
            </td>
            <td class="px-3 py-3 text-right font-mono font-bold">₦${formatNumber(item.unit_cost || 0)}</td>
            <td class="px-3 py-3 text-center text-gray-600">${escapeHtml(String(item.stock_quantity ?? 0))} ${escapeHtml(item.unit || '')}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('aiPreviewModal').classList.remove('hidden');
}

async function archiveItem(id) {
    if (!confirm('Archive this accessory? It will be hidden from the active list.')) return;
    try {
        const response = await fetch('../../api/accessories/items.php?action=archive', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const res = await response.json();
        if (res.success) {
            loadItems();
        } else {
            alert('Error: ' + res.message);
        }
    } catch (err) {
        alert('Failed to archive accessory.');
    }
}

async function saveBulkItems() {    const btn = document.getElementById('bulkSaveBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    try {
        const response = await fetch('../../api/accessories/items.php?action=bulk_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: generatedItems })
        });
        const res = await response.json();

        if (res.success) {
            alert(`Saved! ${res.saved} accessories added to your store.`);
            closeAiPreviewModal();
            loadItems();
            loadCategories();
        } else {
            alert('Error: ' + res.message);
            btn.disabled = false;
            btn.innerText = 'Save All to Store';
        }
    } catch (err) {
        alert('Failed to save items.');
        btn.disabled = false;
        btn.innerText = 'Save All to Store';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

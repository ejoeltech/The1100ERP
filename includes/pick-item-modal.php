<!-- Store Product Picker Modal -->
<div id="productPickerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Select Item from Store</h3>
                <p class="text-sm text-gray-500">Search and select items to add to your quote</p>
            </div>
            <button onclick="closeProductPicker()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Toolbar -->
        <div class="p-4 border-b border-gray-100 bg-white grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
                <input type="text" id="pickerSearch" placeholder="Search items..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <select id="pickerCategory" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm text-gray-600">
                <option value="">All Categories</option>
                <!-- Categories loaded via JS -->
            </select>
            <div class="flex items-center justify-end">
                <span id="pickerCount" class="text-xs font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-full">0 items found</span>
            </div>
        </div>

        <!-- Items List -->
        <div class="flex-1 overflow-y-auto p-0">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left font-bold text-gray-700 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-700 uppercase tracking-wider">Item Name</th>
                        <th class="px-6 py-3 text-right font-bold text-gray-700 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-center font-bold text-gray-700 uppercase tracking-wider">Stock</th>
                        <th class="px-3 py-3 w-20"></th>
                    </tr>
                </thead>
                <tbody id="pickerItemsBody" class="divide-y divide-gray-200">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
            <button onclick="closeProductPicker()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 font-semibold transition-all">Close</button>
        </div>
    </div>
</div>

<script>
let storePickerCallback = null;

function openProductPicker(callback) {
    storePickerCallback = callback;
    document.getElementById('productPickerModal').classList.remove('hidden');
    loadPickerCategories();
    loadPickerItems();
    document.getElementById('pickerSearch').focus();
}

function closeProductPicker() {
    document.getElementById('productPickerModal').classList.add('hidden');
}

async function loadPickerCategories() {
    try {
        const response = await fetch(window.AppConfig.basePath + '/api/store/categories.php?action=list');
        const res = await response.json();
        const select = document.getElementById('pickerCategory');
        const currentVal = select.value;
        select.innerHTML = '<option value="">All Categories</option>';
        if (res.success) {
            res.data.forEach(cat => {
                select.innerHTML += `<option value="${cat.id}" ${cat.id == currentVal ? 'selected' : ''}>${cat.name}</option>`;
            });
        }
    } catch (e) {}
}

async function loadPickerItems() {
    const search = document.getElementById('pickerSearch').value;
    const catId = document.getElementById('pickerCategory').value;
    const tbody = document.getElementById('pickerItemsBody');
    
    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading items...</td></tr>';
    
    try {
        const response = await fetch(window.AppConfig.basePath + `/api/store/items.php?action=list&status=active&search=${encodeURIComponent(search)}&category_id=${catId}`);
        const res = await response.json();
        
        tbody.innerHTML = '';
        if (res.success && res.data.length > 0) {
            document.getElementById('pickerCount').innerText = `${res.data.length} items found`;
            res.data.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-blue-50 cursor-pointer transition-colors group';
                tr.onclick = () => selectItem(item);
                tr.innerHTML = `
                    <td class="px-6 py-4 font-mono text-gray-500">${item.sku || '-'}</td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-900">${item.name}</div>
                        <div class="text-xs text-gray-400">${item.category_name || ''}</div>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">
                        ₦${Number(item.price).toLocaleString('en-NG', { minimumFractionDigits: 2 })}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="${item.stock_quantity <= item.minimum_stock ? 'text-red-500 font-bold' : 'text-gray-600'}">
                            ${item.stock_quantity} ${item.unit || ''}
                        </span>
                    </td>
                    <td class="px-3 py-4 text-center">
                        <button class="bg-primary text-white text-xs px-3 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Add Item</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            document.getElementById('pickerCount').innerText = '0 items found';
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No items found matching your search.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-red-400">Error loading store items.</td></tr>';
    }
}

function selectItem(item) {
    if (storePickerCallback) {
        storePickerCallback(item);
    }
}

// Event listeners for search and category filter
document.getElementById('pickerSearch').addEventListener('input', () => {
    clearTimeout(window.pickerTimer);
    window.pickerTimer = setTimeout(loadPickerItems, 300);
});
document.getElementById('pickerCategory').addEventListener('change', loadPickerItems);
</script>

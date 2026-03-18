<?php
require_once '../../config.php';
include '../../includes/session-check.php';

$pageTitle = 'Manage Categories - ERP System';
include '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-3xl font-bold text-gray-900">Item Categories</h2>
    <button onclick="openModal()" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold shadow-md transition-all">
        + Add Category
    </button>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Name</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Description</th>
                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody id="categoriesTableBody" class="divide-y divide-gray-200">
            <!-- Loaded via AJAX -->
            <tr>
                <td colspan="3" class="px-6 py-8 text-center text-gray-500">Loading categories...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full animate-in fade-in zoom-in duration-200">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add New Category</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="categoryForm" class="space-y-5">
                <input type="hidden" id="categoryId">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name</label>
                    <input type="text" id="categoryName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" placeholder="e.g. Solar Panels">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea id="categoryDescription" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all resize-none" placeholder="Brief description of the category..."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="px-5 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" id="saveBtn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold shadow-sm transition-all">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', loadCategories);

async function loadCategories() {
    try {
        const response = await fetch('../../api/store/categories.php?action=list');
        const res = await response.json();
        
        const tbody = document.getElementById('categoriesTableBody');
        tbody.innerHTML = '';

        if (res.success && res.data.length > 0) {
            res.data.forEach(cat => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors';
                tr.innerHTML = `
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">${escapeHtml(cat.name)}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">${escapeHtml(cat.description || '-')}</td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-3">
                            <button onclick='editCategory(${JSON.stringify(cat)})' class="text-primary hover:text-blue-700 font-bold text-sm">Edit</button>
                            <span class="text-gray-300">|</span>
                            <button onclick="deleteCategory(${cat.id})" class="text-red-600 hover:text-red-800 font-bold text-sm">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No categories found</td></tr>';
        }
    } catch (err) {
        console.error(err);
    }
}

function openModal(cat = null) {
    const modal = document.getElementById('categoryModal');
    const title = document.getElementById('modalTitle');
    const form = document.getElementById('categoryForm');
    
    if (cat) {
        title.innerText = 'Edit Category';
        document.getElementById('categoryId').value = cat.id;
        document.getElementById('categoryName').value = cat.name;
        document.getElementById('categoryDescription').value = cat.description;
    } else {
        title.innerText = 'Add New Category';
        form.reset();
        document.getElementById('categoryId').value = '';
    }
    
    modal.classList.remove('hidden');
    document.getElementById('categoryName').focus();
}

function closeModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

document.getElementById('categoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const data = {
        id: document.getElementById('categoryId').value,
        name: document.getElementById('categoryName').value,
        description: document.getElementById('categoryDescription').value
    };

    try {
        const response = await fetch('../../api/store/categories.php?action=save', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const res = await response.json();
        if (res.success) {
            closeModal();
            loadCategories();
            Swal.fire({ icon: 'success', title: 'Saved!', showConfirmButton: false, timer: 1500 });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to save category', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Save Category';
    }
});

async function deleteCategory(id) {
    const result = await Swal.fire({
        title: 'Delete Category?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#red-600',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('../../api/store/categories.php?action=delete', {
                method: 'POST',
                body: JSON.stringify({ id })
            });
            const res = await response.json();
            if (res.success) {
                loadCategories();
                Swal.fire('Deleted!', 'Category has been deleted.', 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Failed to delete category', 'error');
        }
    }
}

function editCategory(cat) {
    openModal(cat);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php
// pages/archives.php
include '../includes/session-check.php';
requirePermission('view_reports'); // Assuming basic permission. Could be admin only.

$pageTitle = 'Archives - 1100ERP';
include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Archives</h1>
        <p class="text-gray-500 text-sm">View and restore archived items.</p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button onclick="switchTab('quote')" id="tab-quotes"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm active-tab">
                Archived Quotes
            </button>
            <button onclick="switchTab('invoice')" id="tab-invoices"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Archived Invoices
            </button>
            <button onclick="switchTab('receipt')" id="tab-receipts"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Archived Receipts
            </button>
        </nav>
    </div>

    <!-- Content Sections -->
    <div id="content-quotes" class="tab-content">
        <?php
        $stmt = $pdo->query("SELECT * FROM quotes WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        $quotes = $stmt->fetchAll();
        if ($quotes): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-12">
                                <input type="checkbox" id="selectAll-quotes" onchange="toggleSelectAll(event, 'quote')"
                                    class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quotes as $q): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                        class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                        value="<?= $q['id'] ?>" onchange="toggleCheckbox(<?= $q['id'] ?>, event, 'quote')">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $q['quote_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= $q['quote_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($q['customer_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($q['grand_total'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('quote', <?= $q['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-3">Restore</button>
                                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                        <button onclick="permanentDelete('quote', <?= $q['id'] ?>)"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived quotes found.</p>
        <?php endif; ?>
    </div>

    <div id="content-invoices" class="tab-content hidden">
        <?php
        // Fetch invoices with customer name
        $stmt = $pdo->query("
            SELECT i.*, c.customer_name 
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.deleted_at IS NOT NULL 
            ORDER BY i.deleted_at DESC
        ");
        $invoices = $stmt->fetchAll();
        if ($invoices): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-12">
                                <input type="checkbox" id="selectAll-invoices" onchange="toggleSelectAll(event, 'invoice')"
                                    class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                        class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                        value="<?= $inv['id'] ?>"
                                        onchange="toggleCheckbox(<?= $inv['id'] ?>, event, 'invoice')">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $inv['invoice_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= $inv['invoice_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($inv['customer_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($inv['grand_total'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('invoice', <?= $inv['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-3">Restore</button>
                                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                        <button onclick="permanentDelete('invoice', <?= $inv['id'] ?>)"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived invoices found.</p>
        <?php endif; ?>
    </div>

    <div id="content-receipts" class="tab-content hidden">
        <?php
        // Fetch receipts
        $stmt = $pdo->query("SELECT * FROM receipts WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        $receipts = $stmt->fetchAll();
        if ($receipts): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-12">
                                <input type="checkbox" id="selectAll-receipts" onchange="toggleSelectAll(event, 'receipt')"
                                    class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                        class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                        value="<?= $r['id'] ?>" onchange="toggleCheckbox(<?= $r['id'] ?>, event, 'receipt')">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['payment_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['reference_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['payment_method'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($r['amount_paid'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('receipt', <?= $r['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-3">Restore</button>
                                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                        <button onclick="permanentDelete('receipt', <?= $r['id'] ?>)"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived receipts found.</p>
        <?php endif; ?>
    </div>

</div>

<!-- Bulk Actions Bar (Fixed at bottom) -->
<div id="bulkActionBar" class="hidden fixed bottom-0 left-0 right-0 bg-white border-t-2 border-gray-300 shadow-lg z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
            </svg>
            <span class="font-semibold text-gray-900"><span id="selectedCount">0</span> item(s) selected</span>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="deselectAll()"
                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-semibold">Deselect All</button>
            <button onclick="bulkRestore()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                Restore Selected
            </button>
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <button onclick="showBulkPermanentDeleteConfirmation()"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                        </path>
                    </svg>
                    Permanently Delete
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal (Admin Only) -->
<?php if (function_exists('isAdmin') && isAdmin()): ?>
    <div id="permanentDeleteModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-red-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">⚠️ PERMANENT DELETE</h3>
            </div>
            <p class="text-gray-700 mb-4">You are about to <strong class="text-red-600">PERMANENTLY DELETE</strong> <strong
                    id="permanentDeleteCount">0</strong> item(s). This action <strong>CANNOT BE UNDONE</strong>.</p>
            <p class="text-sm text-gray-600 mb-4">To confirm, please type <strong class="text-red-600">PERMANENT
                    DELETE</strong> in the box below:</p>
            <input type="text" id="permanentDeleteInput" onkeyup="validatePermanentDeleteConfirmation()"
                placeholder="Type PERMANENT DELETE to confirm"
                class="w-full px-4 py-2 border-2 border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-6">
            <div class="flex gap-3 justify-end">
                <button onclick="hidePermanentDeleteConfirmation()"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Cancel</button>
                <button id="confirmPermanentDeleteBtn" onclick="executeBulkPermanentDelete()" disabled
                    class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed">Permanently
                    Delete</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    let selectedItems = new Map(); // { 'quote': Set(), 'invoice': Set(), 'receipt': Set() }
    let currentTab = 'quote'; // singular

    // Helper to convert singular to plural for DOM IDs
    function getPlural(type) {
        const plurals = {
            'quote': 'quotes',
            'invoice': 'invoices',
            'receipt': 'receipts'
        };
        return plurals[type] || type;
    }

    function switchTab(tabName) {
        currentTab = tabName;
        const plural = getPlural(tabName);

        // Hide all contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        // Show target content
        document.getElementById('content-' + plural).classList.remove('hidden');

        // Reset tab styles
        document.querySelectorAll('nav button').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Activate target tab
        const activeBtn = document.getElementById('tab-' + plural);
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
        activeBtn.classList.add('border-blue-500', 'text-blue-600');

        // Update bulk action bar
        updateBulkActionBar();
    }

    // Default tab style
    document.addEventListener('DOMContentLoaded', () => {
        selectedItems.set('quote', new Set());
        selectedItems.set('invoice', new Set());
        selectedItems.set('receipt', new Set());
        switchTab('quote');
    });

    function toggleCheckbox(id, event, type) {
        event.stopPropagation();

        if (event.target.checked) {
            selectedItems.get(type).add(id);
        } else {
            selectedItems.get(type).delete(id);
        }

        updateSelectAllCheckbox(type);
        updateBulkActionBar();
    }

    function toggleSelectAll(event, type) {
        const plural = getPlural(type);
        const checkboxes = document.querySelectorAll(`#content-${plural} .item-checkbox`);
        const isChecked = event.target.checked;

        checkboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
            const id = parseInt(checkbox.value);
            if (isChecked) {
                selectedItems.get(type).add(id);
            } else {
                selectedItems.get(type).delete(id);
            }
        });

        updateBulkActionBar();
    }

    function updateSelectAllCheckbox(type) {
        const plural = getPlural(type);
        const selectAllCheckbox = document.getElementById(`selectAll-${plural}`);
        const checkboxes = document.querySelectorAll(`#content-${plural} .item-checkbox`);

        if (checkboxes.length === 0) {
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
            return;
        }

        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const someChecked = Array.from(checkboxes).some(cb => cb.checked);

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
    }

    function updateBulkActionBar() {
        const bulkBar = document.getElementById('bulkActionBar');
        const selectedCount = document.getElementById('selectedCount');
        const currentSet = selectedItems.get(currentTab);

        if (currentSet && currentSet.size > 0) {
            bulkBar.classList.remove('hidden');
            selectedCount.textContent = currentSet.size;
        } else {
            bulkBar.classList.add('hidden');
        }
    }

    function deselectAll() {
        const plural = getPlural(currentTab);
        selectedItems.get(currentTab).clear();
        document.querySelectorAll(`#content-${plural} .item-checkbox`).forEach(cb => cb.checked = false);
        const selectAllCheckbox = document.getElementById(`selectAll-${plural}`);
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
        updateBulkActionBar();
    }

    async function unarchive(type, id) {
        if (!confirm('Restore this item from archives?')) return;

        try {
            const res = await fetch('../api/archive-item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, action: 'unarchive' })
            });
            const data = await res.json();

            if (data.success) {
                alert('Restored successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Network error');
        }
    }

    async function bulkRestore() {
        const currentSet = selectedItems.get(currentTab);
        if (!currentSet || currentSet.size === 0) {
            alert('Please select items to restore');
            return;
        }

        if (!confirm(`Restore ${currentSet.size} item(s) from archives?`)) return;

        const ids = Array.from(currentSet);

        try {
            const response = await fetch('../api/bulk-restore.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_type: currentTab,
                    ids: ids
                })
            });

            const result = await response.json();

            if (result.success) {
                alert(`Successfully restored ${result.restored_count} items`);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }

        } catch (error) {
            alert('Error restoring items: ' + error.message);
        }
    }

    async function permanentDelete(type, id) {
        const confirmation = prompt('⚠️ PERMANENT DELETE\n\nThis will permanently delete this item and CANNOT BE UNDONE.\n\nType "PERMANENT DELETE" to confirm:');

        if (confirmation !== 'PERMANENT DELETE') {
            if (confirmation !== null) {
                alert('Deletion cancelled. You must type exactly "PERMANENT DELETE" to confirm.');
            }
            return;
        }

        try {
            const res = await fetch('../api/permanent-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_type: type,
                    ids: [id]
                })
            });
            const data = await res.json();

            if (data.success) {
                alert('Item permanently deleted');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Network error');
        }
    }

    function showBulkPermanentDeleteConfirmation() {
        const currentSet = selectedItems.get(currentTab);
        if (!currentSet || currentSet.size === 0) {
            alert('Please select items to permanently delete');
            return;
        }

        const modal = document.getElementById('permanentDeleteModal');
        const countSpan = document.getElementById('permanentDeleteCount');
        const confirmInput = document.getElementById('permanentDeleteInput');
        const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');

        countSpan.textContent = currentSet.size;
        confirmInput.value = '';
        confirmBtn.disabled = true;

        modal.classList.remove('hidden');
    }

    function hidePermanentDeleteConfirmation() {
        document.getElementById('permanentDeleteModal').classList.add('hidden');
    }

    function validatePermanentDeleteConfirmation() {
        const input = document.getElementById('permanentDeleteInput');
        const btn = document.getElementById('confirmPermanentDeleteBtn');

        btn.disabled = input.value !== 'PERMANENT DELETE';
    }

    async function executeBulkPermanentDelete() {
        const currentSet = selectedItems.get(currentTab);
        const ids = Array.from(currentSet);
        const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<svg class="w-5 h-5 inline animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Deleting...';
        confirmBtn.disabled = true;

        try {
            const response = await fetch('../api/permanent-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_type: currentTab,
                    ids: ids
                })
            });

            const result = await response.json();

            if (result.success) {
                hidePermanentDeleteConfirmation();
                alert(`Successfully deleted ${result.deleted_count} items permanently`);
                location.reload();
            } else {
                alert('Error: ' + result.message);
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            }

        } catch (error) {
            alert('Error deleting items: ' + error.message);
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
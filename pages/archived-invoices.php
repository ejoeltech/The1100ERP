<?php
include '../includes/session-check.php';

$pageTitle = 'Archived Invoices - Bluedots Technologies';

// Fetch all archived invoices (deleted_at IS NOT NULL)
$stmt = $pdo->query("
    SELECT 
        i.id,
        i.invoice_number as document_number,
        i.invoice_title as quote_title,
        i.customer_name,
        i.salesperson,
        i.invoice_date as quote_date,
        i.grand_total,
        i.status,
        i.created_at,
        i.deleted_at
    FROM invoices i
    WHERE i.deleted_at IS NOT NULL
    ORDER BY i.deleted_at DESC
");

$quotes = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Archived Invoices</h2>
            <p class="text-sm text-gray-600 mt-1">Archived quotes can be restored or permanently deleted</p>
        </div>
        <a href="view-invoices.php" class="px-4 py-2 text-primary hover:bg-blue-50 rounded-lg font-semibold">
            ← Back to Active Invoices
        </a>
    </div>

    <?php if (empty($quotes)): ?>
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No archived invoices</h3>
            <p class="text-gray-500">Archived quotes will appear here</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(event)"
                                class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Quote #</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Title</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Balance Due</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Archived On</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Total</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox"
                                    class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                    value="<?php echo $quote['id']; ?>"
                                    onchange="toggleCheckbox(<?php echo $quote['id']; ?>, event)">
                            </td>
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-gray-500">
                                <?php echo htmlspecialchars($quote['document_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($quote['quote_title']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($quote['customer_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo date('d/m/Y H:i', strtotime($quote['deleted_at'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">
                                <?php echo formatNaira($quote['grand_total']); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="view-quote.php?id=<?php echo $quote['id']; ?>"
                                    class="text-primary hover:text-blue-700 font-semibold text-sm mr-3">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-sm text-gray-600">
            <p>Total Archived Invoices: <strong>
                    <?php echo count($quotes); ?>
                </strong></p>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Actions Bar for Archived Items -->
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
            <?php if (in_array($current_user['user_role'] ?? 'user', ['admin', 'super_admin'])): ?>
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

<!-- Permanent Delete Modal (Admin Only) -->
<?php if (in_array($current_user['user_role'] ?? 'user', ['admin', 'super_admin'])): ?>
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
                    id="permanentDeleteCount">0</strong> invoice(s). This action <strong>CANNOT BE UNDONE</strong>.</p>
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

<script src="../assets/js/bulk-actions.js"></script>
<script>initBulkActions('invoice');</script>

<?php include '../includes/footer.php'; ?>
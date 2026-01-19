<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

$pageTitle = 'View Invoices - Bluedots Technologies';

// Build role-based filter
$role_filter = getRoleFilter('d');

// Fetch invoices with receipt conversion status
$query = "
    SELECT 
        d.id,
        d.invoice_number as document_number,
        d.invoice_title as quote_title,
        d.customer_name,
        d.salesperson,
        d.invoice_date as quote_date,
        d.grand_total,
        d.amount_paid,
        d.balance_due,
        d.status,
        d.created_at,
        d.created_by,
        u.full_name as creator_name,
        rec.id AS receipt_id,
        rec.receipt_number AS receipt_number
    FROM invoices d
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN receipts rec ON rec.invoice_id = d.id AND rec.deleted_at IS NULL
    WHERE 1=1
    AND d.deleted_at IS NULL
    $role_filter
    ORDER BY d.created_at DESC
";

$stmt = $pdo->query($query);
$invoices = $stmt->fetchAll();

include '../includes/header.php';
?>

<?php if (isset($_GET['converted'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <p class="text-green-800 font-semibold">✓ Invoice created successfully from quote!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <p class="text-green-800 font-semibold">✓ Invoice updated successfully!</p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-3xl font-bold text-gray-900">All Invoices</h2>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                </path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No invoices yet</h3>
            <p class="text-gray-500 mb-6">Convert finalized quotes to create invoices</p>
            <a href="view-quotes.php"
                class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                View Quotes
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(event)"
                                class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Invoice #</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Title</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Total</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Balance</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location.href='view-invoice.php?id=<?php echo $invoice['id']; ?>'"
                            style="cursor: pointer;">
                            <td class="px-4 py-3" onclick="event.stopPropagation();">
                                <input type="checkbox"
                                    class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                    value="<?php echo $invoice['id']; ?>"
                                    onchange="toggleCheckbox(<?php echo $invoice['id']; ?>, event)">
                            </td>
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-green-600">
                                <?php echo htmlspecialchars($invoice['document_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($invoice['quote_title']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($invoice['customer_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo date('d/m/Y', strtotime($invoice['quote_date'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">
                                <?php echo formatNaira($invoice['grand_total']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right">
                                <?php
                                $balance = $invoice['balance_due'];
                                $color = $balance > 0 ? 'text-red-600' : 'text-green-600';
                                ?>
                                <span class="<?php echo $color; ?>">
                                    <?php echo formatNaira($balance); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col gap-2">
                                    <?php if ($invoice['status'] === 'finalized'): ?>
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Finalized</span>
                                    <?php else: ?>
                                        <span
                                            class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Draft</span>
                                    <?php endif; ?>

                                    <!-- Receipt Conversion Status -->
                                    <?php if ($invoice['receipt_id']): ?>
                                        <a href="view-receipt.php?id=<?php echo $invoice['receipt_id']; ?>"
                                            class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full hover:bg-blue-200"
                                            title="View Receipt <?php echo htmlspecialchars($invoice['receipt_number']); ?>">
                                            <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            → Receipt
                                        </a>
                                    <?php elseif ($invoice['balance_due'] <= 0 && $invoice['status'] === 'finalized'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                            Fully Paid
                                        </span>
                                    <?php elseif ($invoice['status'] === 'finalized'): ?>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                            Pending Payment
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>"
                                    class="text-primary hover:text-blue-700 font-semibold text-sm">
                                    View
                                </a>
                                <span class="text-gray-300">|</span>
                                <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>"
                                    class="text-yellow-600 hover:text-yellow-700 font-semibold text-sm">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-sm text-gray-600">
            <p>Total Invoices: <strong>
                    <?php echo count($invoices); ?>
                </strong></p>
        </div>
    <?php endif; ?>
</div>


<!-- Bulk Actions Bar -->
<div id="bulkActionBar" class="hidden fixed bottom-0 left-0 right-0 bg-white border-t-2 border-gray-300 shadow-lg z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                </path>
            </svg>
            <span class="font-semibold text-gray-900"><span id="selectedCount">0</span> item(s) selected</span>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="deselectAll()"
                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-semibold">Deselect All</button>
            <button id="bulkDownloadBtn" onclick="bulkDownloadPDFs()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center gap-2"><svg
                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                </svg>Download PDFs</button>
            <button onclick="showBulkArchiveConfirmation()"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center gap-2"><svg
                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    </path>
                </svg>Archive Selected</button>
        </div>
    </div>
</div>

<div id="archiveConfirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="bg-red-100 rounded-full p-3"><svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg></div>
            <h3 class="text-xl font-bold text-gray-900">Confirm Archive</h3>
        </div>
        <p class="text-gray-700 mb-4">You are about to archive <strong id="archiveCount">0</strong> invoice(s). This
            action cannot be undone.</p>
        <p class="text-sm text-gray-600 mb-4">To confirm, please type <strong class="text-red-600">ARCHIVE</strong> in
            the box below:</p>
        <input type="text" id="archiveConfirmInput" onkeyup="validateArchiveConfirmation()"
            placeholder="Type ARCHIVE to confirm"
            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-6">
        <div class="flex gap-3 justify-end">
            <button onclick="hideArchiveConfirmation()"
                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Cancel</button>
            <button id="confirmArchiveBtn" onclick="executeBulkArchive()" disabled
                class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed">Delete
                invoice</button>
        </div>
    </div>
</div>

<script src="../assets/js/bulk-actions.js"></script>
<script>initBulkActions('invoice');</script>

<?php include '../includes/footer.php'; ?>
<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

$pageTitle = 'View Quotes - Bluedots Technologies';

// Build role-based filter
$role_filter = getRoleFilter('d');

// Fetch quotes with conversion status (exclude deleted)
$query = "
    SELECT 
        d.id,
        d.quote_number as document_number,
        d.quote_title,
        d.customer_name,
        d.salesperson,
        d.quote_date,
        d.grand_total,
        d.status,
        d.created_at,
        d.created_by,
        u.full_name as creator_name,
        inv.id AS invoice_id,
        inv.invoice_number AS invoice_number
    FROM quotes d
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN invoices inv ON inv.quote_id = d.id AND inv.deleted_at IS NULL
    WHERE d.deleted_at IS NULL
    $role_filter
    ORDER BY d.created_at DESC
";

$stmt = $pdo->query($query);
$quotes = $stmt->fetchAll();

include '../includes/header.php';
?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Quote deleted successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Quote updated successfully!</p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <h2 class="text-3xl font-bold text-gray-900">All Quotes</h2>
        <a href="create-quote.php"
            class="w-full md:w-auto px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create New Quote
        </a>
    </div>

    <?php if (empty($quotes)): ?>

        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No quotes yet</h3>
            <p class="text-gray-500 mb-6">Create your first quote to get started</p>
            <a href="create-quote.php"
                class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Create Quote
            </a>
        </div>

    <?php else: ?>

        <!-- Quotes Table -->
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(event)"
                                class="w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary">
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Quote #</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Title</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Salesperson</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Total</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location.href='view-quote.php?id=<?php echo $quote['id']; ?>'"
                            style="cursor: pointer;">
                            <td class="px-4 py-3" onclick="event.stopPropagation();">
                                <input type="checkbox"
                                    class="item-checkbox w-4 h-4 text-primary rounded focus:ring-2 focus:ring-primary"
                                    value="<?php echo $quote['id']; ?>"
                                    onchange="toggleCheckbox(<?php echo $quote['id']; ?>, event)">
                            </td>

                            <td class="px-4 py-3 font-mono text-sm font-semibold text-primary">
                                <?php echo htmlspecialchars($quote['document_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($quote['quote_title']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($quote['customer_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($quote['salesperson']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo date('d/m/Y', strtotime($quote['quote_date'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">
                                <?php echo formatNaira($quote['grand_total']); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col gap-2">
                                    <?php if ($quote['status'] === 'finalized'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                            Finalized
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">
                                            Draft
                                        </span>
                                    <?php endif; ?>

                                    <!-- Conversion Status -->
                                    <?php if ($quote['invoice_id']): ?>
                                        <a href="view-invoice.php?id=<?php echo $quote['invoice_id']; ?>"
                                            class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full hover:bg-purple-200"
                                            title="View Invoice <?php echo htmlspecialchars($quote['invoice_number']); ?>">
                                            <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            → Invoice
                                        </a>
                                    <?php elseif ($quote['status'] === 'finalized'): ?>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                            Not Converted
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="view-quote.php?id=<?php echo $quote['id']; ?>"
                                        class="text-primary hover:text-blue-700 font-semibold text-sm" title="View Quote">
                                        View
                                    </a>

                                    <?php if ($quote['status'] === 'draft'): ?>
                                        <span class="text-gray-300">|</span>
                                        <a href="edit-quote.php?id=<?php echo $quote['id']; ?>"
                                            class="text-green-600 hover:text-green-700 font-semibold text-sm" title="Edit Draft">
                                            Edit
                                        </a>
                                    <?php endif; ?>

                                    <span class="text-gray-300">|</span>
                                    <a href="#"
                                        onclick="deleteQuote(<?php echo $quote['id']; ?>, '<?php echo htmlspecialchars($quote['document_number']); ?>'); return false;"
                                        class="text-red-600 hover:text-red-700 font-semibold text-sm" title="Delete Quote">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="mt-6 text-sm text-gray-600">
            <p>Total Quotes: <strong>
                    <?php echo count($quotes); ?>
                </strong></p>
        </div>

    <?php endif; ?>
</div>

<!-- Bulk Actions Bar (Fixed at bottom) -->
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
        <div class="flex flex-col md:flex-row items-center gap-3">
            <button onclick="deselectAll()"
                class="w-full md:w-auto px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-semibold">Deselect
                All</button>
            <button id="bulkDownloadBtn" onclick="bulkDownloadPDFs()"
                class="w-full md:w-auto px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                </svg>
                <span class="hide-mobile">Download PDFs</span>
                <span class="hide-desktop">Download</span>
            </button>
            <button onclick="showBulkArchiveConfirmation()"
                class="w-full md:w-auto px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    </path>
                </svg>
                <span class="hide-mobile">Archive Selected</span>
                <span class="hide-desktop">Archive</span>
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="archiveConfirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Confirm Archive</h3>
        </div>
        <p class="text-gray-700 mb-4">You are about to archive <strong id="archiveCount">0</strong> quote(s). This
            action
            cannot be undone.</p>
        <p class="text-sm text-gray-600 mb-4">To confirm, please type <strong class="text-red-600">DELETE</strong> in
            the box below:</p>
        <input type="text" id="archiveConfirmInput" onkeyup="validateArchiveConfirmation()"
            placeholder="Type ARCHIVE to confirm"
            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-6">
        <div class="flex gap-3 justify-end">
            <button onclick="hideArchiveConfirmation()"
                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Cancel</button>
            <button id="confirmArchiveBtn" onclick="executeBulkArchive()" disabled
                class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed">Delete
                Quotes</button>
        </div>
    </div>
</div>

<script src="../assets/js/bulk-actions.js"></script>
<script>initBulkActions('quote');</script>

<script>
    function deleteQuote(quoteId, quoteNumber) {
        if (confirm(`Are you sure you want to delete quote ${quoteNumber}?\n\nThis action cannot be undone.`)) {
            window.location.href = `../api/delete-quote.php?id=${quoteId}`;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
<?php
include '../includes/session-check.php';

$pageTitle = 'View Receipts - Bluedots Technologies';

// Build role-based filter
$role_filter = getRoleFilter('r');

// Fetch receipts
$query = "
    SELECT 
        r.id,
        r.receipt_number as document_number,
        COALESCE(i.invoice_title, CONCAT('Receipt ', r.receipt_number)) as quote_title,
        r.customer_name,
        r.payment_date as quote_date,
        r.amount_paid,
        r.payment_method,
        r.reference_number as payment_reference,
        r.created_at,
        r.created_by,
        u.full_name as creator_name
    FROM receipts r
    LEFT JOIN invoices i ON r.invoice_id = i.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE 1=1
    AND r.deleted_at IS NULL
    $role_filter
    ORDER BY r.created_at DESC
";

$stmt = $pdo->query($query);
$receipts = $stmt->fetchAll();

include '../includes/header.php';
?>

<!-- Success Messages -->
<?php if (isset($_GET['generated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Receipt generated successfully!</p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-3xl font-bold text-gray-900">All Receipts</h2>
        <div class="text-sm text-gray-600">
            <p>Payment confirmations and receipts</p>
        </div>
    </div>

    <?php if (empty($receipts)): ?>

        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No receipts yet</h3>
            <p class="text-gray-500 mb-6">Generate receipts when payments are received</p>
            <a href="view-invoices.php"
                class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                View Invoices
            </a>
        </div>

    <?php else: ?>

        <!-- Receipts Table -->
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Receipt #</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Title</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Amount Paid</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Method</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Reference</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location.href='view-receipt.php?id=<?php echo $receipt['id']; ?>'"
                            style="cursor: pointer;">

                            <td class="px-4 py-3 font-mono text-sm font-semibold text-purple-600">
                                <?php echo htmlspecialchars($receipt['document_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($receipt['quote_title']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($receipt['customer_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo date('d/m/Y', strtotime($receipt['quote_date'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-green-600 text-right">
                                <?php echo formatNaira($receipt['amount_paid']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($receipt['payment_method']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($receipt['payment_reference'] ?: '—'); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="view-receipt.php?id=<?php echo $receipt['id']; ?>"
                                    class="text-primary hover:text-blue-700 font-semibold text-sm">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <?php
            $total_receipts = count($receipts);
            $total_received = array_sum(array_column($receipts, 'amount_paid'));
            ?>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-gray-600">Total Receipts:</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $total_receipts; ?>
                </p>
            </div>
            <div class="bg-green-50 p-4 rounded">
                <p class="text-gray-600">Total Received:</p>
                <p class="text-2xl font-bold text-green-600">
                    <?php echo formatNaira($total_received); ?>
                </p>
            </div>
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
        <p class="text-gray-700 mb-4">You are about to archive <strong id="archiveCount">0</strong> receipt(s). This
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
                receipt</button>
        </div>
    </div>
</div>

<script src="../assets/js/bulk-actions.js"></script>
<script>initBulkActions('receipt');</script>

<?php include '../includes/footer.php'; ?>
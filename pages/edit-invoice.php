<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header('Location: view-invoices.php');
    exit;
}

$stmt = $pdo->prepare("SELECT *, invoice_number as document_number, invoice_title as quote_title, invoice_date as quote_date FROM invoices WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: view-invoices.php?error=Invoice not found');
    exit;
}

// Phase 4: Check if invoice has receipts - cannot edit if receipts exist
// Phase 4: Check if invoice has receipts - cannot edit if receipts exist
$stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE invoice_id = ? AND deleted_at IS NULL");
$stmt->execute([$invoice_id]);
$has_receipts = $stmt->fetchColumn() > 0;

if ($has_receipts) {
    header('Location: view-invoice.php?id=' . $invoice_id . '&error=Cannot edit invoice - receipts have been generated');
    exit;
}

// Phase 4: Check if finalized - only admins can edit finalized invoices
$is_finalized = $invoice['status'] === 'finalized';
if ($is_finalized) {
    requirePermission('edit_finalized');
}

// Check permission to edit
if (!canEditDocument($invoice)) {
    header('Location: view-invoice.php?id=' . $invoice_id . '&error=You do not have permission to edit this invoice');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$invoice_id]);
$line_items = $stmt->fetchAll();

$pageTitle = 'Edit Invoice ' . $invoice['document_number'] . ' - Bluedots Technologies';
include '../includes/header.php';
?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800 font-semibold">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </p>
    </div>
<?php endif; ?>

<?php if ($is_finalized): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-semibold text-yellow-800">
                    ⚠️ You are editing a FINALIZED invoice
                </p>
                <p class="text-xs text-yellow-700 mt-1">
                    All changes will be tracked in the audit log and document history.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Edit Invoice:
        <?php echo htmlspecialchars($invoice['document_number']); ?>
    </h2>

    <form id="invoiceForm" method="POST" action="../api/update-invoice.php">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">

        <!-- Invoice Header Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Invoice Number
                </label>
                <input type="text" value="<?php echo htmlspecialchars($invoice['document_number']); ?>" readonly
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-lg">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="quote_date" value="<?php echo $invoice['quote_date']; ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Invoice Title <span class="text-red-500">*</span>
                </label>
                <input type="text" name="quote_title" value="<?php echo htmlspecialchars($invoice['quote_title']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Customer Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="customer_name"
                    value="<?php echo htmlspecialchars($invoice['customer_name']); ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Salesperson <span class="text-red-500">*</span>
                </label>
                <input type="text" name="salesperson" value="<?php echo htmlspecialchars($invoice['salesperson']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Payment Terms
                </label>
                <input type="text" name="payment_terms"
                    value="<?php echo htmlspecialchars($invoice['payment_terms']); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Amount Paid <span class="text-red-500">*</span>
                </label>
                <input type="number" step="0.01" name="amount_paid" id="amountPaidInput"
                    value="<?php echo $invoice['amount_paid']; ?>" required onchange="updateBalanceDue()"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Status
                </label>
                <div
                    class="px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 font-medium capitalize">
                    <?php echo $invoice['status']; ?>
                </div>
                <input type="hidden" name="status" id="statusInput" value="<?php echo $invoice['status']; ?>">
            </div>
        </div>

        <!-- Line Items Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Line Items</h3>
                <button type="button" id="addLineBtn"
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Line Item
                </button>
            </div>

            <!-- Line Items Table -->
            <div class="overflow-x-auto">
                <table class="w-full border border-gray-300">
                    <thead>
                        <tr class="bg-primary text-white">
                            <th class="px-3 py-2 text-left text-sm font-semibold w-16">#</th>
                            <th class="px-3 py-2 text-left text-sm font-semibold w-24">Qty</th>
                            <th class="px-3 py-2 text-left text-sm font-semibold">Description</th>
                            <th class="px-3 py-2 text-left text-sm font-semibold w-40">Unit Price</th>
                            <th class="px-3 py-2 text-center text-sm font-semibold w-20">VAT?</th>
                            <th class="px-3 py-2 text-right text-sm font-semibold w-40">Line Total</th>
                            <th class="px-3 py-2 w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="lineItemsContainer">
                        <!-- Existing line items will be added here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals Section -->
        <div class="flex justify-end mb-8">
            <div class="w-full md:w-96 bg-gray-50 border border-gray-300 rounded-lg p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Subtotal:</span>
                        <span id="subtotalDisplay" class="text-lg font-bold text-gray-900">₦0.00</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">VAT (7.5%):</span>
                        <span id="vatDisplay" class="text-lg font-bold text-gray-900">₦0.00</span>
                    </div>
                    <div class="border-t border-gray-300 pt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xl font-bold text-gray-900">Grand Total:</span>
                            <span id="grandTotalDisplay" class="text-2xl font-bold text-primary">₦0.00</span>
                        </div>
                    </div>
                    <div class="border-t border-gray-300 pt-3">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-gray-700">Balance Due:</span>
                            <span id="balanceDueDisplay" class="text-lg font-bold text-red-600">₦0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for submission -->
                <input type="hidden" name="subtotal" id="subtotalInput">
                <input type="hidden" name="total_vat" id="vatInput">
                <input type="hidden" name="grand_total" id="grandTotalInput">
                <input type="hidden" name="balance_due" id="balanceDueInput">
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col md:flex-row gap-4 justify-end">
            <button type="button" onclick="window.location.href='view-invoice.php?id=<?php echo $invoice_id; ?>'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">
                Cancel
            </button>
            <button type="submit"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-center">
                Update Invoice
            </button>
            <?php if ($invoice['status'] === 'draft'): ?>
                <button type="button" onclick="confirmFinalize()"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-center">
                    ✓ Finalize Invoice
                </button>
            <?php endif; ?>
        </div>

    </form>
</div>

<!-- Load existing line items data -->
<script>
    const existingLineItems = <?php echo json_encode($line_items); ?>;
</script>

<!-- JavaScript -->
<script src="../assets/js/quote-form.js?v=2"></script>
<script src="../assets/js/edit-invoice.js?v=2"></script>

<script>
    function updateBalanceDue() {
        const grandTotal = parseFloat(document.getElementById('grandTotalInput').value) || 0;
        const amountPaid = parseFloat(document.getElementById('amountPaidInput').value) || 0;
        const balanceDue = grandTotal - amountPaid;

        document.getElementById('balanceDueDisplay').textContent = '₦' + balanceDue.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('balanceDueInput').value = balanceDue.toFixed(2);
    }

    // Override the calculateTotals function to also update balance
    const originalCalculateTotals = calculateTotals;
    calculateTotals = function () {
        originalCalculateTotals();
        updateBalanceDue();
    };

    function confirmFinalize() {
        if (confirm('Are you sure you want to finalize this invoice? It cannot be edited afterwards.')) {
            document.getElementById('statusInput').value = 'finalized';
            // Submit the form
            document.getElementById('invoiceForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
<?php
include '../includes/session-check.php';

$invoice_id = $_GET['invoice_id'] ?? null;

if (!$invoice_id) {
    header('Location: view-invoices.php?error=No invoice specified');
    exit;
}

// Fetch invoice
$stmt = $pdo->prepare("
    SELECT *, invoice_number as document_number FROM invoices 
    WHERE id = ? 
    AND status = 'finalized'
    AND deleted_at IS NULL
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: view-invoices.php?error=Invoice not found or not finalized');
    exit;
}

// Check if receipt already exists - REMOVED to allow multiple partial payments
/* 
$stmt = $pdo->prepare("
    SELECT id FROM receipts 
    WHERE invoice_id = ? 
");
$stmt->execute([$invoice_id]);
if ($stmt->fetch()) {
    header('Location: view-invoices.php?error=Receipt already exists for this invoice');
    exit;
}
*/

$pageTitle = 'Record Payment - Bluedots Technologies';

include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-3xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Record Payment</h2>

    <!-- Invoice Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <h3 class="font-bold text-gray-900 mb-3">Invoice Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-600">Invoice Number:</p>
                <p class="font-mono font-bold text-green-600">
                    <?php echo htmlspecialchars($invoice['document_number']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Customer:</p>
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($invoice['customer_name']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Invoice Total:</p>
                <p class="font-bold text-gray-900">
                    <?php echo formatNaira($invoice['grand_total']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Balance Due:</p>
                <p class="font-bold text-red-600">
                    <?php echo formatNaira($invoice['balance_due']); ?>
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="../api/generate-receipt.php">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">

        <!-- Payment Details -->
        <div class="space-y-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Receipt Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select name="payment_method" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">-- Select Payment Method --</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cash">Cash</option>
                        <option value="Cheque">Cheque</option>
                        <option value="POS">POS (Card Payment)</option>
                        <option value="Online Payment">Online Payment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Amount Paid <span class="text-red-500">*</span>
                </label>
                <input type="number" name="amount_paid" min="0.01" step="0.01"
                    max="<?php echo $invoice['balance_due']; ?>" value="<?php echo $invoice['balance_due']; ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <p class="text-xs text-gray-500 mt-1">
                    Maximum:
                    <?php echo formatNaira($invoice['balance_due']); ?> (Outstanding balance)
                </p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Payment Reference / Transaction ID
                </label>
                <input type="text" name="payment_reference"
                    placeholder="e.g., Bank reference number, cheque number, etc."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea name="notes" rows="3" placeholder="Any additional notes about this payment..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary resize-none"></textarea>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col md:flex-row gap-4 justify-end">
            <button type="button" onclick="window.location.href='view-invoice.php?id=<?php echo $invoice['id']; ?>'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">
                Cancel
            </button>
            <button type="submit"
                class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Record Payment
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
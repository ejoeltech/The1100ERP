<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

// Get quote ID
$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    header('Location: view-quotes.php?error=No quote specified');
    exit;
}

// Fetch quote
$stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch();

if (!$quote) {
    header('Location: view-quotes.php?error=Quote not found');
    exit;
}

// Check if finalized - only admins can edit finalized quotes
$is_finalized = $quote['status'] === 'finalized';
if ($is_finalized) {
    // Phase 4: Allow admins to edit finalized documents
    requirePermission('edit_finalized');
}

// Check permission to edit
if (!canEditDocument($quote)) {
    header('Location: view-quote.php?id=' . $quote_id . '&error=You do not have permission to edit this quote');
    exit;
}

// Fetch line items
$stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
$stmt->execute([$quote_id]);
$line_items = $stmt->fetchAll();

$pageTitle = 'Edit Quote ' . $quote['document_number'] . ' - Bluedots Technologies';

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
                    ⚠️ You are editing a FINALIZED quote
                </p>
                <p class="text-xs text-yellow-700 mt-1">
                    All changes will be tracked in the audit log and document history.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-3xl font-bold text-gray-900">Edit Quote:
            <?php echo htmlspecialchars($quote['document_number']); ?>
        </h2>
        <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">Draft</span>
    </div>

    <form id="quoteForm" method="POST" action="../api/update-quote.php">
        <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">

        <!-- Quote Header Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Quote Number
                </label>
                <input type="text" value="<?php echo htmlspecialchars($quote['document_number']); ?>" readonly
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-lg">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="quote_date" value="<?php echo $quote['quote_date']; ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Quote Title <span class="text-red-500">*</span>
                </label>
                <input type="text" name="quote_title" value="<?php echo htmlspecialchars($quote['quote_title']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Customer Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="customer_name" value="<?php echo htmlspecialchars($quote['customer_name']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Salesperson <span class="text-red-500">*</span>
                </label>
                <input type="text" name="salesperson" value="<?php echo htmlspecialchars($quote['salesperson']); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Payment Terms
                </label>
                <input type="text" name="payment_terms" value="<?php echo htmlspecialchars($quote['payment_terms']); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
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
                        <!-- Existing line items will be loaded here -->
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
                </div>

                <input type="hidden" name="subtotal" id="subtotalInput">
                <input type="hidden" name="total_vat" id="vatInput">
                <input type="hidden" name="grand_total" id="grandTotalInput">
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col md:flex-row gap-4 justify-end">
            <button type="button" onclick="window.location.href='view-quotes.php'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">
                Cancel
            </button>
            <button type="submit" name="status" value="draft"
                class="px-6 py-3 border border-primary text-primary rounded-lg hover:bg-blue-50 font-semibold text-center">
                Update Draft
            </button>
            <button type="submit" name="status" value="finalized"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-center">
                Update & Finalize
            </button>
        </div>
    </form>
</div>

<!-- Load existing line items data -->
<script>
    const existingLineItems = <?php echo json_encode($line_items); ?>;
</script>

<!-- JavaScript -->
<script src="../assets/js/quote-form.js?v=2"></script>
<script src="../assets/js/edit-quote.js?v=2"></script>

<?php include '../includes/footer.php'; ?>
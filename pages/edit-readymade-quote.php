<?php
include '../includes/session-check.php';
require_once '../config.php';

$template_id = $_GET['id'] ?? null;

if (!$template_id) {
    header('Location: readymade-quotes.php');
    exit;
}

// Fetch template
$stmt = $pdo->prepare("SELECT * FROM readymade_quote_templates WHERE id = ? AND is_active = 1");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    header('Location: readymade-quotes.php?error=Template not found');
    exit;
}

// Fetch template items
$stmt = $pdo->prepare("SELECT * FROM readymade_quote_template_items WHERE template_id = ? ORDER BY item_number");
$stmt->execute([$template_id]);
$existingLineItems = $stmt->fetchAll();

$pageTitle = 'Edit Readymade Quote - ERP System';
include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-900">Edit Readymade Quote Template</h2>
        <p class="text-sm text-gray-600 mt-1">Update your reusable quote template</p>
    </div>

    <form id="readymadeQuoteForm" method="POST" action="../api/update-readymade-quote.php">
        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">

        <!-- Template Info Section -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Template Information</h3>

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Template Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="template_name" required
                        value="<?php echo htmlspecialchars($template['template_name']); ?>"
                        placeholder="e.g., Website Development Package"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Default Project Title
                    </label>
                    <input type="text" name="quote_title"
                        value="<?php echo htmlspecialchars($template['default_project_title'] ?? ''); ?>"
                        placeholder="e.g., Website Development"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Description (Optional)
                    </label>
                    <textarea name="template_description" rows="2"
                        placeholder="Brief description of what this template is for"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($template['description']); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Payment Terms -->
        <div class="mb-8">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Default Payment Terms
            </label>
            <textarea name="payment_terms" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($template['payment_terms']); ?></textarea>
        </div>

        <!-- Line Items Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">Line Items</h3>
                <button type="button" id="addLineBtn"
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Item
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
                        <!-- Line items will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals Display -->
        <div class="bg-gray-50 rounded-lg p-6 mb-8">
            <div class="max-w-md ml-auto space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Subtotal:</span>
                    <span id="subtotalDisplay" class="font-bold text-gray-900">₦0.00</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">VAT (7.5%):</span>
                    <span id="vatDisplay" class="font-bold text-gray-900">₦0.00</span>
                </div>
                <div class="flex justify-between items-center pt-3 border-t-2 border-gray-300">
                    <span class="text-lg font-bold text-gray-900">Estimated Total:</span>
                    <span id="grandTotalDisplay" class="text-xl font-bold text-primary">₦0.00</span>
                </div>
            </div>

            <input type="hidden" name="subtotal" id="subtotalInput">
            <input type="hidden" name="total_vat" id="vatInput">
            <input type="hidden" name="grand_total" id="grandTotalInput">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 justify-end">
            <button type="button" onclick="window.location.href='readymade-quotes.php'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                Cancel
            </button>
            <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Update Template
            </button>
        </div>

    </form>
</div>

<script>
    // Existing line items data
    const existingLineItems = <?php echo json_encode($existingLineItems); ?>;
</script>
<script src="../assets/js/quote-form.js?v=3"></script>
<script src="../assets/js/edit-quote.js?v=4"></script>

<?php include '../includes/footer.php'; ?>
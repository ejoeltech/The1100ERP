<?php
include '../includes/session-check.php';

$document_id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? null;

if (!$document_id || !$document_type) {
    header('Location: ../dashboard.php?error=Invalid document');
    exit;
}

// Fetch document
// Fetch document
$document = null;
if ($document_type === 'quote') {
    $stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
} elseif ($document_type === 'invoice') {
    $stmt = $pdo->prepare("SELECT *, invoice_number as document_number, invoice_title as quote_title FROM invoices WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
} elseif ($document_type === 'receipt') {
    $stmt = $pdo->prepare("
        SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title, r.amount_paid as grand_total 
        FROM receipts r 
        LEFT JOIN invoices i ON r.invoice_id = i.id 
        WHERE r.id = ? AND r.deleted_at IS NULL
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
}

if (!$document) {
    header('Location: ../dashboard.php?error=Document not found');
    exit;
}

// Check permissions
if (function_exists('canViewDocument') && !canViewDocument($document)) {
    die('Access Denied: You cannot view this document');
}

$pageTitle = 'Email Document - ERP System';

// Get document type display name
$type_names = [
    'quote' => 'Quote',
    'invoice' => 'Invoice',
    'receipt' => 'Receipt'
];
$type_display = $type_names[$document_type];

include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Email
        <?php echo $type_display; ?>
    </h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Document Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <h3 class="font-bold text-gray-900 mb-3">Document Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-600">Document Number:</p>
                <p class="font-mono font-bold text-primary">
                    <?php echo htmlspecialchars($document['document_number']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Customer:</p>
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($document['customer_name']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Title:</p>
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($document['quote_title']); ?>
                </p>
            </div>
            <div>
                <p class="text-gray-600">Amount:</p>
                <p class="font-bold text-gray-900">
                    <?php echo formatNaira($document['grand_total']); ?>
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="../api/send-email.php">
        <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
        <input type="hidden" name="document_type" value="<?php echo $document_type; ?>">

        <div class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Recipient Email <span class="text-red-500">*</span>
                </label>
                <input type="email" name="recipient_email" required placeholder="customer@example.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <p class="text-xs text-gray-500 mt-1">Email address where the document will be sent</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Subject <span class="text-red-500">*</span>
                </label>
                <input type="text" name="email_subject" required
                    value="<?php echo $type_display; ?> <?php echo htmlspecialchars($document['document_number']); ?> - ERP System"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Additional Message (Optional)
                </label>
                <textarea name="additional_message" rows="4" placeholder="Add any additional notes or instructions..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary resize-none"></textarea>
            </div>

            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="bcc_salesperson" value="1" checked
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <span class="text-sm font-semibold text-gray-700">Send a copy to salesperson (
                        <?php echo htmlspecialchars($document['salesperson'] ?? 'N/A'); ?>)
                    </span>
                </label>
            </div>

            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="attach_pdf" value="1" checked
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <span class="text-sm font-semibold text-gray-700">Attach PDF document</span>
                </label>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">⚠️ Note:</h4>
                <p class="text-sm text-gray-700">
                    The email will be sent with a professional template and will include the document as a PDF
                    attachment (if selected).
                    Please verify the recipient email address before sending.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 justify-end mt-8">
            <button type="button" onclick="window.history.back()"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                Cancel
            </button>
            <button type="submit"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                    </path>
                </svg>
                Send Email
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
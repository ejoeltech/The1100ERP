<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

// Get quote ID from URL
$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    header('Location: view-quotes.php');
    exit;
}

// Fetch quote details
$stmt = $pdo->prepare("
    SELECT *, quote_number as document_number FROM quotes WHERE id = ?
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch();

if (!$quote) {
    header('Location: view-quotes.php?error=Quote not found');
    exit;
}

// Fetch line items
$stmt = $pdo->prepare("
    SELECT * FROM quote_line_items 
    WHERE quote_id = ? 
    ORDER BY item_number
");
$stmt->execute([$quote_id]);
$line_items = $stmt->fetchAll();

$pageTitle = 'Quote ' . $quote['document_number'] . ' - ' . COMPANY_NAME;

include '../includes/header.php';
?>

<!-- Success Messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-green-800 font-semibold">Quote saved successfully!</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-green-800 font-semibold">Quote updated successfully!</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['duplicated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-green-800 font-semibold">Quote duplicated successfully!</p>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="flex flex-wrap gap-4 mb-6 no-print">
    <!-- Print Button -->
    <button onclick="window.print()"
        class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 256 256">
            <path
                d="M224,96V192H192v32H64V192H32V96A32,32,0,0,1,64,64H192A32,32,0,0,1,224,96ZM80,208h96V160H80Zm128-80H48v48H64V144a16,16,0,0,1,16-16h96a16,16,0,0,1,16,16v32h16Z">
            </path>
        </svg>
        Print Quote
    </button>

    <!-- Export Dropdown Button -->
    <div class="relative group">
        <button
            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
            </svg>
            Export
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div
            class="hidden group-hover:block absolute top-full left-0 mt-1 bg-white shadow-lg rounded-lg py-2 min-w-[180px] z-50">
            <a href="../api/export-pdf.php?id=<?php echo $quote['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                Export as PDF
            </a>
            <a href="../api/export-quote-jpeg.php?id=<?php echo $quote['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                </svg>
                Export as JPEG
            </a>

            <a href="../api/export-quote-html.php?id=<?php echo $quote['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                Export as HTML
            </a>
        </div>
    </div>

    <!-- Duplicate Button -->
    <a href="../api/duplicate-quote.php?id=<?php echo $quote['id']; ?>"
        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
            </path>
        </svg>
        Duplicate
    </a>

    <!-- Convert to Invoice Button (Finalized Quotes Only) -->
    <?php if ($quote['status'] === 'finalized'): ?>
        <?php
        // Check if already converted
        $stmt = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE quote_id = ?");
        $stmt->execute([$quote['id']]);
        $existing_invoice = $stmt->fetch();
        ?>

        <?php if (!$existing_invoice): ?>
            <form method="GET" action="../api/convert-to-invoice.php" style="display: inline-block;"
                onsubmit="return confirm('Convert this quote to an invoice? This will create a new invoice based on this quote.');">
                <input type="hidden" name="id" value="<?php echo $quote['id']; ?>">
                <button type="submit"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center gap-2 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                    Convert to Invoice
                </button>
            </form>
        <?php else: ?>
            <a href="view-invoice.php?id=<?php echo $existing_invoice['id']; ?>"
                class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                View Invoice (<?php echo $existing_invoice['invoice_number']; ?>)
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Edit Button (Drafts Only) -->
    <?php if ($quote['status'] === 'draft'): ?>
        <a href="edit-quote.php?id=<?php echo $quote['id']; ?>"
            class="px-6 py-3 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 font-semibold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                </path>
            </svg>
            Edit Draft
        </a>
    <?php endif; ?>

    <!-- Back Button -->
    <a href="view-quotes.php"
        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
        ← Back to Quotes
    </a>
</div>

<!-- Quote Display -->
<div id="printableQuote" class="bg-white rounded-lg shadow-md p-4 md:p-8 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="text-center mb-8 pb-6 border-b-2 border-gray-200">
        <div class="flex justify-center items-center gap-2 mb-3">
            <?php if (COMPANY_LOGO && file_exists(__DIR__ . '/../' . COMPANY_LOGO)): ?>
                <img src="../<?php echo COMPANY_LOGO; ?>" alt="<?php echo COMPANY_NAME; ?>" class="h-28 object-contain">
            <?php else: ?>
                <div class="flex flex-col items-center">
                    <h1 class="text-3xl font-bold tracking-tight mb-1"><?php echo COMPANY_NAME; ?></h1>
                    <p class="text-[9px] tracking-[0.3em] uppercase font-bold text-gray-600">TECHNOLOGIES</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-xs mt-4 space-y-1 text-gray-700">
            <p><strong>Contact Address:</strong>
                <?php echo COMPANY_ADDRESS; ?>
            </p>
            <p>
                <strong>Phone:</strong>
                <?php echo COMPANY_PHONE; ?> |
                <strong>Email:</strong>
                <?php echo COMPANY_EMAIL; ?> |
                <?php echo COMPANY_WEBSITE; ?>
            </p>
        </div>
    </div>

    <!-- Document Title -->
    <div class="text-center mb-8">
        <h2 class="text-4xl font-serif font-bold mb-2 text-gray-900">QUOTE</h2>
        <p class="text-gray-600 italic">
            <?php echo htmlspecialchars($quote['quote_title']); ?>
        </p>
    </div>

    <!-- Quote Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div>
            <p class="text-sm font-bold text-gray-700 mb-2">Quote For:</p>
            <div class="border border-gray-300 p-3 rounded bg-gray-50">
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($quote['customer_name']); ?>
                </p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Quote Number:</span>
                <span class="font-mono font-bold text-primary">
                    <?php echo htmlspecialchars($quote['document_number']); ?>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Date:</span>
                <span class="text-gray-900">
                    <?php echo date('d/m/Y', strtotime($quote['quote_date'])); ?>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Salesperson:</span>
                <span class="text-gray-900">
                    <?php echo htmlspecialchars($quote['salesperson']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Line Items -->
    <div class="mb-8">
        <table class="w-full border border-gray-300">
            <thead>
                <tr class="bg-primary text-white">
                    <th class="px-3 py-2 text-left text-sm font-semibold">#</th>
                    <th class="px-3 py-2 text-center text-sm font-semibold">Qty</th>
                    <th class="px-3 py-2 text-left text-sm font-semibold">Description</th>
                    <th class="px-3 py-2 text-right text-sm font-semibold">Unit Price</th>
                    <th class="px-3 py-2 text-center text-sm font-semibold">VAT</th>
                    <th class="px-3 py-2 text-right text-sm font-semibold">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($line_items as $item): ?>
                    <tr class="border-b border-gray-200">
                        <td class="px-3 py-2 text-gray-700">
                            <?php echo $item['item_number']; ?>
                        </td>
                        <td class="px-3 py-2 text-center text-gray-900">
                            <?php echo number_format($item['quantity'], 2); ?>
                        </td>
                        <td class="px-3 py-2 text-gray-900">
                            <?php echo htmlspecialchars($item['description']); ?>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900">
                            <?php echo formatNaira($item['unit_price']); ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <?php if ($item['vat_applicable']): ?>
                                <span class="text-green-600 font-bold">✓</span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900">
                            <?php echo formatNaira($item['line_total']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="flex justify-end mb-8">
        <div class="w-80 space-y-2">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="font-semibold text-gray-700">Subtotal:</span>
                <span class="text-lg font-bold text-gray-900">
                    <?php echo formatNaira($quote['subtotal']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="font-semibold text-gray-700">VAT (7.5%):</span>
                <span class="text-lg font-bold text-gray-900">
                    <?php echo formatNaira($quote['total_vat']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 bg-primary text-white px-4 rounded">
                <span class="text-lg font-bold">Grand Total:</span>
                <span class="text-xl font-bold">
                    <?php echo formatNaira($quote['grand_total']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Payment Terms -->
    <div class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded">
        <p class="text-sm font-semibold text-gray-700 mb-1">Payment Terms:</p>
        <p class="text-gray-900">
            <?php echo htmlspecialchars($quote['payment_terms']); ?>
        </p>
    </div>

    <!-- Footer -->
    <div class="border-t-2 border-gray-200 pt-6">
        <p class="text-center font-serif italic font-bold mb-6 text-gray-700">
            We look forward to working with you! Thank you
        </p>

        <div class="bg-primary text-white text-center py-2 text-sm font-bold uppercase tracking-wider mb-2">
            MAKE ALL PAYMENTS IN FAVOUR OF: <?php echo htmlspecialchars(COMPANY_NAME); ?>
        </div>

        <div class="bg-blue-100 flex justify-around py-4 px-6 border-x border-gray-300 mb-2">
            <?php
            // Fetch dynamically configured bank accounts
            $bankAccounts = getBankAccountsForDisplay();
            if (!empty($bankAccounts)):
                foreach ($bankAccounts as $account):
                    ?>
                    <div class="text-center">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($account['bank_name']); ?></p>
                        <p class="text-sm text-gray-700">Account No:
                            <?php echo htmlspecialchars($account['account_number']); ?>
                        </p>
                    </div>
                    <?php
                endforeach;
            else:
                ?>
                <div class="text-center w-full">
                    <p class="text-sm text-gray-600 italic">Please contact us for payment details.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-primary text-white text-right py-2 px-4 text-xs italic">
            Quote prepared by:
            <?php echo htmlspecialchars($quote['salesperson']); ?>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
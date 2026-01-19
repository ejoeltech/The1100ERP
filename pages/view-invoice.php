<?php include '../includes/session-check.php'; ?>
<?php
require_once '../config.php';

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header('Location: view-invoices.php');
    exit;
}

$stmt = $pdo->prepare("SELECT *, invoice_number as document_number, invoice_title as quote_title FROM invoices WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: view-invoices.php?error=Invoice not found');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$invoice_id]);
$line_items = $stmt->fetchAll();

// Fetch parent quote
$parent_quote = null;
if ($invoice['quote_id']) {
    $stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ?");
    $stmt->execute([$invoice['quote_id']]);
    $parent_quote = $stmt->fetch();
}

// Phase 6: Fetch payment history (receipts)
$stmt = $pdo->prepare("
    SELECT *, receipt_number as document_number FROM receipts 
    WHERE invoice_id = ? AND deleted_at IS NULL
    ORDER BY created_at ASC
");
$stmt->execute([$invoice_id]);
$receipts = $stmt->fetchAll();

// Calculate total paid from receipts
$total_paid_from_receipts = 0;
foreach ($receipts as $receipt) {
    $total_paid_from_receipts += $receipt['amount_paid'];
}

// Calculate actual balance
$actual_balance = $invoice['grand_total'] - $total_paid_from_receipts;
$payment_progress = $invoice['grand_total'] > 0 ? ($total_paid_from_receipts / $invoice['grand_total']) * 100 : 0;

$pageTitle = 'Invoice ' . $invoice['document_number'] . ' - ' . COMPANY_NAME;
include '../includes/header.php';
?>

<?php if (isset($_GET['converted'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <p class="text-green-800 font-semibold">✓ Invoice created successfully from quote!</p>
    </div>
<?php endif; ?>

<?php if ($parent_quote): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 no-print">
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-600">From Quote:</span>
            <a href="view-quote.php?id=<?php echo $parent_quote['id']; ?>"
                class="font-mono font-semibold text-primary hover:text-blue-700">
                <?php echo htmlspecialchars($parent_quote['document_number']); ?>
            </a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <span class="font-mono font-semibold text-green-600">
                <?php echo htmlspecialchars($invoice['document_number']); ?>
            </span>
        </div>
    </div>
<?php endif; ?>

<div class="flex flex-wrap gap-4 mb-6 no-print">
    <button onclick="window.print()"
        class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Print Invoice</button>
    <a href="../api/export-invoice-pdf.php?id=<?php echo $invoice_id; ?>"
        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
        📄 Export PDF
    </a>
    <a href="edit-invoice.php?id=<?php echo $invoice_id; ?>"
        class="px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 font-semibold">
        ✏️ Edit Invoice
    </a>

    <?php if ($invoice['status'] === 'draft'): ?>
        <form method="POST" action="../api/finalize-invoice.php"
            onsubmit="return confirm('Are you sure you want to finalize this invoice? It cannot be edited afterwards.');"
            class="inline">
            <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                🔒 Finalize Invoice
            </button>
        </form>
    <?php endif; ?>

    <?php
    // Check if invoice is finalized and has balance
    if ($invoice['status'] === 'finalized' && $invoice['balance_due'] > 0):
        ?>
        <a href="payments/record-payment.php?invoice_id=<?php echo $invoice_id; ?>"
            class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold">
            💳 Record Payment
        </a>
    <?php endif; ?>

    <a href="view-invoices.php"
        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">← Back to
        Invoices</a>
</div>

<div id="printableInvoice" class="bg-white rounded-lg shadow-md p-4 md:p-8 max-w-4xl mx-auto">
    <!-- Same structure as view-quote.php but with INVOICE title and balance_due -->

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
            <p><strong>Phone:</strong>
                <?php echo COMPANY_PHONE; ?> | <strong>Email:</strong>
                <?php echo COMPANY_EMAIL; ?> |
                <?php echo COMPANY_WEBSITE; ?>
            </p>
        </div>
    </div>

    <div class="text-center mb-8">
        <h2 class="text-4xl font-serif font-bold mb-2 text-gray-900">INVOICE</h2>
        <p class="text-gray-600 italic">
            <?php echo htmlspecialchars($invoice['quote_title']); ?>
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div>
            <p class="text-sm font-bold text-gray-700 mb-2">Bill To:</p>
            <div class="border border-gray-300 p-3 rounded bg-gray-50">
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($invoice['customer_name']); ?>
                </p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Invoice Number:</span>
                <span class="font-mono font-bold text-green-600">
                    <?php echo htmlspecialchars($invoice['document_number']); ?>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Date:</span>
                <span class="text-gray-900">
                    <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Salesperson:</span>
                <span class="text-gray-900">
                    <?php echo htmlspecialchars($invoice['salesperson']); ?>
                </span>
            </div>
        </div>
    </div>

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
                            <?php echo $item['vat_applicable'] ? '<span class="text-green-600 font-bold">✓</span>' : '<span class=text-gray-400">—</span>'; ?>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900">
                            <?php echo formatNaira($item['line_total']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-end mb-8">
        <div class="w-80 space-y-2">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="font-semibold text-gray-700">Subtotal:</span>
                <span class="text-lg font-bold text-gray-900">
                    <?php echo formatNaira($invoice['subtotal']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="font-semibold text-gray-700">VAT (7.5%):</span>
                <span class="text-lg font-bold text-gray-900">
                    <?php echo formatNaira($invoice['total_vat']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 bg-primary text-white px-4 rounded">
                <span class="text-lg font-bold">Grand Total:</span>
                <span class="text-xl font-bold">
                    <?php echo formatNaira($invoice['grand_total']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="font-semibold text-gray-700">Amount Paid:</span>
                <span class="text-lg font-bold text-green-600">
                    <?php echo formatNaira($invoice['amount_paid']); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 bg-red-600 text-white px-4 rounded">
                <span class="text-lg font-bold">Balance Due:</span>
                <span class="text-xl font-bold">
                    <?php echo formatNaira($invoice['balance_due']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Footer same as quote -->
    <div class="border-t-2 border-gray-200 pt-6">
        <p class="text-center font-serif italic font-bold mb-6 text-gray-700">We appreciate your business! Thank you</p>
        <div class="bg-primary text-white text-center py-2 text-sm font-bold uppercase tracking-wider mb-2">
            MAKE ALL PAYMENTS IN FAVOUR OF: <?php echo htmlspecialchars(COMPANY_NAME); ?>
        </div>
        <div class="bg-blue-100 flex justify-around py-4 px-6 border-x border-gray-300 mb-2">
            <?php
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
            Invoice prepared by:
            <?php echo htmlspecialchars($invoice['salesperson']); ?>
        </div>
    </div>

    <!-- Phase 6: Payment History -->
    <?php if (count($receipts) > 0): ?>
        <div class="bg-gray-50 rounded-lg p-6 mt-8 no-print">
            <h3 class="text-xl font-bold text-gray-900 mb-4">💰 Payment History</h3>

            <!-- Payment Progress Bar -->
            <div class="mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-semibold text-gray-700">Payment Progress</span>
                    <span class="font-semibold text-gray-900"><?php echo number_format($payment_progress, 1); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-green-500 h-4 rounded-full transition-all"
                        style="width: <?php echo min($payment_progress, 100); ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-600 mt-1">
                    <span>Paid: ₦<?php echo number_format($total_paid_from_receipts, 2); ?></span>
                    <span>Balance: ₦<?php echo number_format($actual_balance, 2); ?></span>
                </div>
            </div>

            <!-- Receipts List -->
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-800 text-sm mb-2">Receipts (<?php echo count($receipts); ?>)</h4>
                <?php foreach ($receipts as $index => $receipt): ?>
                    <div
                        class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-4">
                            <div class="bg-green-100 text-green-800 px-3 py-2 rounded-lg font-mono text-sm font-bold">
                                #<?php echo $index + 1; ?>
                            </div>
                            <div>
                                <a href="view-receipt.php?id=<?php echo $receipt['id']; ?>"
                                    class="font-mono font-semibold text-primary hover:text-blue-700">
                                    <?php echo htmlspecialchars($receipt['document_number']); ?>
                                </a>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo date('d/m/Y H:i', strtotime($receipt['created_at'])); ?>
                                    <?php if ($receipt['payment_method']): ?>
                                        • <?php echo htmlspecialchars($receipt['payment_method']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-green-600">
                                ₦<?php echo number_format($receipt['amount_paid'], 2); ?>
                            </p>
                            <a href="view-receipt.php?id=<?php echo $receipt['id']; ?>"
                                class="text-xs text-primary hover:underline">
                                View Receipt →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
    @media print {
        body {
            background: white;
        }

        .no-print {
            display: none !important;
        }

        #printableInvoice {
            box-shadow: none;
            padding: 20mm;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
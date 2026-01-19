<?php
include '../includes/session-check.php';

$receipt_id = $_GET['id'] ?? null;

if (!$receipt_id) {
    header('Location: view-receipts.php');
    exit;
}

// Fetch receipt
$stmt = $pdo->prepare("
    SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title 
    FROM receipts r 
    LEFT JOIN invoices i ON r.invoice_id = i.id
    WHERE r.id = ? AND r.deleted_at IS NULL
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    header('Location: view-receipts.php?error=Receipt not found');
    exit;
}

// Fetch parent invoice
$parent_invoice = null;
if ($receipt['invoice_id']) {
    $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
    $stmt->execute([$receipt['invoice_id']]);
    $parent_invoice = $stmt->fetch();
}

// Fetch parent quote
$parent_quote = null;
if ($parent_invoice && $parent_invoice['quote_id']) {
    $stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ?");
    $stmt->execute([$parent_invoice['quote_id']]);
    $parent_quote = $stmt->fetch();
}

$pageTitle = 'Receipt ' . $receipt['document_number'] . ' - Bluedots Technologies';
include '../includes/header.php';
?>

<?php if (isset($_GET['generated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 no-print">
        <p class="text-green-800 font-semibold">✓ Receipt generated successfully!</p>
    </div>
<?php endif; ?>

<!-- Breadcrumb Trail -->
<div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-gray-200 rounded-lg p-4 mb-6 no-print">
    <div class="flex items-center gap-2 text-sm flex-wrap">
        <span class="text-gray-600 font-semibold">Document Trail:</span>
        <?php if ($parent_quote): ?>
            <a href="view-quote.php?id=<?php echo $parent_quote['id']; ?>"
                class="font-mono font-semibold text-blue-600 hover:text-blue-700">
                <?php echo htmlspecialchars($parent_quote['document_number']); ?>
            </a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        <?php endif; ?>
        <?php if ($parent_invoice): ?>
            <a href="view-invoice.php?id=<?php echo $parent_invoice['id']; ?>"
                class="font-mono font-semibold text-green-600 hover:text-green-700">
                <?php echo htmlspecialchars($parent_invoice['document_number']); ?>
            </a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        <?php endif; ?>
        <span class="font-mono font-semibold text-purple-600">
            <?php echo htmlspecialchars($receipt['document_number']); ?>
        </span>
        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full ml-2">PAID</span>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex flex-wrap gap-4 mb-6 no-print">
    <button onclick="window.print()"
        class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 256 256">
            <path
                d="M224,96V192H192v32H64V192H32V96A32,32,0,0,1,64,64H192A32,32,0,0,1,224,96ZM80,208h96V160H80Zm128-80H48v48H64V144a16,16,0,0,1,16-16h96a16,16,0,0,1,16,16v32h16Z">
            </path>
        </svg>
        Print Receipt
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
            <a href="../api/export-receipt-pdf.php?id=<?php echo $receipt['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                Export as PDF
            </a>
            <a href="../api/export-receipt-jpeg.php?id=<?php echo $receipt['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                </svg>
                Export as JPEG
            </a>

            <a href="../api/export-receipt-html.php?id=<?php echo $receipt['id']; ?>" target="_blank"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                Export as HTML
            </a>
        </div>
    </div>
    <a href="view-receipts.php"
        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">← Back to
        Receipts</a>
</div>

<!-- Receipt Display -->
<div id="printableReceipt" class="bg-white rounded-lg shadow-md p-4 md:p-8 max-w-4xl mx-auto">
    <!-- Header -->
    <!-- Header -->
    <div class="text-center mb-8 pb-6 border-b-2 border-gray-200">
        <div class="flex justify-center items-center gap-2 mb-3">
            <?php if (defined('COMPANY_LOGO') && !empty(COMPANY_LOGO)): ?>
                <img src="../<?php echo htmlspecialchars(COMPANY_LOGO); ?>" alt="Company Logo" class="max-h-44 max-w-xs">
            <?php else: ?>
                <!-- Default Placeholder if no logo set -->
                <h1 class="text-3xl font-bold tracking-tight mb-1"><?php echo htmlspecialchars(COMPANY_NAME); ?></h1>
            <?php endif; ?>
        </div>

        <?php if (!defined('COMPANY_LOGO') || empty(COMPANY_LOGO)): ?>
            <!-- Only show text header if no logo is present (assuming logo contains name) 
                 OR explicitly show name below logo if desired. User request is to fix logo. -->
            <!-- <p class="text-[9px] tracking-[0.3em] uppercase font-bold text-gray-600">TECHNOLOGIES</p> -->
        <?php endif; ?>

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

    <!-- Document Title -->
    <div class="text-center mb-8">
        <h2 class="text-4xl font-serif font-bold mb-2 text-purple-600">RECEIPT</h2>
        <p class="text-gray-600 italic">
            <?php echo htmlspecialchars($receipt['quote_title']); ?>
        </p>
        <div class="inline-block bg-green-100 px-4 py-2 rounded-full mt-3">
            <p class="text-green-800 font-bold">✓ PAYMENT RECEIVED</p>
        </div>
    </div>

    <!-- Receipt Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div>
            <p class="text-sm font-bold text-gray-700 mb-2">Received From:</p>
            <div class="border border-gray-300 p-3 rounded bg-gray-50">
                <p class="font-semibold text-gray-900">
                    <?php echo htmlspecialchars($receipt['customer_name']); ?>
                </p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Receipt Number:</span>
                <span class="font-mono font-bold text-purple-600">
                    <?php echo htmlspecialchars($receipt['document_number']); ?>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-gray-700">Date:</span>
                <span class="text-gray-900">
                    <?php echo date('d/m/Y', strtotime($receipt['payment_date'])); ?>
                </span>
            </div>
            <?php if ($parent_invoice): ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-gray-700">Invoice:</span>
                    <span class="font-mono text-green-600">
                        <?php echo htmlspecialchars($parent_invoice['document_number']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-6 mb-8">
        <h3 class="font-bold text-gray-900 mb-4 text-lg">Payment Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Payment Method:</p>
                <p class="font-semibold text-gray-900 text-lg">
                    <?php echo htmlspecialchars($receipt['payment_method']); ?>
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Amount Paid:</p>
                <p class="font-bold text-green-600 text-2xl">
                    <?php echo formatNaira($receipt['amount_paid']); ?>
                </p>
            </div>
            <?php if (!empty($receipt['reference_number'])): ?>
                <div class="col-span-2">
                    <p class="text-sm text-gray-600">Payment Reference:</p>
                    <p class="font-mono text-gray-900">
                        <?php echo htmlspecialchars($receipt['reference_number']); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Summary -->
    <div class="border border-gray-300 rounded-lg p-6 mb-8">
        <h3 class="font-bold text-gray-900 mb-4">Invoice Summary</h3>
        <div class="space-y-2">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-700">Original Invoice Amount:</span>
                <span class="font-bold text-gray-900">
                    <?php echo formatNaira($parent_invoice ? $parent_invoice['grand_total'] : 0); ?>
                </span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-700">Amount Paid (This Receipt):</span>
                <span class="font-bold text-green-600">
                    <?php echo formatNaira($receipt['amount_paid']); ?>
                </span>
            </div>
            <?php if ($parent_invoice): ?>
                <div class="flex justify-between items-center py-3 bg-gray-50 px-4 rounded">
                    <span class="font-bold text-gray-900">Remaining Balance:</span>
                    <span
                        class="font-bold text-lg <?php echo $parent_invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                        <?php echo formatNaira($parent_invoice['balance_due']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($receipt['notes']): ?>
        <div class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded">
            <p class="text-sm font-semibold text-gray-700 mb-1">Notes:</p>
            <p class="text-gray-900">
                <?php echo nl2br(htmlspecialchars($receipt['notes'])); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="border-t-2 border-gray-200 pt-6">
        <p class="text-center font-serif italic font-bold mb-6 text-gray-700">Thank you for your payment!</p>
        <div class="bg-purple-600 text-white text-center py-3 rounded-lg mb-4">
            <p class="font-bold text-lg">✓ PAYMENT CONFIRMED</p>
        </div>
        <div class="text-center text-sm text-gray-600 mt-6">
            <p>This is a computer-generated receipt</p>
            <!-- Receipts don't have salesperson column, so we might need fetch from invoice or user -->
            <!-- Previous code used $receipt['salesperson']. I didn't see that in receipts table. Let's check invoice -->
            <p class="mt-1">Receipt prepared by:
                <?php echo htmlspecialchars($parent_invoice['salesperson'] ?? 'System'); ?>
            </p>
        </div>
    </div>
</div>

<style>
    @media print {
        body {
            background: white;
        }

        .no-print {
            display: none !important;
        }

        #printableReceipt {
            box-shadow: none;
            padding: 20mm;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
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

$pageTitle = 'Receipt ' . $receipt['document_number'] . ' - ERP System';
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
            <!-- Export links... -->
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

    <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <?php if (($receipt['status'] ?? '') !== 'void'): ?>
            <button onclick="voidReceipt(<?php echo $receipt['id']; ?>)"
                class="px-6 py-3 border border-red-300 text-red-600 bg-red-50 rounded-lg hover:bg-red-100 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Void Receipt
            </button>
        <?php endif; ?>

        <button onclick="deleteReceipt(<?php echo $receipt['id']; ?>)"
            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                </path>
            </svg>
            Delete
        </button>
    <?php endif; ?>
    <a href="view-receipts.php"
        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">← Back to
        Receipts</a>
</div>

<!-- ... (Receipt Display) ... -->

<script>
    async function voidReceipt(id) {
        // ... (Existing voidReceipt code) ...
        const reason = prompt("Please enter a reason for voiding this receipt:");
        if (reason === null) return;
        if (reason.trim() === "") {
            alert("A reason is required to void a receipt.");
            return;
        }

        if (!confirm("Are you sure you want to VOID this receipt? This will reverse the payment on the invoice.")) {
            return;
        }

        try {
            const btn = document.querySelector('button[onclick^="voidReceipt"]');
            if (btn) {
                btn.disabled = true;
                btn.innerText = "Voiding...";
            }

            const response = await fetch('../api/void-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receipt_id: id, reason: reason })
            });

            const result = await response.json();

            if (result.success) {
                alert('Receipt voided successfully.');
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
                if (btn) { btn.disabled = false; btn.innerText = "Void Receipt"; }
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
        }
    }

    async function deleteReceipt(id) {
        if (!confirm("Are you sure you want to DELETE this receipt? This will remove it from the system and reverse any payments if it wasn't already voided. This cannot be undone.")) {
            return;
        }

        try {
            const reason = prompt("Optional: Reason for deletion?");

            const response = await fetch('../api/delete-receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receipt_id: id, reason: reason })
            });

            const result = await response.json();

            if (result.success) {
                alert('Receipt deleted successfully.');
                window.location.href = 'view-receipts.php';
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Connection Error: ' + error.message);
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
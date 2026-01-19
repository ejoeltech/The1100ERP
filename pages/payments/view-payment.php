<?php
// pages/payments/view-payment.php
include '../../includes/session-check.php';
requirePermission('view_payments');

$id = $_GET['id'] ?? null;
if (!$id)
    die('Payment ID required');

// Fetch Payment Details
$stmt = $pdo->prepare("
    SELECT p.*, c.customer_name as customer_name, c.email, c.phone, u.username as created_by_name
    FROM payments p
    LEFT JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment)
    die('Payment not found');

// Fetch Allocation Details (Receipts linking to this payment)
$stmt = $pdo->prepare("
    SELECT r.*, i.invoice_number, i.grand_total as invoice_total
    FROM receipts r
    JOIN invoices i ON r.invoice_id = i.id
    WHERE r.payment_id = ?
");
$stmt->execute([$id]);
$allocations = $stmt->fetchAll();

$totalAllocated = 0;
foreach ($allocations as $a)
    $totalAllocated += $a['amount_paid'];
$creditAmount = $payment['amount'] - $totalAllocated;

$pageTitle = 'Payment ' . $payment['payment_number'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payment - 1100ERP</title>
    <link href="../../assets/css/style.css" rel="stylesheet">

</head>

<body class="bg-gray-50 text-gray-900">
    <?php include '../../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <?= $payment['payment_number'] ?>
                    </h1>
                    <p class="text-gray-600">Received on
                        <?= formatDate($payment['payment_date']) ?>
                    </p>
                </div>
                <a href="manage-payments.php" class="text-gray-600 hover:text-gray-900">← Back to List</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Basic Info -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-gray-500 text-sm font-bold uppercase mb-4">Payment Details</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-bold text-xl text-green-600">
                                <?= formatNaira($payment['amount']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method:</span>
                            <span class="font-medium">
                                <?= htmlspecialchars($payment['payment_method']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reference:</span>
                            <span class="font-mono text-sm">
                                <?= htmlspecialchars($payment['reference'] ?: 'N/A') ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Notes:</span>
                            <span class="italic text-gray-500">
                                <?= htmlspecialchars($payment['notes'] ?: 'None') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-gray-500 text-sm font-bold uppercase mb-4">Customer Details</h3>
                    <div class="space-y-2">
                        <div class="font-bold text-lg">
                            <?= htmlspecialchars($payment['customer_name']) ?>
                        </div>
                        <div class="text-gray-600">
                            <?= htmlspecialchars($payment['email']) ?>
                        </div>
                        <div class="text-gray-600">
                            <?= htmlspecialchars($payment['phone']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Allocation Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">Allocation Breakdown</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Invoice</th>
                            <th class="px-6 py-3 text-left">Receipt #</th>
                            <th class="px-6 py-3 text-right">Amount Applied</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($allocations as $alloc): ?>
                            <tr>
                                <td class="px-6 py-4 font-medium text-blue-600">
                                    <a href="../view-invoice.php?id=<?= $alloc['invoice_id'] ?>" target="_blank">
                                        <?= $alloc['invoice_number'] ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <a href="../view-receipt.php?id=<?= $alloc['id'] ?>" target="_blank"
                                        class="hover:underline">
                                        <?= $alloc['receipt_number'] ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right font-bold">
                                    <?= formatNaira($alloc['amount_paid']) ?>

                                    <!-- Admin Actions -->
                                    <?php if (isAdmin() && ($alloc['status'] ?? 'valid') !== 'void'): ?>
                                        <div class="mt-1 text-xs space-x-2">
                                            <button onclick="voidReceipt(<?= $alloc['id'] ?>)"
                                                class="text-red-500 hover:underline">Void</button>
                                            <button onclick="archiveReceipt(<?= $alloc['id'] ?>)"
                                                class="text-gray-500 hover:underline">Archive</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (($alloc['status'] ?? 'valid') === 'void'): ?>
                                        <span class="block text-xs text-red-600 font-bold uppercase mt-1">VOIDED</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Unallocated/Credit Row -->
                        <?php if ($creditAmount > 0): ?>
                            <tr class="bg-blue-50">
                                <td colspan="2" class="px-6 py-4 text-blue-800 font-medium">
                                    <i>Unallocated (Added to Customer Credit)</i>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-blue-800">
                                    <?= formatNaira($creditAmount) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td colspan="2" class="px-6 py-4 text-right">Total:</td>
                            <td class="px-6 py-4 text-right">
                                <?= formatNaira($payment['amount']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer Info -->
            <div class="text-center text-gray-500 text-sm">
                Recorded by
                <?= htmlspecialchars($payment['created_by_name']) ?> on
                <?= $payment['created_at'] ?>
            </div>

        </div>
    </main>
    </div>
    <script>
        async function voidReceipt(id) {
            const reason = prompt("Please enter a reason for voiding this receipt:");
            if (!reason) return;

            try {
                const res = await fetch('../../api/void-receipt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ receipt_id: id, reason: reason })
                });
                const data = await res.json();

                if (data.success) {
                    alert('Receipt voided successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                console.error(e);
                alert('Network error');
            }
        }

        async function archiveReceipt(id) {
            if (!confirm("Are you sure you want to archive this receipt? It will be moved to Archives.")) return;

            try {
                const res = await fetch('../../api/archive-item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'receipt', id: id, action: 'archive' })
                });
                const data = await res.json();

                if (data.success) {
                    alert('Receipt archived successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                console.error(e);
                alert('Network error');
            }
        }
    </script>
</body>

</html>
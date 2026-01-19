<?php
// pages/archives.php
include '../includes/session-check.php';
requirePermission('view_reports'); // Assuming basic permission. Could be admin only.

$pageTitle = 'Archives - 1100ERP';
include '../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Archives</h1>
        <p class="text-gray-500 text-sm">View and restore archived items.</p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button onclick="switchTab('quotes')" id="tab-quotes"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm active-tab">
                Archived Quotes
            </button>
            <button onclick="switchTab('invoices')" id="tab-invoices"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Archived Invoices
            </button>
            <button onclick="switchTab('receipts')" id="tab-receipts"
                class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Archived Receipts
            </button>
        </nav>
    </div>

    <!-- Content Sections -->
    <div id="content-quotes" class="tab-content">
        <?php
        $stmt = $pdo->query("SELECT * FROM quotes WHERE is_archived = 1 ORDER BY created_at DESC");
        $quotes = $stmt->fetchAll();
        if ($quotes): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quotes as $q): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $q['quote_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= $q['quote_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($q['customer_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($q['grand_total'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('quote', <?= $q['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900">Restore</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived quotes found.</p>
        <?php endif; ?>
    </div>

    <div id="content-invoices" class="tab-content hidden">
        <?php
        // Fetch invoices with customer name
        $stmt = $pdo->query("
            SELECT i.*, c.customer_name 
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.is_archived = 1 
            ORDER BY i.created_at DESC
        ");
        $invoices = $stmt->fetchAll();
        if ($invoices): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $inv['invoice_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= $inv['invoice_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($inv['customer_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($inv['grand_total'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('invoice', <?= $inv['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900">Restore</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived invoices found.</p>
        <?php endif; ?>
    </div>

    <div id="content-receipts" class="tab-content hidden">
        <?php
        // Fetch receipts
        $stmt = $pdo->query("SELECT * FROM receipts WHERE is_archived = 1 ORDER BY payment_date DESC");
        $receipts = $stmt->fetchAll();
        if ($receipts): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['payment_date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['reference_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $r['payment_method'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">₦
                                    <?= number_format($r['amount_paid'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="unarchive('receipt', <?= $r['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900">Restore</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">No archived receipts found.</p>
        <?php endif; ?>
    </div>

</div>

<script>
    function switchTab(tabName) {
        // Hide all contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        // Show target content
        document.getElementById('content-' + tabName).classList.remove('hidden');

        // Reset tab styles
        document.querySelectorAll('nav button').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Activate target tab
        const activeBtn = document.getElementById('tab-' + tabName);
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
        activeBtn.classList.add('border-blue-500', 'text-blue-600');
    }

    // Default tab style
    document.addEventListener('DOMContentLoaded', () => switchTab('quotes'));

    async function unarchive(type, id) {
        if (!confirm('Restore this item from archives?')) return;

        try {
            const res = await fetch('../api/archive-item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, action: 'unarchive' })
            });
            const data = await res.json();

            if (data.success) {
                alert('Restored successfully');
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

<?php include '../includes/footer.php'; ?>
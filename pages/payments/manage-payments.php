<?php
// pages/payments/manage-payments.php
include '../../includes/session-check.php';
requirePermission('view_payments');

$pageTitle = 'Payments';

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (p.payment_number LIKE ? OR c.customer_name LIKE ? OR p.reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($date_from) {
    $where .= " AND p.payment_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND p.payment_date <= ?";
    $params[] = $date_to;
}

// Get Total Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments p
    LEFT JOIN customers c ON p.customer_id = c.id
    $where
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get Payments
$stmt = $pdo->prepare("
    SELECT p.*, c.customer_name as customer_name, u.username as created_by_name
    FROM payments p
    LEFT JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    $where
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - 1100ERP</title>
    <link href="../../assets/css/style.css" rel="stylesheet">

</head>

<body class="bg-gray-50 text-gray-900">
    <?php include '../../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Payments</h1>
                    <p class="text-gray-600">Track and manage received payments.</p>
                </div>
                <?php if (hasPermission('create_payment')): ?>
                    <a href="record-payment.php"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow-md flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Record Payment
                    </a>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <form class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search number, customer..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-700 flex-1">Filter</button>
                        <a href="manage-payments.php"
                            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Number</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Method</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Reference</th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">No payments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= formatDate($payment['payment_date']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                            <a href="view-payment.php?id=<?= $payment['id'] ?>">
                                                <?= $payment['payment_number'] ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($payment['customer_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($payment['payment_method']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($payment['reference'] ?: '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                            <?= formatNaira($payment['amount']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view-payment.php?id=<?= $payment['id'] ?>"
                                                class="text-blue-600 hover:text-blue-900">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            Page
                            <?= $page ?> of
                            <?= $totalPages ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"
                                    class="px-3 py-1 border rounded bg-white hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"
                                    class="px-3 py-1 border rounded bg-white hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    </div>
</body>

</html>
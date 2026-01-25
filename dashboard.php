<?php
include 'includes/session-check.php';

$pageTitle = 'Dashboard - Bluedots Technologies';

// Phase 8: Role-based filtering (functions loaded from session-check)
$role = function_exists('getUserRole') ? getUserRole() : 'admin';
$userId = $_SESSION['user_id'];

// Get role filter for queries
$roleFilter = function_exists('getRoleFilter') ? getRoleFilter('d') : '';

// Get stats for current month
$currentMonth = date('Y-m');

// Quotes this month
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM quotes
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    AND deleted_at IS NULL
");
$stmt->execute([$currentMonth]);
$quotes_count = $stmt->fetch()['count'];

// Invoices this month
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM invoices
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    AND deleted_at IS NULL
");
$stmt->execute([$currentMonth]);
$invoices_count = $stmt->fetch()['count'];

// Receipts this month
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM receipts
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    AND deleted_at IS NULL
    AND status != 'void'
");
$stmt->execute([$currentMonth]);
$receipts_count = $stmt->fetch()['count'];

// Total revenue (from receipts)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_paid), 0) as total 
    FROM receipts
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    AND deleted_at IS NULL
    AND status != 'void'
");
$stmt->execute([$currentMonth]);
$total_revenue = $stmt->fetch()['total'];

// Conversion rate (quotes to invoices)
$conversion_rate = $quotes_count > 0 ? round(($invoices_count / $quotes_count) * 100) : 0;

// Total documents by status (combining all three tables)
$draft_count = 0;
$finalized_count = 0;

// Count drafts and finalized from quotes
$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status IN ('finalized', 'approved') THEN 1 ELSE 0 END) as finalized
    FROM quotes
    WHERE deleted_at IS NULL
");
$quote_counts = $stmt->fetch();
$draft_count += $quote_counts['draft'];
$finalized_count += $quote_counts['finalized'];

// Count drafts and sent/paid from invoices
$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status IN ('sent', 'paid', 'partially_paid') THEN 1 ELSE 0 END) as finalized
    FROM invoices
    WHERE deleted_at IS NULL
");
$invoice_counts = $stmt->fetch();
$draft_count += $invoice_counts['draft'];
$finalized_count += $invoice_counts['finalized'];

// Recent documents (last 10) - Union of all three tables
$stmt = $pdo->query("
    (SELECT 
        id,
        'quote' as document_type,
        quote_number as document_number,
        customer_name,
        grand_total,
        status,
        created_at
    FROM quotes
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT 10)
    UNION ALL
    (SELECT 
        id,
        'invoice' as document_type,
        invoice_number as document_number,
        customer_name,
        grand_total,
        status,
        created_at
    FROM invoices
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT 10)
    UNION ALL
    (SELECT 
        id,
        'receipt' as document_type,
        receipt_number as document_number,
        customer_name,
        amount_paid as grand_total,
        'paid' as status,
        created_at
    FROM receipts
    WHERE deleted_at IS NULL
    AND status != 'void'
    ORDER BY created_at DESC
    LIMIT 10)
    ORDER BY created_at DESC
    LIMIT 10
");
$recent_documents = $stmt->fetchAll();

// Phase 8: Admin-only stats
if (function_exists('isAdmin') && isAdmin()) {
    // User activity is not tracked in the current schema
    // Setting empty array for now
    $top_users = [];

    // System health
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $active_users = $stmt->fetchColumn();

    // Today's actions (only if audit_log exists)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()");
        $today_actions = $stmt->fetchColumn();
    } catch (Exception $e) {
        $today_actions = 0; // Table doesn't exist yet
    }
}

include 'includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">Dashboard</h2>
    <p class="text-gray-600 mt-1">
        Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!
        <?php if (function_exists('getRoleBadge')): ?>
            <?php echo getRoleBadge($role); ?>
        <?php endif; ?>
    </p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

    <!-- Quotes Card -->
    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-600 uppercase">Quotes</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $quotes_count; ?></p>
                <p class="text-xs text-gray-500 mt-1">This month</p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Invoices Card -->
    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-600 uppercase">Invoices</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $invoices_count; ?></p>
                <p class="text-xs text-gray-500 mt-1">This month</p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                    </path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Receipts Card -->
    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-600 uppercase">Receipts</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $receipts_count; ?></p>
                <p class="text-xs text-gray-500 mt-1">This month</p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Revenue Card -->
    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-primary">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-600 uppercase">Revenue</p>
                <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo formatNaira($total_revenue); ?></p>
                <p class="text-xs text-gray-500 mt-1">This month</p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
            </div>
        </div>
    </div>

</div>

<!-- Phase 8: Document Status Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Conversion Rate -->
    <div class="bg-gradient-to-r from-primary to-blue-700 rounded-lg shadow-md p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold opacity-90">Conversion Rate</p>
                <p class="text-4xl font-bold mt-2"><?php echo $conversion_rate; ?>%</p>
                <p class="text-sm opacity-75 mt-1">Quotes → Invoices</p>
            </div>
            <div class="text-right opacity-75">
                <p class="text-sm"><?php echo $invoices_count; ?> / <?php echo $quotes_count; ?></p>
                <p class="text-xs mt-1">Invoices / Quotes</p>
            </div>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-lg shadow-md p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold opacity-90">Document Status</p>
                <div class="mt-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-bold"><?php echo $draft_count; ?></span>
                        <span class="text-sm opacity-75">Drafts</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-bold"><?php echo $finalized_count; ?></span>
                        <span class="text-sm opacity-75">Finalized</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Phase 8: Admin-only widgets -->
<?php if (isAdmin() && isset($top_users)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">🏆 Top Performers (This Month)</h3>
        <div class="space-y-3">
            <?php foreach ($top_users as $index => $user): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="bg-primary text-white w-10 h-10 rounded-full flex items-center justify-center font-bold">
                            #<?php echo $index + 1; ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $user['document_count']; ?> documents</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-green-600"><?php echo formatNaira($user['revenue']); ?></p>
                        <p class="text-xs text-gray-500">Revenue</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- System Health (Admin only) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-800">Active Users</p>
                    <p class="text-3xl font-bold text-green-900 mt-2"><?php echo $active_users; ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-blue-800">Actions Today</p>
                    <p class="text-3xl font-bold text-blue-900 mt-2"><?php echo $today_actions; ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00 2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="pages/create-quote.php"
            class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg hover:border-primary hover:bg-blue-50 transition-colors">
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Create Quote</p>
                <p class="text-sm text-gray-600">New quote for customer</p>
            </div>
        </a>

        <a href="pages/view-quotes.php"
            class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition-colors">
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">View All Quotes</p>
                <p class="text-sm text-gray-600">Browse quotes list</p>
            </div>
        </a>

        <a href="pages/view-invoices.php"
            class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-colors">
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                    </path>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">View All Invoices</p>
                <p class="text-sm text-gray-600">Browse invoices list</p>
            </div>
        </a>
    </div>
</div>

<!-- Recent Activity -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Recent Activity</h3>

    <?php if (empty($recent_documents)): ?>
        <p class="text-gray-500 text-center py-8">No recent documents</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-200">
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Document #</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Customer</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Amount</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_documents as $doc): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <?php
                                $type_colors = [
                                    'quote' => 'bg-blue-100 text-blue-800',
                                    'invoice' => 'bg-green-100 text-green-800',
                                    'receipt' => 'bg-purple-100 text-purple-800'
                                ];
                                $color = $type_colors[$doc['document_type']];
                                ?>
                                <span class="px-2 py-1 <?php echo $color; ?> text-xs font-semibold rounded-full uppercase">
                                    <?php echo $doc['document_type']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-primary">
                                <?php echo htmlspecialchars($doc['document_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($doc['customer_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">
                                <?php echo formatNaira($doc['grand_total']); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($doc['status'] === 'finalized'): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                        Finalized
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">
                                        Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 text-center">
                                <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $view_pages = [
                                    'quote' => 'view-quote.php',
                                    'invoice' => 'view-invoice.php',
                                    'receipt' => 'view-receipt.php'
                                ];
                                $view_page = $view_pages[$doc['document_type']];
                                ?>
                                <a href="pages/<?php echo $view_page; ?>?id=<?php echo $doc['id']; ?>"
                                    class="text-primary hover:text-blue-700 font-semibold text-sm">
                                    View →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
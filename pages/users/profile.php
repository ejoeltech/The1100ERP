<?php
include '../../includes/session-check.php';

$pageTitle = 'My Profile - ERP System';

// Get stats for current user
$my_quotes = 0;
$my_invoices = 0;

if (function_exists('isSalesRep') && isSalesRep()) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM quotes 
        WHERE created_by = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$current_user['id']]);
    $my_quotes = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM invoices 
        WHERE created_by = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$current_user['id']]);
    $my_invoices = $stmt->fetch()['count'];
}

include '../../includes/header.php';
?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Profile updated successfully!</p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Profile Info -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">My Profile</h2>

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg font-mono">
                        <?php echo htmlspecialchars($current_user['username']); ?>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                    <?php
                    $role = $current_user['role'] ?? 'admin';
                    $role_badges = [
                        'admin' => 'bg-red-100 text-red-800',
                        'manager' => 'bg-blue-100 text-blue-800',
                        'sales_rep' => 'bg-green-100 text-green-800'
                    ];
                    $badge_color = $role_badges[$role] ?? 'bg-gray-100 text-gray-800';
                    $role_display = str_replace('_', ' ', ucwords($role));
                    ?>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <span class="px-3 py-1 <?php echo $badge_color; ?> text-xs font-semibold rounded-full">
                            <?php echo $role_display; ?>
                        </span>
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                    <?php echo htmlspecialchars($current_user['full_name']); ?>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <?php echo htmlspecialchars($current_user['email'] ?? '—'); ?>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <?php echo htmlspecialchars($current_user['phone'] ?? '—'); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Last Login</label>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <?php echo isset($current_user['last_login']) && $current_user['last_login'] ? date('d/m/Y H:i', strtotime($current_user['last_login'])) : 'Never'; ?>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Member Since</label>
                    <p class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?>
                    </p>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200">
                <a href="change-password.php"
                    class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                        </path>
                    </svg>
                    Change Password
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="space-y-6">

        <?php if (function_exists('isSalesRep') && isSalesRep()): ?>
            <!-- Sales Rep Stats -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">My Stats</h3>
                <div class="space-y-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">My Quotes</p>
                        <p class="text-3xl font-bold text-blue-600">
                            <?php echo $my_quotes; ?>
                        </p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">My Invoices</p>
                        <p class="text-3xl font-bold text-green-600">
                            <?php echo $my_invoices; ?>
                        </p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Conversion Rate</p>
                        <p class="text-3xl font-bold text-purple-600">
                            <?php echo $my_quotes > 0 ? round(($my_invoices / $my_quotes) * 100) : 0; ?>%
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="../create-quote.php"
                    class="block px-4 py-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 font-semibold transition-colors">
                    + Create Quote
                </a>
                <a href="../view-quotes.php"
                    class="block px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700 font-semibold transition-colors">
                    View My Quotes
                </a>
                <?php if (!function_exists('isSalesRep') || !isSalesRep()): ?>
                    <a href="../view-invoices.php"
                        class="block px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700 font-semibold transition-colors">
                        View All Invoices
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Permissions Info -->
        <div class="bg-gradient-to-br from-primary to-blue-700 rounded-lg shadow-md p-6 text-white">
            <h3 class="text-lg font-bold mb-3">Your Permissions</h3>
            <div class="space-y-2 text-sm">
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <p>✓ Full system access</p>
                    <p>✓ Manage users</p>
                    <p>✓ Edit all documents</p>
                    <p>✓ View audit log</p>
                <?php elseif (function_exists('isManager') && isManager()): ?>
                    <p>✓ View all documents</p>
                    <p>✓ Create & edit documents</p>
                    <p>✓ Generate invoices/receipts</p>
                    <p>✗ Cannot manage users</p>
                <?php else: ?>
                    <p>✓ Create quotes</p>
                    <p>✓ View own documents</p>
                    <p>✓ Edit own drafts</p>
                    <p>✗ Limited access</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
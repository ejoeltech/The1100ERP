<?php
include '../../includes/session-check.php';

// Only admins can manage users - backward compatible check
if (function_exists('requirePermission')) {
    requirePermission('manage_users');
} elseif (!function_exists('isAdmin') || !isAdmin()) {
    // Before migration, just check if user is admin from session
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die('Access Denied: Admin only');
    }
}

$pageTitle = 'Manage Users - ERP System';

// Fetch all users - backward compatible query
try {
    $stmt = $pdo->query("
    SELECT 
        id,
        username,
        full_name,
        email,
        phone,
        role,
        is_active,
        last_login,
        created_at
    FROM users
    ORDER BY created_at DESC
");

    $users = $stmt->fetchAll();
} catch (Exception $e) {
    // If users table doesn't exist yet, show empty state
    $users = [];
}

// Helper function for role badge - backward compatible
if (!function_exists('getRoleBadge')) {
    function getRoleBadge($role)
    {
        $badges = [
            'admin' => '<span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Admin</span>',
            'manager' => '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">Manager</span>',
            'sales_rep' => '<span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full">Sales Rep</span>',
        ];
        return $badges[$role] ?? '<span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">' . htmlspecialchars($role) . '</span>';
    }
}

include '../../includes/header.php';
?>

<!-- Success/Error Messages -->
<?php if (isset($_GET['created'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ User created successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ User updated successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ User deleted successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['status_changed'])): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-blue-800 font-semibold">✓ User status updated successfully!</p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-3xl font-bold text-gray-900">Manage Users</h2>
        <a href="create-user.php"
            class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create New User
        </a>
    </div>

    <?php if (empty($users)): ?>
        <p class="text-center text-gray-500 py-8">No users found</p>
    <?php else: ?>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Username</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Full Name</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Email</th>
                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Phone</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Role</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Last Login</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-sm font-semibold text-gray-900">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($user['email'] ?: '—'); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php echo htmlspecialchars($user['phone'] ?: '—'); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php echo getRoleBadge($user['role']); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($user['is_active']): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center">
                                <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>"
                                        class="text-primary hover:text-blue-700 font-semibold text-sm">
                                        Edit
                                    </a>

                                    <?php if ($user['id'] != $current_user['id']): // Can't toggle own status ?>
                                        <span class="text-gray-300">|</span>
                                        <a href="../../api/toggle-user-status.php?id=<?php echo $user['id']; ?>"
                                            class="text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-600 hover:text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-700 font-semibold text-sm"
                                            onclick="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?');">
                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </a>

                                        <span class="text-gray-300">|</span>
                                        <a href="../../api/delete-user.php?id=<?php echo $user['id']; ?>"
                                            class="text-red-600 hover:text-red-700 font-semibold text-sm"
                                            onclick="return confirm('Delete this user? This action cannot be undone.');">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <?php
            $total_users = count($users);
            $active_users = count(array_filter($users, fn($u) => $u['is_active']));
            $admin_count = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
            $manager_count = count(array_filter($users, fn($u) => $u['role'] === 'manager'));
            $salesrep_count = count(array_filter($users, fn($u) => $u['role'] === 'sales_rep'));
            ?>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-gray-600">Total Users:</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $total_users; ?>
                </p>
            </div>
            <div class="bg-green-50 p-4 rounded">
                <p class="text-gray-600">Active Users:</p>
                <p class="text-2xl font-bold text-green-600">
                    <?php echo $active_users; ?>
                </p>
            </div>
            <div class="bg-blue-50 p-4 rounded">
                <p class="text-gray-600">Managers:</p>
                <p class="text-2xl font-bold text-blue-600">
                    <?php echo $manager_count; ?>
                </p>
            </div>
            <div class="bg-purple-50 p-4 rounded">
                <p class="text-gray-600">Sales Reps:</p>
                <p class="text-2xl font-bold text-purple-600">
                    <?php echo $salesrep_count; ?>
                </p>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
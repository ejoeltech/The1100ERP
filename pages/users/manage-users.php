<?php
include '../../includes/session-check.php';

// WP3: manage_users via the permission system (the raw role fallback below
// only runs if it is missing, and uses isAdmin so super_admin is covered).
if (function_exists('requirePermission')) {
    requirePermission('manage_users');
} elseif (!function_exists('isAdmin') || !isAdmin()) {
    die('Access Denied: Admin only');
}

$pageTitle = 'Manage Users - ERP System';

// Fetch all users with groups
try {
    $stmt = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.group_id,
        u.is_active,
        u.last_login,
        u.created_at,
        g.name AS group_name,
        (SELECT COUNT(*) FROM user_permission_overrides o WHERE o.user_id = u.id) AS override_count
    FROM users u
    LEFT JOIN user_groups g ON g.id = u.group_id
    ORDER BY u.created_at DESC
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
        <?php if (!empty($_GET['invite'])): ?>
            <div class="mt-3 bg-white border border-green-300 rounded p-3">
                <p class="text-sm font-bold text-gray-900">One-time invite link (valid 48 hours, single use, shown once):</p>
                <p class="font-mono text-sm text-primary break-all select-all">accept-invite.php?token=<?php echo htmlspecialchars($_GET['invite']); ?></p>
                <p class="text-xs text-red-700 mt-1 font-semibold">Copy it for the user now — it will never be shown again and is not stored anywhere.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ User updated successfully!</p>
        <?php if (!empty($_GET['invite'])): ?>
            <div class="mt-3 bg-white border border-green-300 rounded p-3">
                <p class="text-sm font-bold text-gray-900">Password reset invite (valid 48 hours, single use, shown once):</p>
                <p class="font-mono text-sm text-primary break-all select-all">accept-invite.php?token=<?php echo htmlspecialchars($_GET['invite']); ?></p>
                <p class="text-xs text-red-700 mt-1 font-semibold">Copy it for the user now — it will never be shown again and is not stored anywhere.</p>
            </div>
        <?php endif; ?>
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
    <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
        <h2 class="text-3xl font-bold text-gray-900">Manage Users</h2>
        <div class="flex gap-2">
            <?php if (hasPermission('manage_access')): ?>
                <a href="manage-groups.php"
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold flex items-center gap-2">
                    🛡️ Groups & Permissions
                </a>
            <?php endif; ?>
            <a href="create-user.php"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create New User
            </a>
        </div>
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
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Group</th>
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
                            <td class="px-4 py-3 text-center text-sm">
                                <?php if (!empty($user['group_name'])): ?>
                                    <span class="px-3 py-1 bg-slate-100 text-slate-800 text-xs font-semibold rounded-full"><?php echo htmlspecialchars($user['group_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                                <?php if (!empty($user['override_count'])): ?>
                                    <div class="text-xs text-amber-600 font-semibold mt-1">+<?php echo (int)$user['override_count']; ?> override<?php echo $user['override_count'] == 1 ? '' : 's'; ?></div>
                                <?php endif; ?>
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
                                <div class="flex items-center justify-center gap-2 flex-wrap">
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>"
                                        class="text-primary hover:text-blue-700 font-semibold text-sm">
                                        Edit
                                    </a>

                                    <?php if (hasPermission('manage_access')): ?>
                                        <span class="text-gray-300">|</span>
                                        <a href="user-permissions.php?id=<?php echo $user['id']; ?>"
                                            class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm">
                                            Permissions
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($user['id'] != $current_user['id']): // Can't toggle own status ?>
                                        <span class="text-gray-300">|</span>
                                        <form method="POST" action="../../api/toggle-user-status.php" class="inline"
                                            onsubmit="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit"
                                                class="text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-600 hover:text-<?php echo $user['is_active'] ? 'yellow' : 'green'; ?>-700 font-semibold text-sm">
                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <span class="text-gray-300">|</span>
                                        <form method="POST" action="../../api/delete-user.php" class="inline"
                                            onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700 font-semibold text-sm">
                                                Delete
                                            </button>
                                        </form>
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
            $admin_count = count(array_filter($users, fn($u) => in_array($u['role'], ['super_admin', 'admin'])));
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
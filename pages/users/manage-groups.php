<?php
include '../../includes/session-check.php';
requirePermission('manage_access');

$pageTitle = 'User Groups & Permissions - ERP System';

// Fetch groups with permission counts + member counts
$groups = [];
try {
    $stmt = $pdo->query("
        SELECT g.*, COUNT(DISTINCT gp.permission_key) AS perm_count,
               (SELECT COUNT(*) FROM users u WHERE u.group_id = g.id) AS member_count
        FROM user_groups g
        LEFT JOIN group_permissions gp ON gp.group_id = g.id
        GROUP BY g.id
        ORDER BY g.is_system DESC, g.name ASC
    ");
    $groups = $stmt->fetchAll();
} catch (Exception $e) {
    $groups = [];
}

$catalog = getPermissionCatalog();

// Group being edited (GET ?edit=id)
$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($groups as $g) {
        if ($g['id'] == $_GET['edit']) {
            $editing = $g;
            break;
        }
    }
    if ($editing) {
        $stmt = $pdo->prepare("SELECT permission_key FROM group_permissions WHERE group_id = ?");
        $stmt->execute([$editing['id']]);
        $editing['perms'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
$editingPerms = $editing['perms'] ?? [];

include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8">
    <div class="flex items-center justify-between mb-2 flex-wrap gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">User Groups & Permissions</h2>
            <p class="text-gray-600 mt-1">Standard groups define baseline permissions. Assign users to a group, then fine-tune individuals via per-user overrides.</p>
        </div>
        <a href="manage-users.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-sm">← Back to Users</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 my-4"><p class="text-green-800 font-semibold">✓ Group saved.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 my-4"><p class="text-green-800 font-semibold">✓ Group deleted. Members moved to their role's group.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 my-4"><p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p></div>
    <?php endif; ?>

    <!-- Groups table -->
    <div class="overflow-x-auto mt-6">
        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-gray-300">
                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Group</th>
                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Primary Role</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Permissions</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Members</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($g['name']); ?></span>
                            <?php if ($g['is_system']): ?>
                                <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full">system</span>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($g['description'] ?? ''); ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?php echo htmlspecialchars(getRoleDisplayName($g['primary_role'])); ?></td>
                        <td class="px-4 py-3 text-center text-sm font-semibold"><?php echo (int)$g['perm_count']; ?></td>
                        <td class="px-4 py-3 text-center text-sm"><?php echo (int)$g['member_count']; ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2 text-sm font-semibold">
                                <a href="?edit=<?php echo $g['id']; ?>#editor" class="text-primary hover:text-blue-700">Edit</a>
                                <?php if (!$g['is_system']): ?>
                                    <span class="text-gray-300">|</span>
                                    <a href="../../api/delete-group.php?id=<?php echo $g['id']; ?>" class="text-red-600 hover:text-red-700" onclick="return confirm('Delete group <?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>? Members will be moved to their role group.');">Delete</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Editor -->
    <div id="editor" class="mt-8 border-t pt-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4"><?php echo $editing ? 'Edit Group: ' . htmlspecialchars($editing['name']) : 'Create Custom Group'; ?></h3>
        <form method="POST" action="../../api/save-group.php">
            <?php if ($editing): ?>
                <input type="hidden" name="group_id" value="<?php echo $editing['id']; ?>">
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" <?php echo ($editing && $editing['is_system']) ? 'readonly class="w-full px-4 py-2 border border-gray-200 bg-gray-50 rounded-lg text-gray-500"' : 'class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"'; ?> placeholder="e.g. store_keeper">
                    <?php if ($editing && $editing['is_system']): ?><p class="text-xs text-gray-500 mt-1">System group names are locked.</p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Primary Role</label>
                    <select name="primary_role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <?php foreach (['super_admin' => 'Super Administrator', 'admin' => 'Administrator', 'manager' => 'Manager', 'sales_rep' => 'Sales Representative', 'accountant' => 'Accountant', 'viewer' => 'Viewer'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (($editing['primary_role'] ?? 'viewer') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" value="<?php echo htmlspecialchars($editing['description'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" placeholder="What is this group for?">
                </div>
            </div>

            <p class="text-sm font-semibold text-gray-700 mb-2">Permissions in this group</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($catalog as $section => $perms): ?>
                    <div class="border rounded-lg p-4">
                        <p class="font-bold text-gray-900 text-sm mb-2"><?php echo htmlspecialchars($section); ?></p>
                        <?php foreach ($perms as $key => $label): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 py-0.5">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" <?php echo in_array($key, $editingPerms, true) ? 'checked' : ''; ?> class="w-4 h-4 text-primary rounded">
                                <?php echo htmlspecialchars($label); ?>
                                <span class="text-xs text-gray-400 font-mono"><?php echo $key; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-4 justify-end mt-6">
                <?php if ($editing): ?>
                    <a href="manage-groups.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold"><?php echo $editing ? 'Save Group' : 'Create Group'; ?></button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

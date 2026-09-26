<?php
include '../../includes/session-check.php';
requirePermission('manage_access');

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: manage-users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, g.name AS group_name FROM users u LEFT JOIN user_groups g ON g.id = u.group_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: manage-users.php?error=User not found');
    exit;
}

$stmt = $pdo->prepare("SELECT permission_key FROM group_permissions WHERE group_id = ?");
$stmt->execute([$user['group_id']]);
$groupPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT permission_key, granted FROM user_permission_overrides WHERE user_id = ?");
$stmt->execute([$user_id]);
$overrideRows = $stmt->fetchAll();
$overrides = [];
foreach ($overrideRows as $r) {
    $overrides[$r['permission_key']] = (int)$r['granted'];
}

$catalog = getPermissionCatalog();
$pageTitle = 'Permissions: ' . $user['username'] . ' - ERP System';
include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-2 flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Permissions for <?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p class="text-gray-600 mt-1">@<?php echo htmlspecialchars($user['username']); ?> · Role: <?php echo htmlspecialchars(getRoleDisplayName($user['role'])); ?> · Group: <?php echo htmlspecialchars($user['group_name'] ?? 'none'); ?></p>
        </div>
        <a href="manage-users.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-sm">← Back to Users</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 my-4"><p class="text-green-800 font-semibold">✓ Overrides saved.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 my-4"><p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p></div>
    <?php endif; ?>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 my-4 text-sm text-blue-900">
        <strong>How it works:</strong> the user's group grants the baseline. Per-user settings below override it:
        <span class="font-semibold">Grant</span> adds a permission the group lacks,
        <span class="font-semibold">Deny</span> removes one the group grants (deny always wins),
        <span class="font-semibold">Inherit</span> follows the group. Super admins bypass all checks.
    </div>

    <form method="POST" action="../../api/save-user-overrides.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($catalog as $section => $perms): ?>
                <div class="border rounded-lg p-4">
                    <p class="font-bold text-gray-900 text-sm mb-2"><?php echo htmlspecialchars($section); ?></p>
                    <?php foreach ($perms as $key => $label):
                        $inGroup = in_array($key, $groupPerms, true);
                        $ov = $overrides[$key] ?? null;
                        $effective = $ov !== null ? ($ov === 1) : $inGroup;
                    ?>
                        <div class="flex items-center justify-between gap-2 py-1 border-b border-gray-100 last:border-0">
                            <div class="text-sm">
                                <span class="text-gray-800"><?php echo htmlspecialchars($label); ?></span>
                                <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-semibold <?php echo $effective ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'; ?>"><?php echo $effective ? 'allowed' : 'denied'; ?></span>
                                <?php if ($inGroup): ?><span class="text-xs text-gray-400">· via group</span><?php endif; ?>
                            </div>
                            <select name="overrides[<?php echo $key; ?>]" class="text-xs border border-gray-300 rounded px-2 py-1">
                                <option value="" <?php echo $ov === null ? 'selected' : ''; ?>>Inherit</option>
                                <option value="grant" <?php echo $ov === 1 ? 'selected' : ''; ?>>Grant</option>
                                <option value="deny" <?php echo $ov === 0 ? 'selected' : ''; ?>>Deny</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex gap-4 justify-end mt-6">
            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Edit User Instead</a>
            <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Save Overrides</button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>

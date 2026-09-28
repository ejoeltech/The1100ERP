<?php
include '../../includes/session-check.php';
requirePermission('edit_user');

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: manage-users.php');
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: manage-users.php?error=User not found');
    exit;
}

$pageTitle = 'Edit User - ERP System';

// Groups for assignment dropdown
try {
    $groups = $pdo->query("SELECT id, name, description FROM user_groups ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $groups = [];
}
$isSuper = function_exists('isSuperAdmin') && isSuperAdmin();
$hasSuper = function_exists('systemHasSuperAdmin') ? systemHasSuperAdmin() : true;
$canMakeSuper = $isSuper || !$hasSuper;

include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Edit User</h2>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="../../api/update-user.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="username"
                        required
                        value="<?php echo htmlspecialchars($user['username']); ?>"
                        pattern="[a-zA-Z0-9_]+"
                        title="Only letters, numbers, and underscores allowed"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="full_name"
                        required
                        value="<?php echo htmlspecialchars($user['full_name']); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Email
                    </label>
                    <input 
                        type="email" 
                        name="email"
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Phone
                    </label>
                    <input 
                        type="tel" 
                        name="phone"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="role"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                        <?php if ($canMakeSuper || $user['role'] === 'super_admin'): ?><option value="super_admin" <?php echo $user['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin (Full system control)</option><?php endif; ?>
                        <?php if ($isSuper || $user['role'] === 'admin'): ?><option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin (Manage users & settings)</option><?php endif; ?>
                        <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager (View All, Edit Own)</option>
                        <option value="accountant" <?php echo $user['role'] === 'accountant' ? 'selected' : ''; ?>>Accountant (Invoices & payments)</option>
                        <option value="sales_rep" <?php echo $user['role'] === 'sales_rep' ? 'selected' : ''; ?>>Sales Rep (View Own Only)</option>
                        <option value="viewer" <?php echo $user['role'] === 'viewer' ? 'selected' : ''; ?>>Viewer (Read-only)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Group
                    </label>
                    <select
                        name="group_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                        <option value="">-- Keep current --</option>
                        <?php foreach ($groups as $g): ?>
                            <?php if ($g['name'] === 'super_admin' && !$canMakeSuper && (string)($user['group_id'] ?? '') !== (string)$g['id']) continue; ?>
                            <?php if ($g['name'] === 'developer' && !$isSuper && (string)($user['group_id'] ?? '') !== (string)$g['id']) continue; ?>
                            <option value="<?php echo $g['id']; ?>" <?php echo (string)($user['group_id'] ?? '') === (string)$g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Group grants baseline permissions; <a class="text-primary font-semibold" href="user-permissions.php?id=<?php echo $user['id']; ?>">customize per-user permissions →</a></p>
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800 font-semibold mb-2">Set Temporary Password (Optional)</p>
                <p class="text-xs text-yellow-700 mb-4">Tick the box and enter a new temporary password. The user must change it at their next login. Relay it securely — never by email alone.</p>

                <label class="flex items-center gap-2 mb-3">
                    <input
                        type="checkbox"
                        name="reset_password"
                        value="1"
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary"
                    >
                    <span class="text-sm font-semibold text-gray-700">Set a new temporary password</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        placeholder="Temporary password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                    <input
                        type="password"
                        name="confirm_password"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    >
                </div>
            </div>
            
            <div>
                <label class="flex items-center gap-2">
                    <input 
                        type="checkbox" 
                        name="is_active"
                        value="1"
                        <?php echo $user['is_active'] ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary"
                    >
                    <span class="text-sm font-semibold text-gray-700">Active (User can login)</span>
                </label>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex gap-4 justify-end mt-8">
            <button 
                type="button"
                onclick="window.location.href='manage-users.php'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold"
            >
                Cancel
            </button>
            <button 
                type="submit"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold"
            >
                Update User
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
include '../../includes/session-check.php';
requirePermission('create_user');
require_once '../../includes/invites.php'; // invitesGloballyEnabled()

$pageTitle = 'Create User - ERP System';

// Groups for assignment dropdown
try {
    $groups = $pdo->query("SELECT id, name, description FROM user_groups ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $groups = [];
}
$isSuper = function_exists('isSuperAdmin') && isSuperAdmin();
$hasSuper = function_exists('systemHasSuperAdmin') ? systemHasSuperAdmin() : true;
$canMakeSuper = $isSuper || !$hasSuper;
$invitesOn = function_exists('invitesGloballyEnabled') && invitesGloballyEnabled();
$passMin = function_exists('passwordMinLength') ? passwordMinLength() : 12;

include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Create New User</h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!$hasSuper): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <p class="text-yellow-800 text-sm font-semibold">No super admin exists yet — the next user created will automatically become the Super Admin.</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="../../api/save-user.php">
        <?php echo csrfField(); ?>
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" required pattern="[a-zA-Z0-9_]+"
                        title="Only letters, numbers, and underscores allowed"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="johndoe">
                    <p class="text-xs text-gray-500 mt-1">Letters, numbers, underscores only</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="full_name" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="John Doe">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Email
                    </label>
                    <input type="email" name="email"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="john@eleven100erp.com.ng">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Phone
                    </label>
                    <input type="tel" name="phone"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="08012345678">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select name="role" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">-- Select Role --</option>
                        <?php if ($canMakeSuper): ?><option value="super_admin">Super Admin (Full system control)</option><?php endif; ?>
                        <?php if ($isSuper): ?><option value="admin">Admin (Manage users & settings)</option><?php endif; ?>
                        <option value="manager">Manager (View All, Edit Own)</option>
                        <option value="accountant">Accountant (Invoices & payments)</option>
                        <option value="sales_rep">Sales Rep (View Own Only)</option>
                        <option value="viewer">Viewer (Read-only)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Role drives ownership rules; group drives permissions</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Group
                    </label>
                    <select name="group_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">-- Auto (match role) --</option>
                        <?php foreach ($groups as $g): ?>
                            <?php if ($g['name'] === 'super_admin' && !$canMakeSuper) continue; ?>
                            <?php if ($g['name'] === 'developer' && !$isSuper) continue; ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Leave on auto to use the role's standard group</p>
                </div>
            </div>

            <?php if ($invitesOn): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800 font-semibold">No password needed</p>
                    <p class="text-xs text-blue-700 mt-1">The new user receives a one-time invite link (valid 48 hours) to set their own password. The link is shown once after creation — copy it for them.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Temporary Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required autocomplete="new-password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="At least <?php echo (int)$passMin; ?> characters">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required autocomplete="new-password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Repeat the password">
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-xs text-blue-700">Relay this password to the user securely (in person or a trusted channel — never by email alone). They must change it at first login.</p>
                </div>
            <?php endif; ?>

            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <span class="text-sm font-semibold text-gray-700">Active (User can login)</span>
                </label>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col md:flex-row gap-4 justify-end mt-8">
            <button type="button" onclick="window.location.href='manage-users.php'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-center">
                Cancel
            </button>
            <button type="submit"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-center">
                Create User
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
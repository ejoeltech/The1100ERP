<?php
include '../../includes/session-check.php';
requirePermission('create_user');

$pageTitle = 'Create User - Bluedots Technologies';

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

    <form method="POST" action="../../api/save-user.php">
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
                        placeholder="john@bluedots.com.ng">
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

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Role <span class="text-red-500">*</span>
                </label>
                <select name="role" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">-- Select Role --</option>
                    <option value="admin">Admin (Full Access)</option>
                    <option value="manager">Manager (View All, Edit Own)</option>
                    <option value="sales_rep">Sales Rep (View Own Only)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">This determines user permissions</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password" required minlength="6"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="Minimum 6 characters">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="confirm_password" required minlength="6"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="Re-enter password">
                </div>
            </div>

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
<?php
include '../../includes/session-check.php';

$pageTitle = 'Change Password - ERP System';

include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Change Password</h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800 font-semibold">✓ Password changed successfully!</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="../../api/change-password.php">
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Current Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="current_password" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="Enter your current password">
            </div>

            <div class="border-t border-gray-200 pt-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="new_password" required minlength="6"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Minimum 6 characters">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required minlength="6"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Re-enter new password">
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Password Requirements:</h4>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• Minimum 6 characters</li>
                    <li>• Should be unique and not easily guessable</li>
                    <li>• Consider using a mix of letters, numbers, and symbols</li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 justify-end mt-8">
            <button type="button" onclick="window.location.href='profile.php'"
                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                Cancel
            </button>
            <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Change Password
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
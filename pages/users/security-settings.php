<?php
// Security feature toggles (super_admin only).
include '../../includes/session-check.php';

if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    header('Location: manage-users.php?error=' . urlencode('Only a super admin can view security settings'));
    exit;
}

$pageTitle = 'Security Settings - ERP System';

$mfaOn = getSetting('security_mfa_enabled', '1') !== '0';
$invOn = getSetting('security_invites_enabled', '0') === '1';
$passMin = getSetting('security_password_min', '12');
if (!in_array($passMin, ['8', '10', '12'], true)) {
    $passMin = '12';
}

include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-900 mb-2">Security Settings</h2>
    <p class="text-gray-600 mb-6">Advanced authentication features. Changes apply immediately to every login.</p>

    <?php if (isset($_GET['saved'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800 font-semibold">✓ Security settings saved.</p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="../../api/save-security-settings.php">
        <?php echo csrfField(); ?>
        <div class="space-y-6">
            <div class="border rounded-lg p-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="security_mfa_enabled" value="0">
                    <input type="checkbox" name="security_mfa_enabled" value="1" <?php echo $mfaOn ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <span class="font-semibold text-gray-900">Two-factor authentication (opt-in)</span>
                </label>
                <p class="text-sm text-gray-600 mt-2 ml-8">When off, the 2FA enrollment page is hidden and the login code step is skipped for everyone — including already-enrolled accounts. Users keep their passwords.</p>
            </div>

            <div class="border rounded-lg p-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="security_invites_enabled" value="0">
                    <input type="checkbox" name="security_invites_enabled" value="1" <?php echo $invOn ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <span class="font-semibold text-gray-900">Invite links for onboarding</span>
                </label>
                <p class="text-sm text-gray-600 mt-2 ml-8">When off (recommended), admins set a temporary password when creating users, and the user changes it at first login. When on, new users get a 48-hour single-use invite link instead of a password.</p>
            </div>

            <div class="border rounded-lg p-4">
                <label class="block text-sm font-semibold text-gray-900 mb-2">Minimum password length</label>
                <select name="security_password_min"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="12" <?php echo $passMin === '12' ? 'selected' : ''; ?>>12 characters (strict)</option>
                    <option value="10" <?php echo $passMin === '10' ? 'selected' : ''; ?>>10 characters</option>
                    <option value="8" <?php echo $passMin === '8' ? 'selected' : ''; ?>>8 characters (relaxed)</option>
                </select>
                <p class="text-sm text-gray-600 mt-2">Common-password blocklist and username checks always apply. Never below 8.</p>
            </div>
        </div>

        <div class="flex justify-end mt-8">
            <button type="submit"
                class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Save Security Settings
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>

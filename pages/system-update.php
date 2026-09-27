<?php
// Permanent admin system tools: schema patcher + installer status.
// Survives cleanup.php (unlike maintenance/setup/run-schema-update.php).
include '../includes/session-check.php';
require_once '../includes/security.php';

requirePermission('manage_settings');

$pageTitle = 'System Update - ' . COMPANY_NAME;

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['run'] ?? '') === 'patch') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
    require_once '../includes/schema-patcher.php';
    $results = SchemaPatcher::run($pdo, dirname(__DIR__));
    if (function_exists('logAudit')) {
        logAudit('system_update', 'system', null, ['source' => 'system-update-page']);
    }
}

$installerPresent = is_dir('../maintenance/setup');
$csrf = generateCSRFToken();

include '../includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">System Update</h2>
    <p class="text-gray-600 mt-1">Database schema patcher and installer status.</p>
</div>

<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Database Schema</h3>
    <p class="text-sm text-gray-600 mb-4">Synchronizes tables and columns with the current codebase version. Safe to re-run (idempotent).</p>
    <form method="POST" onsubmit="return confirm('Run schema patcher now?');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="run" value="patch">
        <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
            Run Schema Patcher
        </button>
    </form>

    <?php if ($results !== null): ?>
        <div class="mt-6 space-y-1 text-sm">
            <?php foreach ($results as $e): ?>
                <?php
                $color = $e['status'] === 'ok' ? 'text-green-700' : ($e['status'] === 'error' ? 'text-red-700' : 'text-blue-700');
                $icon = $e['status'] === 'ok' ? '✓' : ($e['status'] === 'error' ? '✗' : 'ℹ️');
                ?>
                <p class="<?php echo $color; ?>"><?php echo $icon . ' ' . htmlspecialchars($e['message']); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-xl font-bold text-gray-900 mb-4">One-Time Installer</h3>
    <?php if ($installerPresent): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
            <p class="text-sm text-red-800 font-semibold">Installer still present: <code>maintenance/setup/</code></p>
            <p class="text-sm text-red-700 mt-1">Remove it after setup. Requires your admin password.</p>
        </div>
        <form id="removeInstallerForm" onsubmit="return removeInstaller(event);">
            <div class="flex gap-3 items-end">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admin Password</label>
                    <input type="password" id="removePassword" required
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                </div>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                    Delete Installer
                </button>
            </div>
        </form>
        <p id="removeStatus" class="text-sm mt-3"></p>
    <?php else: ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4">
            <p class="text-sm text-green-800 font-semibold">Installer removed. Production posture clean.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    async function removeInstaller(event) {
        event.preventDefault();
        if (!confirm('Permanently delete maintenance/ (installer + leftovers)?')) return false;
        const status = document.getElementById('removeStatus');
        status.innerHTML = '<span class="text-gray-600">Deleting...</span>';
        const formData = new FormData();
        formData.append('password', document.getElementById('removePassword').value);
        formData.append('csrf_token', '<?php echo $csrf; ?>');
        try {
            const response = await fetch('../api/system/remove-installer.php', { method: 'POST', body: formData });
            const result = await response.json();
            status.innerHTML = result.success
                ? '<span class="text-green-600">' + result.message + '</span> Refresh to confirm.'
                : '<span class="text-red-600">Failed: ' + result.message + '</span>';
        } catch (e) {
            status.innerHTML = '<span class="text-red-600">Error: ' + e.message + '</span>';
        }
        return false;
    }
</script>

<?php include '../includes/footer.php'; ?>

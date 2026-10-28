<?php
// About: version info + update check. Visible to every logged-in user;
// the live git comparison only renders for users with manage_settings.
include '../includes/session-check.php';
require_once '../includes/version.php';

$pageTitle = 'About - ' . APP_NAME;

$repo = function_exists('getSetting') ? trim((string)getSetting('github_repo', '')) : '';
if ($repo === '') $repo = APP_REPO_DEFAULT;
$canManage = function_exists('hasPermission') && hasPermission('manage_settings');

include '../includes/header.php';
?>
<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">About <?php echo htmlspecialchars(APP_NAME); ?></h2>
    <p class="text-gray-600 mt-1">Version information and updates</p>
</div>

<div class="max-w-3xl space-y-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary text-white rounded-lg flex items-center justify-center text-xl font-bold">11</div>
            <div>
                <p class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars(APP_NAME); ?></p>
                <p class="text-sm text-gray-500 font-mono">v<?php echo htmlspecialchars(APP_VERSION); ?></p>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-100 text-sm space-y-1">
            <p><span class="text-gray-500">Updates source:</span>
                <a class="text-primary hover:underline font-mono" target="_blank" rel="noopener"
                   href="https://github.com/<?php echo htmlspecialchars($repo); ?>"><?php echo htmlspecialchars($repo); ?></a>
            </p>
            <p><span class="text-gray-500">PHP:</span> <span class="font-mono"><?php echo htmlspecialchars(PHP_VERSION); ?></span></p>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="https://github.com/<?php echo htmlspecialchars($repo); ?>/releases" target="_blank" rel="noopener"
               class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 font-semibold text-sm">View releases</a>
            <?php if ($canManage): ?>
            <button type="button" onclick="aboutCheckUpdates()" id="about-check-btn"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-sm">Check for updates</button>
            <a href="settings.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-sm">Open Maintenance</a>
            <?php endif; ?>
        </div>
        <div id="about-update-result" class="mt-3 text-sm"></div>
    </div>
</div>

<?php if ($canManage): ?>
<script>
async function aboutCheckUpdates() {
    const btn = document.getElementById('about-check-btn');
    const out = document.getElementById('about-update-result');
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    btn.disabled = true;
    btn.textContent = 'Checking...';
    out.innerHTML = '<span class="text-gray-500">Contacting update source...</span>';
    try {
        const res = await fetch('../api/system/git-status.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Check failed');

        const local = 'v' + esc(data.local_version);
        const latest = data.latest_version ? 'v' + esc(data.latest_version) : '';
        const link = data.latest_url
            ? ' <a class="text-primary hover:underline" target="_blank" rel="noopener" href="' + esc(data.latest_url) + '">release notes</a>'
            : '';

        let html;
        if (data.update_available) {
            html = '<span class="text-amber-700 font-semibold">Update available:</span> <span class="font-mono">' + local + ' → ' + latest + '</span>' + link;
        } else if (latest) {
            html = '<span class="text-green-700 font-semibold">You are up to date</span> <span class="font-mono text-gray-500">(' + local + ' · latest ' + latest + ')</span>' + link;
        } else {
            html = '<span class="text-green-700 font-semibold">You are up to date</span> <span class="font-mono text-gray-500">(' + local + ')</span>';
        }
        if (data.warning) {
            html += '<div class="text-amber-700 mt-1">' + esc(data.warning) + '</div>';
        }
        out.innerHTML = html;
    } catch (e) {
        out.innerHTML = '<span class="text-red-600">Check failed: ' + esc(e.message) + '</span>';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Check for updates';
    }
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

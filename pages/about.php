<?php
// About: version info + update check. Update actions are limited to super
// admins and developer admins; all other users only see version information.
include '../includes/session-check.php';
require_once '../includes/version.php';

$pageTitle = 'About - ' . APP_NAME;

$repo = function_exists('getSetting') ? trim((string)getSetting('github_repo', '')) : '';
if ($repo === '') $repo = APP_REPO_DEFAULT;
$canManage = function_exists('hasPermission') && hasPermission('manage_settings');
$canUpdate = function_exists('canManageUpdates') && canManageUpdates();
$csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : '';

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
            <?php if ($canUpdate): ?>
            <button type="button" onclick="aboutCheckUpdates()" id="about-check-btn"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold text-sm">Check for updates</button>
            <a href="#" id="about-download-btn" target="_blank" rel="noopener" style="display:none"
                class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 font-semibold text-sm">Download update</a>
            <button type="button" onclick="aboutOpenUpdate('apply')" id="about-update-btn" style="display:none"
                class="px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 font-semibold text-sm">Update now</button>
            <button type="button" onclick="aboutOpenUpdate('rollback')" id="about-rollback-btn" style="display:none"
                class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold text-sm">Rollback last update</button>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <a href="settings.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold text-sm">Open Maintenance</a>
            <?php endif; ?>
        </div>
        <div id="about-update-result" class="mt-3 text-sm"></div>
        <?php if (!$canUpdate && $canManage): ?>
            <p class="text-xs text-gray-500 mt-3">Update download, apply and rollback are limited to super admins and developer admins.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($canUpdate): ?>
<div id="aboutUpdateModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 id="aboutModalTitle" class="text-xl font-bold text-gray-900 mb-2">Apply update</h3>
        <p id="aboutModalText" class="text-sm text-gray-600 mb-4"></p>
        <div id="aboutModalMeta" class="text-xs text-gray-500 mb-4 space-y-1"></div>
        <form id="aboutUpdateForm" onsubmit="return aboutSubmitUpdate(event)" class="space-y-3">
            <label id="aboutBackupWrap" class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" id="aboutBackupConfirm" class="mt-0.5">
                <span>I confirm I have a current backup of the database and application files.</span>
            </label>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Admin password</label>
                <input type="password" id="aboutPassword" required autocomplete="current-password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Type <span id="aboutConfirmHint" class="font-mono">APPLY</span> to confirm</label>
                <input type="text" id="aboutConfirmText" required autocomplete="off"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono focus:ring-2 focus:ring-primary">
            </div>
            <p id="aboutModalStatus" class="text-sm"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="aboutCloseUpdate()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold">Cancel</button>
                <button type="submit" id="aboutSubmitBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Apply update</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
window.ABOUT_CSRF = <?php echo json_encode($csrf); ?>;
window.ABOUT_VERSION = <?php echo json_encode(APP_VERSION); ?>;
let aboutState = {};
let aboutMode = 'apply';

function aboutEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function aboutCmp(a, b) {
    const pa = String(a || '').replace(/^v/i, '').split('.').map(n => parseInt(n, 10) || 0);
    const pb = String(b || '').replace(/^v/i, '').split('.').map(n => parseInt(n, 10) || 0);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
        if ((pa[i] || 0) !== (pb[i] || 0)) return (pa[i] || 0) > (pb[i] || 0) ? 1 : -1;
    }
    return 0;
}
function aboutSyncButtons(data) {
    const download = document.getElementById('about-download-btn');
    const update = document.getElementById('about-update-btn');
    const rollback = document.getElementById('about-rollback-btn');
    const asset = data && data.latest_asset ? data.latest_asset : null;
    if (download) {
        if (asset && asset.url) {
            download.href = asset.url;
            download.style.display = '';
            download.textContent = 'Download update (' + asset.name + ')';
        } else {
            download.style.display = 'none';
        }
    }
    if (update) update.style.display = data && data.update_available ? '' : 'none';
    if (rollback) rollback.style.display = data && data.last_update_backup ? '' : 'none';
}
async function aboutCheckUpdates() {
    const btn = document.getElementById('about-check-btn');
    const out = document.getElementById('about-update-result');
    btn.disabled = true;
    btn.textContent = 'Checking...';
    out.innerHTML = '<span class="text-gray-500">Contacting update source...</span>';
    try {
        const res = await fetch('../api/system/git-status.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Check failed');
        aboutState = data;
        aboutSyncButtons(data);

        const local = 'v' + aboutEsc(data.local_version);
        const latest = data.latest_version ? 'v' + aboutEsc(data.latest_version) : '';
        const link = data.latest_url
            ? ' <a class="text-primary hover:underline" target="_blank" rel="noopener" href="' + aboutEsc(data.latest_url) + '">release notes</a>'
            : '';
        let html;
        if (data.update_available) {
            html = '<span class="text-amber-700 font-semibold">Update available:</span> <span class="font-mono">' + local + ' → ' + latest + '</span>' + link;
            if (!data.latest_asset) {
                html += '<div class="text-amber-700 mt-1">The latest release has no update ZIP asset.</div>';
            } else if (!data.latest_checksum) {
                html += '<div class="text-amber-700 mt-1">The latest release is missing its SHA-256 checksum asset; applying is blocked.</div>';
            } else {
                html += '<div class="text-gray-500 mt-1">Update asset: <span class="font-mono">' + aboutEsc(data.latest_asset.name) + '</span></div>';
            }
        } else if (latest && aboutCmp(data.local_version, data.latest_version) > 0) {
            html = '<span class="text-blue-700 font-semibold">Development build</span> <span class="font-mono text-gray-500">(' + local + ' is ahead of latest ' + latest + ')</span>' + link;
        } else if (latest) {
            html = '<span class="text-green-700 font-semibold">You are up to date</span> <span class="font-mono text-gray-500">(' + local + ' · latest ' + latest + ')</span>' + link;
        } else {
            html = '<span class="text-green-700 font-semibold">You are up to date</span> <span class="font-mono text-gray-500">(' + local + ')</span>';
        }
        if (data.last_update_backup) {
            html += '<div class="text-gray-500 mt-1">Last update backup: <span class="font-mono">' + aboutEsc(data.last_update_backup.id) + '</span> · ' + aboutEsc(data.last_update_backup.file_count || 0) + ' file(s)</div>';
        }
        if (data.warning) html += '<div class="text-amber-700 mt-1">' + aboutEsc(data.warning) + '</div>';
        out.innerHTML = html;
    } catch (e) {
        out.innerHTML = '<span class="text-red-600">Check failed: ' + aboutEsc(e.message) + '</span>';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Check for updates';
    }
}
function aboutOpenUpdate(mode) {
    aboutMode = mode === 'rollback' ? 'rollback' : 'apply';
    const modal = document.getElementById('aboutUpdateModal');
    const title = document.getElementById('aboutModalTitle');
    const text = document.getElementById('aboutModalText');
    const meta = document.getElementById('aboutModalMeta');
    const backupWrap = document.getElementById('aboutBackupWrap');
    const backup = document.getElementById('aboutBackupConfirm');
    const hint = document.getElementById('aboutConfirmHint');
    const submit = document.getElementById('aboutSubmitBtn');
    const status = document.getElementById('aboutModalStatus');
    status.textContent = '';
    document.getElementById('aboutPassword').value = '';
    document.getElementById('aboutConfirmText').value = '';

    if (aboutMode === 'apply') {
        title.textContent = 'Apply GitHub update';
        text.textContent = 'The latest release will be downloaded, verified and applied. Existing files are backed up automatically before extraction.';
        meta.innerHTML = 'Current: <span class="font-mono">v' + aboutEsc(window.ABOUT_VERSION) + '</span><br>Target: <span class="font-mono">' + aboutEsc(aboutState.latest_version || '') + '</span>' + (aboutState.latest_asset ? '<br>Asset: <span class="font-mono">' + aboutEsc(aboutState.latest_asset.name) + '</span>' : '');
        backupWrap.style.display = '';
        backup.checked = false;
        hint.textContent = 'APPLY';
        submit.textContent = 'Apply update';
        submit.className = 'px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 font-semibold';
    } else {
        title.textContent = 'Rollback last update';
        text.textContent = 'Restore the files saved before the last applied update. Database schema changes are additive and are not reversed.';
        meta.innerHTML = aboutState.last_update_backup ? 'Backup: <span class="font-mono">' + aboutEsc(aboutState.last_update_backup.id) + '</span><br>Version: <span class="font-mono">v' + aboutEsc(aboutState.last_update_backup.app_version || '') + '</span>' : 'No update backup is available.';
        backupWrap.style.display = 'none';
        hint.textContent = 'ROLLBACK';
        submit.textContent = 'Rollback files';
        submit.className = 'px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold';
    }
    modal.classList.remove('hidden');
    document.getElementById('aboutPassword').focus();
}
function aboutCloseUpdate() {
    document.getElementById('aboutUpdateModal').classList.add('hidden');
}
async function aboutSubmitUpdate(event) {
    event.preventDefault();
    const status = document.getElementById('aboutModalStatus');
    const btn = document.getElementById('aboutSubmitBtn');
    if (aboutMode === 'apply' && !document.getElementById('aboutBackupConfirm').checked) {
        status.className = 'text-sm text-red-600';
        status.textContent = 'Confirm that you have a current backup first.';
        return false;
    }
    const expected = aboutMode === 'apply' ? 'APPLY' : 'ROLLBACK';
    if (document.getElementById('aboutConfirmText').value !== expected) {
        status.className = 'text-sm text-red-600';
        status.textContent = 'Type ' + expected + ' exactly to confirm.';
        return false;
    }
    const fd = new FormData();
    fd.append('csrf_token', window.ABOUT_CSRF);
    fd.append('confirm', expected);
    fd.append('admin_password', document.getElementById('aboutPassword').value);
    if (aboutMode === 'apply') fd.append('confirm_backup', '1');

    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = aboutMode === 'apply' ? 'Downloading and applying...' : 'Rolling back...';
    status.className = 'text-sm text-gray-600';
    status.textContent = 'Please wait. Do not close this page.';
    try {
        const endpoint = aboutMode === 'apply' ? '../api/system/update-from-github.php' : '../api/system/rollback-update.php';
        const res = await fetch(endpoint, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || ('HTTP ' + res.status));
        status.className = 'text-sm text-green-700';
        status.textContent = data.message + ' Reloading...';
        setTimeout(() => window.location.reload(), 1800);
    } catch (e) {
        status.className = 'text-sm text-red-600';
        status.textContent = 'Failed: ' + e.message;
        btn.disabled = false;
        btn.textContent = old;
    }
    return false;
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') aboutCloseUpdate();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

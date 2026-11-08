<?php
// Apply the latest GitHub update asset after admin re-authentication.
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/invites.php';
require_once '../../includes/version.php';
require_once '../../includes/updates.php';
require_once '../../includes/update-manager.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    updateFail('Invalid request method.', 405);
}
updateRequireOperator();
requireCsrf();
updateVerifyReauth($pdo, 'APPLY');

if (($_POST['confirm_backup'] ?? '') !== '1') {
    updateFail('Confirm that you have a current backup before applying an update.', 400);
}

$repo = function_exists('getSetting') ? trim((string)getSetting('github_repo', '')) : '';
if ($repo === '') $repo = APP_REPO_DEFAULT;

$tmp = null;
try {
    $release = githubLatestRelease($repo);
    if (!appVersionIsNewer($release['version'], APP_VERSION)) {
        updateFail('No newer release is available (current v' . APP_VERSION . ', latest v' . $release['version'] . ').', 400);
    }

    $assets = appFindUpdateAssets($release);
    if (!$assets['zip']) {
        throw new Exception('The latest release has no matching update ZIP asset.');
    }
    if (!$assets['checksum']) {
        throw new Exception('The latest release is missing the required SHA-256 checksum asset.');
    }
    if (!appDownloadHostAllowed($assets['zip']['url']) || !appDownloadHostAllowed($assets['checksum']['url'])) {
        throw new Exception('Release asset URLs are not on an allowed GitHub host.');
    }

    $sumResponse = appHttpGetJson($assets['checksum']['url'], 20);
    if ($sumResponse['status'] !== 200 || $sumResponse['body'] === '') {
        throw new Exception('Could not download the update checksum.');
    }
    if (!preg_match('/\b([a-f0-9]{64})\b/i', (string)$sumResponse['body'], $m)) {
        throw new Exception('The update checksum file is invalid.');
    }
    $expected = strtolower($m[1]);

    $tmp = tempnam(sys_get_temp_dir(), 'erp-update-');
    if ($tmp === false) throw new Exception('Could not create a temporary update file.');

    appHttpDownloadFile($assets['zip']['url'], $tmp, 25 * 1024 * 1024, 60);
    $actual = strtolower((string)hash_file('sha256', $tmp));
    if (!hash_equals($expected, $actual)) {
        throw new Exception('The downloaded update failed its SHA-256 check.');
    }

    $root = realpath(__DIR__ . '/../../');
    $result = updateApplyArchive($tmp, $root, [
        'source' => 'github',
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
        'release' => $release['tag'],
    ]);
    $output = (string)($result['output'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, created_at) VALUES (?, 'system_update_github', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], json_encode([
            'repo' => $repo,
            'release' => $release['tag'],
            'asset' => $assets['zip']['name'],
            'sha256' => $actual,
            'backup_id' => $result['backup']['id'] ?? null,
            'output' => $output,
        ])]);
    } catch (Throwable $auditError) {
        error_log('Update audit failed: ' . $auditError->getMessage());
    }

    $message = 'Updated to ' . $release['tag'] . '. ' . strip_tags($output);
    if (!empty($result['schema_errors'])) {
        $message .= ' Schema warnings were reported — review the output or roll back to the previous files.';
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'release' => $release['tag'],
        'backup_id' => $result['backup']['id'] ?? null,
        'schema_errors' => !empty($result['schema_errors']),
    ]);
} catch (Throwable $e) {
    error_log('GitHub update error: ' . $e->getMessage());
    updateFail($e->getMessage() !== '' ? $e->getMessage() : 'Update failed.', 500);
} finally {
    if ($tmp && is_file($tmp)) @unlink($tmp);
}

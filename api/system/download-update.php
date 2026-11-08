<?php
// Download the latest GitHub update asset after verifying its SHA-256.
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/invites.php';
require_once '../../includes/version.php';
require_once '../../includes/updates.php';
require_once '../../includes/update-manager.php';

updateRequireOperator();

$repo = function_exists('getSetting') ? trim((string)getSetting('github_repo', '')) : '';
if ($repo === '') $repo = APP_REPO_DEFAULT;

$tmp = null;
$sumTmp = null;
try {
    $release = githubLatestRelease($repo);
    if (!appVersionIsNewer($release['version'], APP_VERSION)) {
        updateFail('No newer release is available.', 400);
    }
    $assets = appFindUpdateAssets($release);
    if (!$assets['zip'] || !$assets['checksum']) {
        throw new Exception('The latest release does not have both the update ZIP and SHA-256 checksum assets.');
    }

    $zipUrl = appAssetApiUrl($repo, $assets['zip']);
    $sumUrl = appAssetApiUrl($repo, $assets['checksum']);
    if (!appDownloadHostAllowed($zipUrl) || !appDownloadHostAllowed($sumUrl)) {
        throw new Exception('Release asset URLs are not on an allowed GitHub host.');
    }

    $sumTmp = tempnam(sys_get_temp_dir(), 'erp-sum-');
    if ($sumTmp === false) throw new Exception('Could not create a temporary checksum file.');
    appHttpDownloadFile($sumUrl, $sumTmp, 1024 * 1024, 20);
    if (!preg_match('/\b([a-f0-9]{64})\b/i', (string)file_get_contents($sumTmp), $m)) {
        throw new Exception('The update checksum file is invalid.');
    }
    $expected = strtolower($m[1]);

    $tmp = tempnam(sys_get_temp_dir(), 'erp-update-');
    if ($tmp === false) throw new Exception('Could not create a temporary update file.');
    appHttpDownloadFile($zipUrl, $tmp, 25 * 1024 * 1024, 60);
    $actual = strtolower((string)hash_file('sha256', $tmp));
    if (!hash_equals($expected, $actual)) {
        throw new Exception('The downloaded update failed its SHA-256 check.');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($assets['zip']['name']) . '"');
    header('Content-Length: ' . (string)filesize($tmp));
    header('Cache-Control: no-store, private');
    readfile($tmp);
} catch (Throwable $e) {
    error_log('Update download error: ' . $e->getMessage());
    updateFail($e->getMessage() !== '' ? $e->getMessage() : 'Download failed.', 500);
} finally {
    if ($tmp && is_file($tmp)) @unlink($tmp);
    if ($sumTmp && is_file($sumTmp)) @unlink($sumTmp);
}

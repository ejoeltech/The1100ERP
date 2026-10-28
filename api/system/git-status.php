<?php
/**
 * API: update status.
 *
 * Primary signal is the app version vs. the latest GitHub release, so this
 * works on zip-deployed copies that have no .git directory. Git metadata is
 * included only when the server has a real working copy (dev/staging).
 */
include '../../includes/session-check.php';
require_once '../../config.php';
require_once '../../includes/version.php';
require_once '../../includes/updates.php';

// requirePermission exits on failure; returns void on success.
requirePermission('manage_settings');

header('Content-Type: application/json');

$repo = function_exists('getSetting') ? trim((string)getSetting('github_repo', '')) : '';
if ($repo === '') $repo = APP_REPO_DEFAULT;

$root = realpath(__DIR__ . '/../../');

/**
 * Run a git command inside the app root. Returns trim(stdout) or ''.
 * Never fetches: the release/tag API is the source of truth and avoids
 * blocking on credentials or network.
 */
function updateRunGit($root, $args)
{
    if (!$root || !is_dir($root . DIRECTORY_SEPARATOR . '.git') || !function_exists('exec')) {
        return '';
    }

    $git = 'git';
    if (stripos(PHP_OS, 'WIN') === 0) {
        $found = trim((string)@shell_exec('where git 2>NUL'));
        $first = strtok($found, "\r\n");
        if ($first !== false && $first !== '' && stripos($first, 'git.exe') !== false) {
            $git = '"' . $first . '"';
        } elseif (file_exists('C:\\Program Files\\Git\\cmd\\git.exe')) {
            $git = '"C:\\Program Files\\Git\\cmd\\git.exe"';
        }
    }

    $cmd = $git . ' -C ' . escapeshellarg($root) . ' ' . $args . ' 2>&1';
    $output = [];
    $rc = 0;
    @exec($cmd, $output, $rc);
    if ($rc !== 0) return '';
    return trim(implode("\n", $output));
}

try {
    $status = appUpdateStatus($repo, APP_VERSION);

    // Optional git metadata (only exists in a cloned working copy).
    $branch = updateRunGit($root, 'branch --show-current');
    if ($branch === '') $branch = updateRunGit($root, 'rev-parse --abbrev-ref HEAD');
    if ($branch === 'HEAD') $branch = '';

    $localCommit = updateRunGit($root, 'rev-parse --short HEAD');
    $remoteCommit = '';
    if ($branch !== '') {
        $remoteCommit = updateRunGit($root, 'rev-parse --short ' . escapeshellarg('origin/' . $branch));
    }
    $localChanges = $branch !== '' && updateRunGit($root, 'status --short') !== '';
    $hasGit = $branch !== '';

    $latestMessage = $status['release_name'] !== '' ? $status['release_name'] : ('v' . $status['latest_version']);
    if ($status['source'] === 'release' && $status['published_at'] !== '') {
        $ts = strtotime($status['published_at']);
        if ($ts) $latestMessage .= ' (released ' . date('Y-m-d', $ts) . ')';
    } elseif ($status['source'] === 'tag') {
        $latestMessage .= ' (tag only — no GitHub release published yet)';
    }

    echo json_encode(array_merge($status, [
        'branch' => $hasGit ? $branch : '',
        'local_commit' => $localCommit,
        'remote_commit' => $remoteCommit,
        'has_local_changes' => $localChanges,
        'can_git_pull' => $hasGit,
        'latest_message' => $latestMessage,
        'last_checked' => date('Y-m-d H:i:s'),
        'repo_url' => 'https://github.com/' . $repo,
    ]));

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'repo' => $repo,
        'local_version' => APP_VERSION,
        'repo_url' => 'https://github.com/' . $repo,
        'last_checked' => date('Y-m-d H:i:s'),
    ]);
}

<?php
/**
 * API: Get GitHub Update Status
 * Checks for remote updates and local changes.
 */
include '../../includes/session-check.php';
require_once '../../config.php';

// Check permission (requirePermission exits on failure; returns void on success)
requirePermission('manage_settings');

header('Content-Type: application/json');

function run_git($cmd) {
    $cwd = realpath(__DIR__ . '/../../');
    // Scope dubious-ownership trust to the app root only.
    putenv('GIT_CONFIG_COUNT=1');
    putenv('GIT_CONFIG_KEY_0=safe.directory');
    putenv('GIT_CONFIG_VALUE_0=' . $cwd);
    // Absolute git path: the service account's PATH may not include it.
    // Resolve via `where`, else the common install location, else PATH.
    $git = 'git';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $found = trim((string)shell_exec('where git 2>NUL'));
        $first = strtok($found, "\r\n");
        if ($first !== '' && $first !== false && stripos($first, 'git.exe') !== false) {
            $git = '"' . $first . '"';
        } elseif (file_exists('C:\\Program Files\\Git\\cmd\\git.exe')) {
            $git = '"C:\\Program Files\\Git\\cmd\\git.exe"';
        }
    } elseif (trim((string)shell_exec('command -v git')) === '') {
        throw new Exception('git binary not found on server.');
    }
    // Callers pass full "git ..." commands; strip to avoid `git git`.
    $cmd = preg_replace('/^\s*git\s+/', '', $cmd);
    $full_cmd = "cd /d \"$cwd\" && $git $cmd 2>&1";
    exec($full_cmd, $output, $return_var);
    return [
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

try {
    // 1. Fetch from remote
    $fetch = run_git("git fetch origin main");
    
    // 2. Get current branch
    $branch = run_git("git branch --show-current");
    $current_branch = trim($branch['output']);

    // 3. Get current commit
    $local_commit = run_git("git rev-parse --short HEAD");
    $local_hash = trim($local_commit['output']);

    // 4. Get remote commit
    $remote_commit = run_git("git rev-parse --short origin/$current_branch");
    $remote_hash = trim($remote_commit['output']);

    // 5. Check for local changes
    $status = run_git("git status --short");
    $has_local_changes = !empty(trim($status['output']));

    // 6. Get commit message of latest remote
    $log = run_git("git log -1 --format=\"%s (%cr)\" origin/$current_branch");
    $latest_msg = trim($log['output']);

    // Determine status
    $update_available = ($local_hash !== $remote_hash);

    echo json_encode([
        'success' => true,
        'branch' => $current_branch,
        'local_commit' => $local_hash,
        'remote_commit' => $remote_hash,
        'update_available' => $update_available,
        'has_local_changes' => $has_local_changes,
        'latest_message' => $latest_msg,
        'last_checked' => date('Y-m-d H:i:s'),
        'debug' => [
            'fetch_output' => $fetch['output']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'System error: ' . $e->getMessage()
    ]);
}

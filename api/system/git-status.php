<?php
/**
 * API: Get GitHub Update Status
 * Checks for remote updates and local changes.
 */
include '../../includes/session-check.php';
require_once '../../config.php';

// Check permission
if (!requirePermission('manage_settings', true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

function run_git($cmd) {
    $cwd = realpath(__DIR__ . '/../../');
    $full_cmd = "cd /d \"$cwd\" && $cmd 2>&1";
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

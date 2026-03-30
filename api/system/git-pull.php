<?php
/**
 * API: Pull GitHub Updates
 * Performs a git pull, handling stashing of local changes.
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
    $logs = [];
    $branch_res = run_git("git branch --show-current");
    $branch = trim($branch_res['output']);

    // 1. Check for local changes
    $status = run_git("git status --short");
    $has_changes = !empty(trim($status['output']));
    $stashed = false;

    if ($has_changes) {
        $logs[] = "Local changes detected. Stashing...";
        $stash_res = run_git("git stash");
        if ($stash_res['return_code'] !== 0) {
            throw new Exception("Git stash failed: " . $stash_res['output']);
        }
        $logs[] = $stash_res['output'];
        $stashed = true;
    }

    // 2. Perform Pull
    $logs[] = "Pulling updates from origin/$branch...";
    $pull_res = run_git("git pull origin $branch");
    $logs[] = $pull_res['output'];

    if ($pull_res['return_code'] !== 0) {
        // If pull fails, try to restore stash if we had one
        if ($stashed) {
            run_git("git stash pop");
        }
        throw new Exception("Git pull failed: " . $pull_res['output']);
    }

    // 3. Restore stash if needed
    if ($stashed) {
        $logs[] = "Restoring local changes (stash pop)...";
        $pop_res = run_git("git stash pop");
        $logs[] = $pop_res['output'];
    }

    // Log the activity
    logActivity($_SESSION['user_id'], 'maintenance', "System updated from GitHub (branch: $branch)");

    echo json_encode([
        'success' => true,
        'message' => "Successfully updated from GitHub.",
        'logs' => $logs,
        'stashed' => $stashed
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage(),
        'logs' => isset($logs) ? $logs : []
    ]);
}

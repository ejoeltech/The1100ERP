<?php
/**
 * API: Pull GitHub Updates
 * Performs a git pull, handling stashing of local changes.
 */
include '../../includes/session-check.php';
require_once '../../config.php';

// Check permission (requirePermission exits on failure; returns void on success)
requirePermission('manage_settings');

header('Content-Type: application/json');

// State-changing: POST only (central session-check gate enforces CSRF).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

function run_git($cmd) {
    $cwd = realpath(__DIR__ . '/../../');
    // Scope dubious-ownership trust to the app root only (the web user
    // rarely owns the repo). Env-scoped config avoids nested-quote mangling
    // through cmd; a global safe.directory would be broader.
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
    $logs = [];
    $branch_res = run_git("git branch --show-current");
    $branch = trim($branch_res['output']);
    // Branch feeds the pull command: strict allow-list (git output, not request input).
    if ($branch === '' || !preg_match('/^[A-Za-z0-9_\\/.-]+$/', $branch)) {
        error_log('Git pull branch diagnostics: rc=' . $branch_res['return_code'] . ' out=' . substr($branch_res['output'], 0, 200));
        throw new Exception('Cannot determine a safe branch name.');
    }

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

    // 2. Perform Pull (fast-forward only: never auto-merge on production)
    $logs[] = "Pulling updates from origin/$branch...";
    $pull_res = run_git("git pull --ff-only origin $branch");
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

    // Log the activity (audit.php is optional on minimal installs)
    if (function_exists('logAudit')) {
        logAudit('system_update', 'system', null, ['source' => 'git-pull', 'branch' => $branch]);
    }

    echo json_encode([
        'success' => true,
        'message' => "Successfully updated from GitHub.",
        'logs' => $logs,
        'stashed' => $stashed
    ]);

} catch (Exception $e) {
    error_log('Git pull error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Update failed. Check server logs.',
        'logs' => isset($logs) ? $logs : []
    ]);
}

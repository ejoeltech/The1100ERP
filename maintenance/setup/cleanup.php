<?php
// maintenance/setup/cleanup.php
// One-time installer self-delete. Deletes the ENTIRE maintenance/ folder.
// Requires admin login. Run once after setup + final schema check.
// Permanent equivalent after deletion: pages/system-update.php + api/system/*.

require_once dirname(__DIR__, 2) . '/includes/session-check.php';
require_once dirname(__DIR__, 2) . '/includes/security.php';

// Rigor parity with api/system/remove-installer.php (WP0-D):
// admin role + POST + CSRF + current password re-entry.
if (!isAdmin()) {
    http_response_code(403);
    die('Access denied: Administrator privileges required.');
}

$setupDir = __DIR__;
$maintenanceDir = dirname(__DIR__);

// Safety: realpath containment — never delete unless the target really is
// <project-root>/maintenance and this file really is <root>/maintenance/setup.
$rootReal = realpath(dirname(__DIR__, 2));
$maintReal = realpath($maintenanceDir);
$setupReal = realpath($setupDir);
if ($rootReal === false || $maintReal === false || $setupReal === false
    || dirname($maintReal) !== $rootReal || basename($maintReal) !== 'maintenance'
    || dirname($setupReal) !== $maintReal || basename($setupReal) !== 'setup'
    || basename(__FILE__) !== 'cleanup.php'
) {
    die('Safety stop: unexpected installer path.');
}

function delPath($path, &$log) {
    if (is_dir($path) && !is_link($path)) {
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            delPath($path . '/' . $item, $log);
        }
        if (@rmdir($path)) {
            $log[] = ['ok' => true, 'path' => $path, 'msg' => 'dir removed'];
        } else {
            $e = error_get_last();
            $log[] = ['ok' => false, 'path' => $path, 'msg' => 'rmdir failed: ' . ($e['message'] ?? 'unknown')];
        }
    } else {
        @chmod($path, 0777);
        if (@unlink($path)) {
            $log[] = ['ok' => true, 'path' => $path, 'msg' => 'file deleted'];
        } else {
            $e = error_get_last();
            $log[] = ['ok' => false, 'path' => $path, 'msg' => 'unlink failed: ' . ($e['message'] ?? 'unknown')];
        }
    }
}

if (isset($_POST['confirm']) && $_POST['confirm'] === 'YES') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed.');
    }
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    // Current password re-entry (same bar as remove-installer.php).
    $password = $_POST['password'] ?? '';
    if ($password === '') {
        die('Current password required.');
    }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(403);
        die('Password verification failed.');
    }

    $log = [];
    // Phase 1: delete everything under maintenance/ except this running file.
    // Also removes the spent install token and attempt log; the installed
    // marker lives in storage/ (outside maintenance/) and is kept.
    foreach (['install.token', '.install-attempts'] as $spent) {
        $p = $setupDir . '/' . $spent;
        if (file_exists($p)) {
            delPath($p, $log);
        }
    }
    // Phase 1: delete everything under maintenance/ except this running file
    $items = array_diff(scandir($maintenanceDir), ['.', '..']);
    foreach ($items as $item) {
        if ($item === 'setup') {
            // Inside setup/: delete everything except cleanup.php itself
            foreach (array_diff(scandir($setupDir), ['.', '..', 'cleanup.php']) as $sub) {
                delPath($setupDir . '/' . $sub, $log);
            }
            continue;
        }
        delPath($maintenanceDir . '/' . $item, $log);
    }
    $failures = array_values(array_filter($log, fn($r) => !$r['ok']));
    $self = $setupDir . '/cleanup.php';

    // In-request verification: after Phase 1, setup/ must hold ONLY this file
    // and maintenance/ must hold ONLY setup/. Anything else is reported loudly.
    // (Final existence of maintenance/ itself can only be confirmed after the
    // shutdown self-delete — use deploy/verify-deployment.sh on the live site.)
    $leftoverSetup = array_values(array_diff(scandir($setupDir), ['.', '..', 'cleanup.php']));
    $leftoverMaint = array_values(array_diff(scandir($maintenanceDir), ['.', '..', 'setup']));
    foreach (array_merge(
        array_map(fn($f) => $setupDir . '/' . $f, $leftoverSetup),
        array_map(fn($f) => $maintenanceDir . '/' . $f, $leftoverMaint)
    ) as $leftover) {
        $failures[] = ['ok' => false, 'path' => $leftover, 'msg' => 'still present after cleanup'];
    }

    // JSON mode for wizard Step 7 (fetch API)
    if (($_POST['format'] ?? '') === 'json') {
        header('Content-Type: application/json');
        if (empty($failures)) {
            register_shutdown_function(function () use ($setupDir, $maintenanceDir, $self) {
                @chmod($self, 0777);
                @unlink($self);
                @rmdir($setupDir);
                @rmdir($maintenanceDir);
            });
            echo json_encode([
                'success' => true,
                'message' => 'Deleted ' . count($log) . ' installer items. maintenance/ removed.',
                'deleted' => count($log),
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => count($failures) . ' of ' . count($log) . ' deletes failed.',
                'failures' => array_map(fn($r) => $r['path'] . ' — ' . $r['msg'], $failures),
            ]);
        }
        exit;
    }

    if (empty($failures)) {
        // Phase 2: self-delete on shutdown (Windows locks the running file),
        // then remove the now-empty setup/ and maintenance/ folders.
        register_shutdown_function(function () use ($setupDir, $maintenanceDir, $self) {
            @chmod($self, 0777);
            @unlink($self);
            @rmdir($setupDir);
            @rmdir($maintenanceDir);
        });
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Installer Removed</title></head>
        <body style="font-family:sans-serif;padding:40px;max-width:760px;margin:auto;">
            <h1>Phase 1 complete: <?php echo count($log); ?> items deleted</h1>
            <p>Self-delete of <code>cleanup.php</code> + folder is scheduled on shutdown. <strong>Refresh this page or check via FTP/File Manager:</strong> if <code>maintenance/</code> still exists, delete the leftovers manually.</p>
            <ul>
                <?php foreach ($log as $r): ?>
                    <li><?php echo htmlspecialchars($r['path']); ?> — <?php echo htmlspecialchars($r['msg']); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="../../dashboard.php">Go to Dashboard</a> | <a href="../../login.php">Go to Login</a></p>
        </body>
        </html>
        <?php
        exit;
    }
    // Failures: show exactly what remains + why
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Cleanup Incomplete</title></head>
    <body style="font-family:sans-serif;padding:40px;max-width:760px;margin:auto;">
        <h1>Cleanup incomplete — <?php echo count($failures); ?> of <?php echo count($log); ?> failed</h1>
        <p>PHP user: <code><?php echo htmlspecialchars(get_current_user() . ' / ' . php_uname()); ?></code></p>
        <ul>
            <?php foreach ($log as $r): ?>
                <li style="color:<?php echo $r['ok'] ? 'green' : 'red'; ?>">
                    <?php echo htmlspecialchars($r['path']); ?> — <?php echo htmlspecialchars($r['msg']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p>Fallback: delete <code>maintenance/</code> via FTP/File Manager (cPanel &gt; File Manager &gt; right-click &gt; Delete), or run in a terminal from the project root:</p>
        <pre>rmdir /S maintenance   (Windows)&#10;rm -rf maintenance    (Linux)</pre>
        <form method="POST"><input type="hidden" name="confirm" value="YES">
            <button type="submit">Retry delete</button>
            <a href="../../dashboard.php">Cancel</a>
        </form>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Installer - WARNING</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-red-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full text-center border-t-8 border-red-600">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">DELETE INSTALLER</h1>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 text-left text-sm">
            <p class="font-bold mb-2">IRREVERSIBLE - READ FIRST</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Deletes the whole <code>maintenance/</code> folder (installer + leftovers) entirely.</li>
                <li>Includes wizard, <code>run-schema-update.php</code>, <code>factory-reset.php</code>, <code>tools/</code>, this file.</li>
                <li>Run <code>run-schema-update.php</code> first, then delete.</li>
                <li>Reinstall later requires re-uploading the folder.</li>
            </ul>
        </div>
        <form method="POST">
            <input type="hidden" name="confirm" value="YES">
            <?php echo function_exists('csrfField') ? csrfField() : ''; ?>
            <div style="text-align:left;margin-bottom:12px;">
                <label style="display:block;font-weight:bold;margin-bottom:4px;">Current admin password (re-entry required)</label>
                <input type="password" name="password" required autocomplete="current-password"
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;">
            </div>
            <button type="submit" class="w-full px-6 py-4 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">
                YES, DELETE maintenance/
            </button>
            <a href="../../dashboard.php" class="block w-full mt-3 px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300">
                No, Cancel
            </a>
        </form>
    </div>
</body>
</html>

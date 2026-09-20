<?php
// maintenance/setup/cleanup.php
// One-time installer self-delete. Deletes the entire maintenance/setup/ folder.
// Requires admin login. Run once after setup + run-schema-update.php.

require_once dirname(__DIR__, 2) . '/includes/session-check.php';

requirePermission('manage_settings');

$setupDir = __DIR__;

// Safety: never delete unless path ends in maintenance/setup
if (basename($setupDir) !== 'setup' || basename(dirname($setupDir)) !== 'maintenance') {
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
    $log = [];
    // Phase 1: delete everything except this running file
    $items = array_diff(scandir($setupDir), ['.', '..', 'cleanup.php']);
    foreach ($items as $item) {
        delPath($setupDir . '/' . $item, $log);
    }
    $failures = array_filter($log, fn($r) => !$r['ok']);
    $self = $setupDir . '/cleanup.php';

    if (empty($failures)) {
        // Phase 2: self-delete on shutdown (Windows locks the running file)
        register_shutdown_function(function () use ($setupDir, $self) {
            @chmod($self, 0777);
            @unlink($self);
            @rmdir($setupDir);
        });
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Installer Removed</title></head>
        <body style="font-family:sans-serif;padding:40px;max-width:760px;margin:auto;">
            <h1>Phase 1 complete: <?php echo count($log); ?> items deleted</h1>
            <p>Self-delete of <code>cleanup.php</code> + folder is scheduled on shutdown. <strong>Refresh this page or check via FTP/File Manager:</strong> if <code>maintenance/setup/</code> still exists, delete the one remaining file manually.</p>
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
        <p>Fallback: delete <code>maintenance/setup/</code> via FTP/File Manager (cPanel &gt; File Manager &gt; right-click &gt; Delete), or run in a terminal from the project root:</p>
        <pre>rmdir /S maintenance\setup   (Windows)&#10;rm -rf maintenance/setup    (Linux)</pre>
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
                <li>Deletes <code>maintenance/setup/</code> entirely.</li>
                <li>Includes wizard, <code>run-schema-update.php</code>, <code>factory-reset.php</code>, <code>tools/</code>, this file.</li>
                <li>Run <code>run-schema-update.php</code> first, then delete.</li>
                <li>Reinstall later requires re-uploading the folder.</li>
            </ul>
        </div>
        <form method="POST">
            <input type="hidden" name="confirm" value="YES">
            <button type="submit" class="w-full px-6 py-4 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">
                YES, DELETE maintenance/setup/
            </button>
            <a href="../../dashboard.php" class="block w-full mt-3 px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300">
                No, Cancel
            </a>
        </form>
    </div>
</body>
</html>

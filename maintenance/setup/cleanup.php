<?php
// maintenance/setup/cleanup.php
// One-time installer self-delete. Deletes the entire maintenance/setup/ folder.
// Requires admin login. Run once after setup + run-schema-update.php.

require_once dirname(__DIR__, 2) . '/includes/session-check.php';

requirePermission('manage_settings');

$setupDir = __DIR__;
$rootDir = dirname(__DIR__, 2);

// Safety: never delete unless path ends in maintenance/setup
if (basename($setupDir) !== 'setup' || basename(dirname($setupDir)) !== 'maintenance') {
    die('Safety stop: unexpected installer path.');
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @chmod($path, 0777);
            @unlink($path);
        }
    }
    @rmdir($dir);
}

if (isset($_POST['confirm']) && $_POST['confirm'] === 'YES') {
    // Delete everything except this running file first
    $items = array_diff(scandir($setupDir), ['.', '..', 'cleanup.php']);
    foreach ($items as $item) {
        $path = $setupDir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @chmod($path, 0777);
            @unlink($path);
        }
    }
    // Self-delete on shutdown (Windows locks the running file)
    register_shutdown_function(function () use ($setupDir) {
        @unlink($setupDir . '/cleanup.php');
        @rmdir($setupDir);
    });
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Installer Removed</title></head>
    <body style="font-family:sans-serif;padding:40px;text-align:center;">
        <h1>Installer deleted</h1>
        <p><code>maintenance/setup/</code> and all one-time tools were removed.</p>
        <p><a href="../../dashboard.php">Go to Dashboard</a> | <a href="../../login.php">Go to Login</a></p>
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

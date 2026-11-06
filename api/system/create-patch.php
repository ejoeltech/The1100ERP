<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';

requirePermission('manage_settings');

// Set filename
$filename = 'update_package_' . date('Y-m-d_H-i-s') . '.zip';

// Create temp file
$tmpFile = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();

if ($zip->open($tmpFile, ZipArchive::CREATE) !== TRUE) {
    die("Cannot open <$tmpFile>\n");
}

$rootPath = realpath(__DIR__ . '/../../');

// Parse timeframe parameter
// Parse timeframe parameter
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'custom';
$hours = isset($_GET['hours']) ? (int) $_GET['hours'] : 0;
$minutes = isset($_GET['minutes']) ? (int) $_GET['minutes'] : 0;

$cutoffTime = 0;

if ($timeframe === 'full') {
    $cutoffTime = 0;
} else if ($hours > 0 || $minutes > 0) {
    // Custom time
    $totalSeconds = ($hours * 3600) + ($minutes * 60);
    $cutoffTime = time() - $totalSeconds;
} else {
    // Default fallback if nothing specified -> Full
    $cutoffTime = 0;
}

// Create recursive directory iterator
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    // Skip directories (they would be added automatically)
    if (!$file->isDir()) {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootPath) + 1);

        // EXCLUSIONS
        // Skip .git, .vscode, node_modules, config.php, uploads, logs, brain, .gemini, database/backups.
        // WP: also never bundle secrets or server-side state (.env, storage, tmp).
        if (
            strpos($relativePath, '.git') === 0 ||
            strpos($relativePath, '.vscode') === 0 ||
            strpos($relativePath, 'node_modules') === 0 ||
            strpos($relativePath, 'brain') === 0 ||
            strpos($relativePath, '.gemini') === 0 ||
            strpos($relativePath, 'delete-me') === 0 ||
            strpos($relativePath, 'logs') === 0 ||
            strpos($relativePath, 'uploads') === 0 ||
            strpos($relativePath, 'storage') === 0 ||
            strpos($relativePath, 'tmp') === 0 ||
            strpos($relativePath, 'database' . DIRECTORY_SEPARATOR . 'backups') === 0 ||
            strpos($relativePath, 'docs') === 0 ||
            $relativePath === 'config.php' ||
            $relativePath === 'Actual' ||
            $relativePath === '.env' ||
            $relativePath === '.env.example' ||
            preg_match('/\.md$/i', $relativePath)
        ) {
            continue;
        }

        // Optional Exclusions via GET params - for Minimal Patches
        // Exclude Vendor: skips vendor/ folder (useful if mpdf is heavy and already on server)
        if (isset($_GET['exclude_vendor']) && $_GET['exclude_vendor'] == '1') {
            if (strpos($relativePath, 'vendor') === 0)
                continue;
        }

        // Exclude Setup: skips setup/ folder (once installed, rarely needed for updates)
        if (isset($_GET['exclude_setup']) && $_GET['exclude_setup'] == '1') {
            if (strpos($relativePath, 'setup') === 0)
                continue;
        }

        // Exclude Database: skips database/ folder (raw scripts not needed if applying code only)
        if (isset($_GET['exclude_database']) && $_GET['exclude_database'] == '1') {
            if (strpos($relativePath, 'database') === 0)
                continue;
        }


        // Time-based filtering
        if ($cutoffTime > 0) {
            if ($file->getMTime() < $cutoffTime) {
                continue;
            }
        }

        // Normalize slashes for ZIP (always use /)
        $zipPath = str_replace('\\', '/', $relativePath);

        // apply-patch.php deliberately rejects .htaccess entries. Ship them
        // as .new files and let update_script.php move them into place.
        if (basename($zipPath) === '.htaccess') {
            $zipPath .= '.new';
        }

        // Add current file to archive
        $zip->addFile($filePath, $zipPath);
    }
}



// uploads/ is excluded from patch contents, but its static guard is code.
// Add the known .htaccess guards explicitly as .new files.
foreach (['.htaccess', 'exports/.htaccess', 'uploads/.htaccess'] as $htPath) {
    $abs = $rootPath . '/' . $htPath;
    if (is_file($abs) && $zip->locateName($htPath . '.new') === false) {
        $zip->addFile($abs, $htPath . '.new');
    }
}

// The patch applier can run this after extraction. It installs .htaccess
// guards, applies schema changes, and clears opcache.
$updateScript = <<<'PHP'
<?php
set_time_limit(300);
$root = __DIR__;
foreach (['.htaccess', 'exports/.htaccess', 'uploads/.htaccess'] as $ht) {
    $src = $root . '/' . $ht . '.new';
    $dst = $root . '/' . $ht;
    if (is_file($src)) {
        @rename($src, $dst);
        if (is_file($src)) { @copy($src, $dst); @unlink($src); }
    }
}
try {
    require_once $root . '/includes/schema-patcher.php';
    $entries = SchemaPatcher::run($pdo, $root);
    $errors = 0;
    foreach ($entries as $entry) {
        if (($entry['status'] ?? '') === 'error') {
            $errors++;
            echo 'SCHEMA ERROR: ' . ($entry['message'] ?? '') . "\n";
        }
    }
    echo 'Schema: ' . count($entries) . " entries, $errors errors.\n";
} catch (Throwable $e) {
    echo 'SCHEMA FAILED: ' . $e->getMessage() . "\n";
}
if (function_exists('opcache_reset')) { @opcache_reset(); }
echo "Update script finished.\n";
PHP;
$zip->addFromString('update_script.php', $updateScript);

// Ensure zip is not empty by adding a metadata file
$zip->addFromString('patch_info.txt', "Patch generated on " . date('Y-m-d H:i:s') . "\nTimeframe: " . $timeframe);

$zip->close();

if (file_exists($tmpFile)) {
    // Clear any previous output (whitespace, notices) that would break the zip
    if (ob_get_level())
        ob_end_clean();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tmpFile);
    unlink($tmpFile);
} else {
    http_response_code(500);
    echo "Error: Failed to create temporary zip file.";
}
exit;
?>
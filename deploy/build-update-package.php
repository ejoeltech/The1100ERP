<?php
// Build a forward-slash update ZIP + SHA-256 for the GitHub updater.
// Usage: php deploy/build-update-package.php --base=v1.2.3 --version=1.2.11
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run from the CLI.');
}

$root = dirname(__DIR__);
$opts = getopt('', ['base::', 'version::', 'out::']);
$base = $opts['base'] ?? 'v1.2.3';
$out = $opts['out'] ?? (rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . '1100erp-update.zip');

require_once $root . '/includes/version.php';
$version = $opts['version'] ?? (defined('APP_VERSION') ? APP_VERSION : '');
if ($version === '') {
    fwrite(STDERR, "No version supplied and APP_VERSION is undefined.\n");
    exit(1);
}
$zipName = '1100erp-v' . $version . '-update.zip';
if (!array_key_exists('out', $opts)) {
    $home = getenv('USERPROFILE') ?: getenv('HOME') ?: $root;
    $downloads = is_dir($home . DIRECTORY_SEPARATOR . 'Downloads')
        ? $home . DIRECTORY_SEPARATOR . 'Downloads'
        : $home;
    $out = $downloads . DIRECTORY_SEPARATOR . $zipName;
}

// Only these files are allowed into an update archive.
$exclude = [
    '#^docs/#', '#^tests/#', '#^scripts/#', '#^deploy/#', '#^cron/#', '#^maintenance/setup/#',
    '#^vendor/#', '#^node_modules/#', '#^uploads/#', '#^storage/#', '#^tmp/#', '#^logs/#', '#^exports/#',
    '#^database/backups/#', '#\.md$#', '#\.zip$#', '#\.7z$#', '#\.sha256$#',
    '#^\.env#', '#^config\.php$#', '#^config\.sample\.php$#', '#^\.git#',
];

$cmd = 'git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($base . '..HEAD');
$output = shell_exec($cmd);
if ($output === null) {
    fwrite(STDERR, "Could not run git diff. Is git installed and the base ref valid?\n");
    exit(1);
}

$files = [];
foreach (preg_split('/\R/', trim($output)) as $rel) {
    $rel = trim(str_replace('\\', '/', $rel));
    if ($rel === '') continue;
    $skip = false;
    foreach ($exclude as $pattern) {
        if (preg_match($pattern, $rel)) { $skip = true; break; }
    }
    if ($skip) continue;
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($abs)) continue;
    $target = basename($rel) === '.htaccess' ? $rel . '.new' : $rel;
    $files[$target] = $abs;
}

$updateScript = <<<'PHP'
<?php
set_time_limit(300);
$root = __DIR__;

// Remove stray files/directories whose names contain a literal backslash.
// These are left behind when a ZIP built on Windows is extracted on Linux.
$removeTree = function ($path) use (&$removeTree) {
    if (is_dir($path) && !is_link($path)) {
        $items = @scandir($path);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $removeTree($path . DIRECTORY_SEPARATOR . $item);
            }
        }
        @rmdir($path);
    } else {
        @unlink($path);
    }
};
$stray = 0;
$rootItems = @scandir($root);
if (is_array($rootItems)) {
    foreach ($rootItems as $item) {
        if ($item === '.' || $item === '..') continue;
        if (strpos($item, '\\') !== false) {
            $removeTree($root . DIRECTORY_SEPARATOR . $item);
            $stray++;
        }
    }
}
if ($stray > 0) {
    echo 'Removed ' . $stray . " stray backslash entries.\n";
}

foreach (['.htaccess', 'exports/.htaccess', 'uploads/.htaccess', 'modules/hr/assets/uploads/.htaccess'] as $ht) {
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

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}
foreach ($files as $target => $abs) {
    $zip->addFile($abs, str_replace('\\', '/', $target));
}
$zip->addFromString('update_script.php', $updateScript);
$zip->addFromString('patch_info.txt', "1100erp v{$version} update package\nGenerated: " . date('Y-m-d H:i:s') . "\nBase: {$base}\n");
$zip->close();

$hash = hash_file('sha256', $out);
file_put_contents($out . '.sha256', $hash . '  ' . basename($out) . "\n");

echo "Built $out\n";
echo 'Source files: ' . count($files) . "\n";
echo "SHA256: $hash\n";

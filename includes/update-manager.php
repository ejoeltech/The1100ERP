<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Update archive backup/apply/rollback service.
 *
 * Every patch creates a file backup under storage/updates/<id>/ before any
 * extraction. The last manifest supports a one-click rollback to the previous
 * files. Database schema changes are additive and are not rolled back; the
 * schema patcher remains safe to re-run after a file rollback.
 */

function updateFail($message, $code = 400)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function updateRequireOperator()
{
    if (!function_exists('canManageUpdates') || !canManageUpdates()) {
        updateFail('Access denied: only a super admin or developer admin can manage updates.', 403);
    }
}

/** Require a typed confirmation + current admin password, with throttling. */
function updateVerifyReauth($pdo, $phrase = 'APPLY')
{
    if (($_POST['confirm'] ?? '') !== $phrase) {
        updateFail('Type ' . $phrase . ' to confirm this action.', 400);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (function_exists('throttleCheck') && !throttleCheck('reauth:' . ($_SESSION['user_id'] ?? 0) . ':' . $ip, 5, 900)) {
        updateFail('Too many re-authentication attempts. Try again later.', 429);
    }
    $password = $_POST['admin_password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $user = $stmt->fetch();
    if (!$user || !verifyPassword($password, $user['password'])) {
        updateFail('Incorrect admin password.', 403);
    }
}

function updateBackupsRoot($root)
{
    return rtrim((string)$root, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates';
}

function updateValidBackupId($id)
{
    return (bool)preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/', (string)$id);
}

function updateNormalizeArchiveEntry($entry)
{
    $entry = str_replace('\\', '/', (string)$entry);
    if ($entry === '' || $entry[0] === '/' || strpos($entry, "\0") !== false) return '';
    $parts = explode('/', $entry);
    if (in_array('..', $parts, true) || in_array('.', $parts, true)) return '';
    return $entry;
}

/**
 * Map an archive entry to the file it will become. `.htaccess.new` is renamed
 * to `.htaccess` by update_script.php, so the backup must track that target.
 */
function updateTargetForEntry($entry)
{
    $entry = updateNormalizeArchiveEntry($entry);
    if ($entry === '' || substr($entry, -1) === '/') return '';
    if ($entry === 'update_script.php' || $entry === 'patch_info.txt') return '';
    $base = basename($entry);
    if ($base === '.htaccess.new') return substr($entry, 0, -4);
    if ($base === '.htaccess') return '';
    return $entry;
}

function updateWriteJson($file, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($file, $json, LOCK_EX) === false) {
        throw new Exception('Cannot write update metadata.');
    }
}

function updateReadJson($file)
{
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function updateCreateBackup($zip, $root, $meta = [])
{
    $root = rtrim((string)$root, '/\\');
    $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $dir = updateBackupsRoot($root) . DIRECTORY_SEPARATOR . $id;
    $filesDir = $dir . DIRECTORY_SEPARATOR . 'files';
    if (!is_dir($filesDir) && !mkdir($filesDir, 0755, true)) {
        throw new Exception('Cannot create the update backup directory.');
    }

    $manifest = [
        'id' => $id,
        'created_at' => gmdate('c'),
        'app_version' => defined('APP_VERSION') ? APP_VERSION : '',
        'source' => (string)($meta['source'] ?? 'upload'),
        'release' => (string)($meta['release'] ?? ''),
        'user_id' => (int)($meta['user_id'] ?? 0),
        'files' => [],
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = updateNormalizeArchiveEntry($zip->getNameIndex($i));
        $target = updateTargetForEntry($entry);
        if ($target === '') continue;

        $targetAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        $existed = is_file($targetAbs);
        $hash = $existed ? hash_file('sha256', $targetAbs) : null;

        if ($existed) {
            $backupAbs = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
            $backupParent = dirname($backupAbs);
            if (!is_dir($backupParent) && !mkdir($backupParent, 0755, true)) {
                throw new Exception('Cannot create backup folders.');
            }
            if (!@copy($targetAbs, $backupAbs)) {
                throw new Exception('Cannot back up ' . $target);
            }
        }

        $manifest['files'][] = [
            'target' => $target,
            'entry' => $entry,
            'existed' => $existed,
            'sha256' => $hash,
        ];
    }

    $manifest['file_count'] = count($manifest['files']);
    updateWriteJson($dir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
    updateWriteJson(updateBackupsRoot($root) . DIRECTORY_SEPARATOR . 'last.json', [
        'id' => $id,
        'created_at' => $manifest['created_at'],
        'app_version' => $manifest['app_version'],
        'source' => $manifest['source'],
        'release' => $manifest['release'],
        'user_id' => $manifest['user_id'],
        'file_count' => $manifest['file_count'],
    ]);

    return [
        'id' => $id,
        'dir' => $dir,
        'manifest' => $manifest,
    ];
}

function updateLastBackup($root)
{
    $root = rtrim((string)$root, '/\\');
    $last = updateReadJson(updateBackupsRoot($root) . DIRECTORY_SEPARATOR . 'last.json');
    if (!$last || empty($last['id']) || !updateValidBackupId($last['id'])) return null;
    $manifestFile = updateBackupsRoot($root) . DIRECTORY_SEPARATOR . $last['id'] . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!is_file($manifestFile)) return null;
    return $last;
}

function updateRollbackBackup($root, $id = null)
{
    $root = rtrim((string)$root, '/\\');
    if ($id === null) {
        $last = updateLastBackup($root);
        if (!$last) throw new Exception('No update backup is available to roll back.');
        $id = $last['id'];
    }
    if (!updateValidBackupId($id)) throw new Exception('Invalid update backup identifier.');

    $dir = updateBackupsRoot($root) . DIRECTORY_SEPARATOR . $id;
    $manifest = updateReadJson($dir . DIRECTORY_SEPARATOR . 'manifest.json');
    if (!$manifest || !is_array($manifest['files'] ?? null)) {
        throw new Exception('Update backup manifest is missing or unreadable.');
    }

    $restored = 0;
    $removed = 0;
    foreach ($manifest['files'] as $file) {
        $target = updateNormalizeArchiveEntry($file['target'] ?? '');
        if ($target === '' || substr($target, -1) === '/') continue;
        $targetAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);

        if (!empty($file['existed'])) {
            $backupAbs = $dir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
            if (!is_file($backupAbs)) throw new Exception('Backup file is missing: ' . $target);
            $parent = dirname($targetAbs);
            if (!is_dir($parent) && !mkdir($parent, 0755, true)) {
                throw new Exception('Cannot recreate the folder for ' . $target);
            }
            if (!@copy($backupAbs, $targetAbs)) throw new Exception('Cannot restore ' . $target);
            $restored++;
        } elseif (is_file($targetAbs)) {
            @unlink($targetAbs);
            $removed++;
        }

        // Remove a staged `.htaccess.new` if the update script did not run.
        $entry = updateNormalizeArchiveEntry($file['entry'] ?? '');
        if ($entry !== '' && substr($entry, -4) === '.new') {
            @unlink($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry));
        }
    }

    @unlink($root . DIRECTORY_SEPARATOR . 'update_script.php');
    $lastFile = updateBackupsRoot($root) . DIRECTORY_SEPARATOR . 'last.json';
    if (is_file($lastFile)) {
        $last = updateReadJson($lastFile) ?: [];
        $last['rolled_back_at'] = gmdate('c');
        updateWriteJson($lastFile, $last);
    }
    if (function_exists('opcache_reset')) @opcache_reset();

    return ['id' => $id, 'restored' => $restored, 'removed' => $removed];
}

/**
 * Inspect, back up, extract and run an update archive. On an exception the
 * files are rolled back automatically and the exception is re-thrown.
 *
 * Returns ['backup' => [...], 'output' => string, 'schema_errors' => bool].
 */
function updateApplyArchive($zipPath, $root, $meta = [])
{
    global $pdo;
    $root = rtrim((string)$root, '/\\');
    $zip = inspectZipArchive($zipPath, ['.env', 'config.php', 'config.sample.php', '.htaccess']);
    $backup = null;
    try {
        $backup = updateCreateBackup($zip, $root, $meta);
        if (!$zip->extractTo($root)) {
            throw new Exception('The update archive could not be extracted.');
        }

        $output = '';
        $updateScript = $root . DIRECTORY_SEPARATOR . 'update_script.php';
        if (is_file($updateScript)) {
            ob_start();
            include $updateScript;
            $output = (string)ob_get_clean();
            @unlink($updateScript);
        }

        $schemaErrors = (stripos($output, 'SCHEMA ERROR:') !== false)
            || (stripos($output, 'SCHEMA FAILED:') !== false);

        return [
            'backup' => $backup,
            'output' => $output,
            'schema_errors' => $schemaErrors,
        ];
    } catch (Throwable $e) {
        if (ob_get_level() > 1) { @ob_end_clean(); }
        if ($backup) {
            try {
                updateRollbackBackup($root, $backup['id']);
                throw new Exception('Update failed and the previous files were restored: ' . $e->getMessage(), 0, $e);
            } catch (Exception $rollbackError) {
                if (strpos($rollbackError->getMessage(), 'previous files were restored') !== false) {
                    throw $rollbackError;
                }
                throw new Exception('Update failed and rollback also failed: ' . $rollbackError->getMessage(), 0, $e);
            }
        }
        throw $e;
    } finally {
        @$zip->close();
    }
}

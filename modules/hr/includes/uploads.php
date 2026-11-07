<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Shared helpers for HR-uploaded images (employee photos/signatures, onboarding
 * scans and generated ID-card assets). These files live under the document root
 * but are served through modules/hr/api/image.php after an HR permission check.
 */

/**
 * Map a stored upload path to a canonical root-relative path + folder name.
 * Returns null for anything outside the known upload folders or for paths
 * that contain traversal, backslashes, NULs or nested names.
 */
function hrUploadPathInfo($path)
{
    $path = trim((string)$path);
    if ($path === '' || strlen($path) > 255 || strpos($path, "\0") !== false || strpos($path, '\\') !== false) {
        return null;
    }
    $prefixes = [
        'modules/hr/assets/uploads/idcards/'    => ['idcards',    'modules/hr/assets/uploads/idcards/'],
        'modules/hr/assets/uploads/employees/'  => ['employees',  'modules/hr/assets/uploads/employees/'],
        'modules/hr/assets/uploads/onboarding/' => ['onboarding', 'modules/hr/assets/uploads/onboarding/'],
        '../assets/uploads/idcards/'            => ['idcards',    'modules/hr/assets/uploads/idcards/'],
        '../assets/uploads/employees/'          => ['employees',  'modules/hr/assets/uploads/employees/'],
        '../assets/uploads/onboarding/'         => ['onboarding', 'modules/hr/assets/uploads/onboarding/'],
    ];
    foreach ($prefixes as $prefix => [$folder, $canonical]) {
        if (strpos($path, $prefix) !== 0) continue;
        $name = substr($path, strlen($prefix));
        if ($name !== '' && basename($name) === $name
            && $name[0] !== '.'
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            return ['folder' => $folder, 'path' => $canonical . $name];
        }
    }
    return null;
}

/**
 * Validate a path before writing it to the database. Logo paths may also point
 * at the public company logo folder. Returns '' for disallowed values.
 */
function cleanStoredImagePath($path, $allowLogo = true)
{
    $info = hrUploadPathInfo($path);
    if ($info) return $info['path'];

    if ($allowLogo) {
        $path = trim((string)$path);
        foreach (['../../../uploads/logo/', 'uploads/logo/'] as $prefix) {
            if (strpos($path, $prefix) !== 0) continue;
            $name = substr($path, strlen($prefix));
            if ($name !== '' && basename($name) === $name
                && $name[0] !== '.'
                && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
                return $prefix . $name;
            }
        }
    }
    return '';
}

/** Resolve an upload path to its real file path, confined to its upload folder. */
function hrUploadAbsolutePath($path)
{
    $info = hrUploadPathInfo($path);
    if (!$info) return null;
    $base = realpath(__DIR__ . '/../assets/uploads/' . $info['folder']);
    $real = realpath(dirname(__DIR__, 3) . '/' . $info['path']);
    if ($base === false || $real === false) return null;
    $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($real, $prefix) === 0 ? $real : null;
}

function hrUploadFileExists($path)
{
    return hrUploadAbsolutePath($path) !== null;
}

/** Relative URL for HR pages that displays a protected upload via the proxy. */
function hrUploadImageUrl($path)
{
    $info = hrUploadPathInfo($path);
    return $info ? '../api/image.php?path=' . rawurlencode($info['path']) : (string)$path;
}

/** Only ID-card uploads may be deleted by card/template cleanup. */
function idCardDeletablePath($path)
{
    $info = hrUploadPathInfo($path);
    if (!$info || $info['folder'] !== 'idcards') return null;
    return hrUploadAbsolutePath($info['path']);
}

/** Check whether an upload path is still used by any card, template or default row. */
function imagePathReferenced($pdo, $path)
{
    if ($path === '') return true;
    foreach ([
        ['hr_id_cards', 'photo_path'],
        ['hr_id_cards', 'logo_path'],
        ['hr_id_cards', 'signature_path'],
        ['hr_id_templates', 'logo_path'],
        ['hr_id_templates', 'signature_path'],
        ['hr_id_template_defaults', 'logo_path'],
        ['hr_id_template_defaults', 'signature_path'],
    ] as [$table, $column]) {
        $st = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1");
        $st->execute([$path]);
        if ($st->fetchColumn()) return true;
    }
    return false;
}

function deleteCardImageIfUnused($pdo, $path)
{
    $file = idCardDeletablePath($path);
    if ($file === null) return;
    if (!imagePathReferenced($pdo, $path)) @unlink($file);
}

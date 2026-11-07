<?php
// Authenticated reader for HR uploads (employee photos/signatures, onboarding
// scans and ID-card assets). The files live under the document root so this is
// the only HTTP path allowed to serve them.
require_once '../../../includes/security.php';
configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$onboardingSession = !empty($_SESSION['onboarding_code_id']);
if ($onboardingSession) {
    require_once '../../../config.php';
} else {
    // Normal HR users go through the standard timeout/revocation/CSRF bootstrap.
    require_once '../../../includes/session-check.php';
}
require_once __DIR__ . '/../includes/uploads.php';

$raw = $_GET['path'] ?? '';
$info = hrUploadPathInfo($raw);
if (!$info) {
    http_response_code(404);
    exit;
}

$allowed = false;
if ($info['folder'] === 'onboarding' && $onboardingSession) {
    // An applicant may only read the files attached to their own latest draft.
    $st = $pdo->prepare('SELECT passport_path, signature_path FROM hr_onboarding_entries WHERE code_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([(int)$_SESSION['onboarding_code_id']]);
    $entry = $st->fetch(PDO::FETCH_ASSOC);
    if ($entry) {
        $owned = array_filter([
            cleanStoredImagePath($entry['passport_path'] ?? '', false),
            cleanStoredImagePath($entry['signature_path'] ?? '', false),
        ]);
        $allowed = in_array($info['path'], $owned, true);
    }
} elseif (function_exists('hasPermission')) {
    $allowed = $info['folder'] === 'idcards'
        ? hasPermission('hr_manage')
        : (hasPermission('hr_view') || hasPermission('hr_manage'));
}
if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$file = hrUploadAbsolutePath($raw);
if ($file === null) {
    http_response_code(404);
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string)finfo_file($finfo, $file);
        finfo_close($finfo);
    }
}
if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string)mime_content_type($file);
}
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
readfile($file);
exit;

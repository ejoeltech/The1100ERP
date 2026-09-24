#!/usr/bin/env php
<?php
/**
 * guard-audit.php — static route-exposure report (WP0-F, extended in WP3).
 *
 * Lists every .php file under the web root with:
 *   guard  - which access gate it includes (session, session-inline,
 *            public, install-token, api-token, cli-only, none)
 *   perm   - the requirePermission() key / isAdmin() / isSuperAdmin() if present
 *   verdict- OK (guarded or allow-listed) / REVIEW (unguarded, not public)
 *            / INFO (library, partial, template, config, CLI tool)
 *
 * Usage: php scripts/guard-audit.php [--strict]
 *   --strict exits 1 when any REVIEW row exists (for CI).
 * Read-only: never writes, never touches the database.
 */

$root = dirname(__DIR__);
$strict = in_array('--strict', $argv ?? [], true);

// Endpoints deliberately reachable without a login session.
$publicAllowList = [
    'index.php',
    'login.php',
    'logout.php',
    'signup.php',
    'lead-form.php',
    'api/leads/save-lead.php',
    'api/leads/whatsapp-webhook.php',
    'api/ai/calculate-roi.php',
    'api/ai/design-implementation.php',
    'api/ai/design-planner.php',
    'api/ai/export-recommendation-pdf.php',
    'pages/roi-calculator.php',
    'pages/system-designer.php',
    'modules/hr/pages/signup-form.php', // onboarding-code session, not login
];

// Installer files with a different-but-sufficient gate (documented):
// - cleanup.php: post-install admin tool (session + isAdmin + password + CSRF).
// - install.php: install-token AJAX gate + admin-only final_check exception.
$installerAdminOk = [
    'maintenance/setup/cleanup.php',
    'maintenance/setup/install.php',
];
$installerPrefix = 'maintenance/setup/';

// Never directly routable: libraries, partials, templates, configs, assets.
$infoPatterns = [
    '#^includes/#',
    '#modules/.*/classes/#',
    '#-template\.php$#',
    '#(header|footer|sidebar|chat-widget|pick-item-modal|email-modal)\.php$#',
    '#^config(\.sample)?\.php$#',
    '#^maintenance/setup/assets/#',
    '#^maintenance/setup/install-guard\.php$#',
    '#idmaker-(designer|preview|records)\.php$#',
    '#^docs/#',
    '#^scripts/#',
];

$skipDirs = ['vendor', '.git', 'tmp', 'uploads', 'logs', 'exports'];

$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $skip = false;
    foreach ($skipDirs as $d) {
        if ($rel === $d || str_starts_with($rel, $d . '/')) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $files[] = $rel;
    }
}
sort($files);

$review = 0;
printf("%-52s %-13s %-18s %s\n", 'FILE', 'GUARD', 'PERM', 'VERDICT');
foreach ($files as $rel) {
    $c = file_get_contents($root . '/' . $rel);
    $guard = 'none';
    foreach ([
        'session-check.php' => 'session',
        'public-init.php' => 'public',
        'install-guard.php' => 'install-token',
        'requireApiAuth' => 'api-token',
    ] as $needle => $label) {
        if (str_contains($c, $needle)) {
            $guard = $label;
            break;
        }
    }
    if ($guard === 'none' && preg_match('/php_sapi_name\(\)\s*!==?\s*[\'"]cli[\'"]|PHP_SAPI\s*!==?\s*[\'"]cli[\'"]/', $c)) {
        $guard = 'cli-only';
    }
    if ($guard === 'none' && preg_match('/session_start\(\)/', $c)
        && preg_match('/isLoggedIn\(\)|requireLogin\(|isset\(\$_SESSION\s*\[\s*[\'"]user_id[\'"]\s*\]\)/', $c)
    ) {
        $guard = 'session-inline';
    }
    $perm = '-';
    if (preg_match("/requirePermission\(\s*['\"]([^'\"]+)/", $c, $m)) {
        $perm = $m[1];
    } elseif (str_contains($c, 'isSuperAdmin(')) {
        $perm = 'super_admin*';
    } elseif (str_contains($c, 'isAdmin(')) {
        $perm = 'admin*';
    }

    $verdict = 'REVIEW';
    $isInfo = false;
    foreach ($infoPatterns as $pat) {
        if (preg_match($pat, $rel)) {
            $isInfo = true;
            break;
        }
    }
    if ($isInfo) {
        $verdict = 'INFO';
    } elseif (str_starts_with($rel, $installerPrefix)) {
        $onAdminList = in_array($rel, $installerAdminOk, true);
        $adminGated = ($guard === 'session' || $guard === 'session-inline')
            && ($perm === 'admin*' || $perm === 'super_admin*' || str_contains($c, 'isAdmin'));
        $verdict = ($guard === 'install-token' || ($onAdminList && $adminGated)) ? 'OK' : 'REVIEW';
    } elseif (in_array($rel, $publicAllowList, true)) {
        $verdict = 'OK';
    } elseif (in_array($guard, ['session', 'session-inline', 'cli-only', 'api-token'], true)) {
        // session-inline = login enforced without the shared bootstrap
        // (WP3 standardises these onto session-check + requirePermission).
        $verdict = 'OK';
    }
    if ($verdict === 'REVIEW') {
        $review++;
    }
    printf("%-52s %-13s %-18s %s\n", $rel, $guard, $perm, $verdict);
}

echo "\nFiles: " . count($files) . " | REVIEW: $review\n";
exit($strict && $review > 0 ? 1 : 0);

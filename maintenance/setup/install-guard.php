<?php
/**
 * Install-window guard for the setup wizard.
 *
 * Rules:
 * - The wizard refuses everything once ANY installed signal exists:
 *   config.php, setup/lock, or storage/installed (WP0-C third marker).
 * - Otherwise every wizard request (UI, AJAX, restore) must present the
 *   one-time install token that only someone with server access can read:
 *   maintenance/setup/install.token (created at deploy time, see
 *   deploy/INSTALL_RUNBOOK.md). Compared with hash_equals. A verified
 *   session is remembered via $_SESSION['install_token_ok'].
 * - Token guesses are rate-limited per IP and met with delays.
 */

function install_root_dir()
{
    return dirname(__DIR__, 2);
}

function install_is_installed()
{
    $root = install_root_dir();
    if (file_exists($root . '/config.php')) {
        return true;
    }
    if (file_exists(__DIR__ . '/lock')) {
        return true;
    }
    if (file_exists($root . '/storage/installed')) {
        return true;
    }
    return false;
}

function install_token_file()
{
    return __DIR__ . '/install.token';
}

function install_expected_token()
{
    $file = install_token_file();
    if (!is_readable($file)) {
        return null;
    }
    $token = trim((string)@file_get_contents($file));
    return $token !== '' ? $token : null;
}

function install_provided_token()
{
    if (isset($_POST['install_token']) && $_POST['install_token'] !== '') {
        return (string)$_POST['install_token'];
    }
    if (isset($_GET['token']) && $_GET['token'] !== '') {
        return (string)$_GET['token'];
    }
    if (isset($_SERVER['HTTP_X_INSTALL_TOKEN']) && $_SERVER['HTTP_X_INSTALL_TOKEN'] !== '') {
        return (string)$_SERVER['HTTP_X_INSTALL_TOKEN'];
    }
    return null;
}

function install_ratelimit_file()
{
    return __DIR__ . '/.install-attempts';
}

function install_too_many_attempts($ip)
{
    $file = install_ratelimit_file();
    $data = [];
    if (is_readable($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    $now = time();
    $window = 600;
    $max = 10;
    foreach ($data as $k => $times) {
        $data[$k] = array_values(array_filter((array)$times, fn($t) => ($now - (int)$t) < $window));
        if (empty($data[$k])) {
            unset($data[$k]);
        }
    }
    $count = count($data[$ip] ?? []);
    return [$count >= $max, $data];
}

function install_record_attempt($ip, $data)
{
    $data[$ip][] = time();
    @file_put_contents(install_ratelimit_file(), json_encode($data), LOCK_EX);
}

/**
 * Verify the install token. Returns true and remembers it in the session.
 * Emits a generic failure (with delay) otherwise.
 */
function install_verify_token()
{
    if (!empty($_SESSION['install_token_ok'])) {
        return true;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    [$blocked, $data] = install_too_many_attempts($ip);
    if ($blocked) {
        http_response_code(429);
        return false;
    }
    $expected = install_expected_token();
    $provided = install_provided_token();
    if ($expected === null || $provided === null || !hash_equals($expected, $provided)) {
        install_record_attempt($ip, $data);
        sleep(2);
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['install_token_ok'] = true;
    return true;
}

function install_deny_json($message = 'Install token required or invalid.')
{
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function install_deny_ui()
{
    http_response_code(403);
    die('
        <h1>Installation Locked</h1>
        <p>This installer requires a one-time install token that only the server administrator can read.</p>
        <p>See <code>deploy/INSTALL_RUNBOOK.md</code> for the install procedure.</p>
    ');
}

/**
 * Gate for wizard AJAX endpoints (install.php, restore_during_setup.php).
 * Dies JSON when installed or when the token is missing/invalid.
 */
function install_require_token_ajax()
{
    if (install_is_installed()) {
        install_deny_json('Already installed. Delete maintenance/setup/ to reinstall.');
    }
    if (!install_verify_token()) {
        [$blocked] = install_too_many_attempts($_SERVER['REMOTE_ADDR'] ?? 'cli');
        install_deny_json($blocked ? 'Too many attempts. Try again later.' : 'Install token required or invalid.');
    }
}

/**
 * Gate for the wizard UI page. Dies HTML when installed or token is absent.
 */
function install_require_token_ui()
{
    if (install_is_installed()) {
        die('
            <h1>Already Installed</h1>
            <p>1100-ERP is already installed on this server.</p>
        ');
    }
    if (!install_verify_token()) {
        install_deny_ui();
    }
}

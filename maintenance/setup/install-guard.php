<?php
/**
 * Install-window guard for the setup wizard (WP0-C, usability revision).
 *
 * Model: proof-of-WRITE instead of a shared secret. To install, someone able
 * to write files on the server creates an empty file:
 *
 *     maintenance/setup/ALLOW_INSTALL
 *
 * (cPanel File Manager → + File, or FTP upload, or `touch` / New-Item.)
 * The wizard — UI, AJAX and restore — refuses every request while that file
 * is absent, and finalizeInstallation() deletes it. A remote stranger who
 * can only browse the site cannot create it, so they cannot start an
 * install; anyone who CAN write server files already owns the host, and no
 * token scheme adds anything on top of that.
 *
 * Independent second layer (unchanged): the wizard also refuses once ANY
 * installed signal exists — config.php, setup/lock, or storage/installed.
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

function install_claim_file()
{
    return __DIR__ . '/ALLOW_INSTALL';
}

function install_claim_present()
{
    return file_exists(install_claim_file());
}

function install_spend_claim()
{
    @unlink(install_claim_file());
    // Belt and braces: remove legacy token artefacts if ever created.
    @unlink(__DIR__ . '/install.token');
    @unlink(__DIR__ . '/.install-attempts');
}

function install_locked_ui()
{
    http_response_code(403);
    die('
        <h1>Installation Locked</h1>
        <p>To run this installer, create an <strong>empty file</strong> named
        <code>ALLOW_INSTALL</code> inside the <code>maintenance/setup/</code>
        folder, then reload this page.</p>
        <p>cPanel: File Manager → open <code>maintenance/setup/</code> →
        <strong>+ File</strong> → name it <code>ALLOW_INSTALL</code>.
        FTP: upload an empty file with that name. Terminal:
        <code>touch maintenance/setup/ALLOW_INSTALL</code>.</p>
        <p>The installer deletes the file when setup finishes. Full procedure:
        <code>deploy/INSTALL_RUNBOOK.md</code>.</p>
    ');
}

function install_deny_json($message)
{
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Gate for wizard AJAX endpoints (install.php).
 */
function install_require_claim_ajax()
{
    if (install_is_installed()) {
        install_deny_json('Already installed. Delete maintenance/setup/ to reinstall.');
    }
    if (!install_claim_present()) {
        install_deny_json('Installation locked: create maintenance/setup/ALLOW_INSTALL on the server first.');
    }
}

/**
 * Gate for the wizard UI page.
 */
function install_require_claim_ui()
{
    if (install_is_installed()) {
        die('
            <h1>Already Installed</h1>
            <p>1100-ERP is already installed on this server.</p>
        ');
    }
    if (!install_claim_present()) {
        install_locked_ui();
    }
}

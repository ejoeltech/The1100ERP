<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Small helpers for keeping user input after a failed form submission.
 *
 * API handlers call flashOldInput('<redirect target>') from their catch block,
 * then the form page reads values with old()/oldSelected() and calls
 * clearOldInput() once the form has been rendered. Values are scoped to the
 * target script and expire quickly, so one form cannot leak into another.
 * Secrets and CSRF tokens are never stored.
 */

function flashOldInput($redirectTarget = null, $ttlSeconds = 600)
{
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    $skip = [
        'csrf_token', 'password', 'confirm_password', 'current_password',
        'new_password', 'password_confirm', 'token', 'secret', 'reset_password',
    ];
    $data = [];
    foreach ((array)($_POST ?? []) as $key => $value) {
        if (in_array(strtolower((string)$key), $skip, true)) {
            continue;
        }
        if (is_scalar($value)) {
            $data[$key] = (string)$value;
        }
    }
    $_SESSION['_old_input'] = [
        'data' => $data,
        'target' => $redirectTarget ? basename(parse_url($redirectTarget, PHP_URL_PATH) ?: $redirectTarget) : null,
        'at' => time(),
        'ttl' => max(60, (int)$ttlSeconds),
    ];
    return true;
}

function oldInputFlash()
{
    if (empty($_SESSION['_old_input'])) {
        return null;
    }
    $flash = $_SESSION['_old_input'];
    if (!is_array($flash) || empty($flash['data']) || (time() - (int)($flash['at'] ?? 0)) > (int)($flash['ttl'] ?? 600)) {
        unset($_SESSION['_old_input']);
        return null;
    }
    if (!empty($flash['target']) && $flash['target'] !== basename($_SERVER['SCRIPT_NAME'] ?? '')) {
        return null;
    }
    return $flash['data'];
}

/**
 * Return a previously posted value for this form, else the default.
 */
function old($key, $default = '')
{
    $data = oldInputFlash();
    return $data !== null && array_key_exists($key, $data) ? $data[$key] : $default;
}

/**
 * True when old input is queued for this form and should win over DB values.
 */
function oldInputActive()
{
    return oldInputFlash() !== null;
}

/**
 * Selected attribute helper for <select> and checked for checkboxes.
 */
function oldSelected($key, $value, $default = '')
{
    $current = old($key, $default);
    return (string)$current === (string)$value ? 'selected' : '';
}

function oldChecked($key, $value = '1', $default = false)
{
    // When a failed submission is being restored, an unchecked box is absent
    // from $_POST and must stay unchecked (default only applies on a fresh load).
    $current = oldInputActive() ? old($key, '') : ($default ? $value : '');
    return (string)$current === (string)$value ? 'checked' : '';
}

function clearOldInput()
{
    unset($_SESSION['_old_input']);
}

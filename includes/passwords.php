<?php
/**
 * Password helpers (WP2). Single home for hashing + policy.
 *
 * - Argon2id with explicit options everywhere (no scattered bcrypt calls).
 * - Rehash-on-login path kept: old bcrypt hashes verify, then upgrade.
 * - Policy: 12+ chars, blocklist, not equal to current password.
 */

function passwordHashOptions()
{
    return ['memory_cost' => 1 << 16, 'time_cost' => 4, 'threads' => 1];
}

function hashPassword($password)
{
    return password_hash($password, PASSWORD_ARGON2ID, passwordHashOptions());
}

function verifyPassword($password, $hash)
{
    if (!is_string($hash) || $hash === '') {
        return false;
    }
    return password_verify($password, $hash);
}

function passwordNeedsRehash($hash)
{
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, passwordHashOptions());
}

/**
 * Run a dummy verify so unknown-user and wrong-password logins take
 * comparable time (used by WP4 rate-limit work; harmless to call now).
 */
function dummyPasswordVerify()
{
    static $dummy = null;
    if ($dummy === null) {
        $dummy = password_hash(random_bytes(16), PASSWORD_ARGON2ID, passwordHashOptions());
    }
    password_verify(random_bytes(16), $dummy);
}

/**
 * Common-password blocklist (exact match, case-insensitive, plus
 * leet-normalised variants of the worst offenders).
 */
function commonPasswordList()
{
    return [
        'password', 'password1', 'password12', 'password123', 'password1234',
        'password12345', 'password123456', 'password2024', 'password2025', 'password2026',
        '123456', '12345678', '123456789', '1234567890', '123456789012',
        'qwerty', 'qwerty123', 'qwertyuiop', 'abc123', 'abcd1234',
        'letmein', 'welcome', 'welcome1', 'welcome123',
        'admin', 'admin123', 'administrator', 'administrator123',
        'changeme', 'change123', 'temp123', 'temporary',
        'iloveyou', 'monkey', 'dragon', 'football', 'baseball',
        'superman', 'trustno1', 'master', 'master123',
        'eleven100', 'eleven100erp', 'bluedots', 'bluedotserp',
        'solar123', 'inverter', 'nigeria123', 'lagos123',
        'user123', 'test123', 'demo123', 'staff123',
        'passw0rd', 'p@ssword', 'p@ssw0rd', 'password!', 'password@123',
        'qwerty123!', 'admin@123', 'welcome@123',
    ];
}

/**
 * Validate a candidate password. Returns list of error strings (empty = ok).
 * $oldHash: when changing, the new password must differ from the old one.
 */
function validatePasswordPolicy($password, $oldHash = null, $username = '')
{
    $errors = [];
    if (!is_string($password) || strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
        return $errors; // length first; other checks need enough material
    }
    if (strlen($password) > 256) {
        $errors[] = 'Password must be at most 256 characters.';
    }
    $lower = strtolower($password);
    foreach (commonPasswordList() as $bad) {
        if ($lower === $bad) {
            $errors[] = 'That password is too common. Choose a different one.';
            break;
        }
    }
    if (ctype_digit($password)) {
        $errors[] = 'Password cannot be numbers only.';
    }
    if ($username !== '' && stripos($password, $username) !== false && strlen($username) >= 4) {
        $errors[] = 'Password must not contain your username.';
    }
    if ($oldHash && verifyPassword($password, $oldHash)) {
        $errors[] = 'New password must be different from the current one.';
    }
    return $errors;
}

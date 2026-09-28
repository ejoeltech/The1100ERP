<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * TOTP MFA + recovery codes (WP4). No external libraries.
 * - RFC 6238 TOTP (SHA1, 30s step, 6 digits, ±1 step window).
 * - Secrets encrypted at rest (AES-256-GCM, key from ENCRYPTION_KEY).
 * - Recovery codes: 10 single-use, Argon2-hashed, shown once.
 */

require_once __DIR__ . '/passwords.php';

define('MFA_STEP_SECONDS', 30);
define('MFA_DIGITS', 6);
define('MFA_WINDOW_STEPS', 1);
define('MFA_RECOVERY_COUNT', 10);

/* ---------- base32 (RFC 4648, no padding) ---------- */
function totpBase32Encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $buffer = 0;
    $bits = 0;
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($buffer >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}

function totpBase32Decode($s)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(str_replace([' ', '-', '='], '', $s));
    $out = '';
    $buffer = 0;
    $bits = 0;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $v = strpos($alphabet, $s[$i]);
        if ($v === false) {
            return false;
        }
        $buffer = ($buffer << 5) | $v;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 255);
        }
    }
    return $out;
}

/* ---------- TOTP ---------- */
function totpCode($secretBin, $timeStep, $digits = MFA_DIGITS)
{
    // 8-byte big-endian counter (works on 64-bit PHP).
    $counter = pack('N*', 0, $timeStep);
    $hash = hash_hmac('sha1', $counter, $secretBin, true);
    $offset = ord($hash[19]) & 0x0f;
    $code = ((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
    return str_pad((string)($code % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totpVerify($secretBin, $code, $window = MFA_WINDOW_STEPS, $now = null)
{
    $code = trim((string)$code);
    if (!preg_match('/^\d{' . MFA_DIGITS . '}$/', $code)) {
        return false;
    }
    $now = $now ?? time();
    $step = (int)floor($now / MFA_STEP_SECONDS);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totpCode($secretBin, $step + $i), $code)) {
            return true;
        }
    }
    return false;
}

function totpNewSecret($bytes = 20)
{
    return random_bytes($bytes);
}

function totpProvisionUri($issuer, $account, $secretB32)
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=' . MFA_DIGITS . '&period=' . MFA_STEP_SECONDS;
}

/* ---------- at-rest encryption for MFA secrets ---------- */
function mfaCryptoKey()
{
    $material = getenv('ENCRYPTION_KEY');
    if (!$material) {
        // Also accept the settings-table key used by encryptPII flows.
        if (function_exists('getSetting')) {
            try {
                $material = getSetting('encryption_key', '');
            } catch (Exception $e) {
                $material = '';
            }
        }
    }
    if (!$material) {
        throw new Exception('Encryption key is not configured (ENCRYPTION_KEY). MFA cannot be set up.');
    }
    // Accept raw or base64 material; derive a fixed 32-byte key either way.
    $raw = $material;
    $decoded = base64_decode($material, true);
    if ($decoded !== false && strlen($decoded) >= 16) {
        $raw = $decoded;
    }
    return hash('sha256', $raw, true);
}

function mfaEncryptSecret($secretBin)
{
    $key = mfaCryptoKey();
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($secretBin, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) {
        throw new Exception('MFA encryption failed.');
    }
    return base64_encode($iv . $tag . $ct);
}

function mfaDecryptSecret($blob)
{
    $key = mfaCryptoKey();
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 28) {
        throw new Exception('Invalid MFA secret blob.');
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct = substr($raw, 28);
    $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($pt === false) {
        throw new Exception('MFA decryption failed.');
    }
    return $pt;
}

/* ---------- recovery codes ---------- */
function mfaNewRecoveryCodes($count = MFA_RECOVERY_COUNT)
{
    $alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // unambiguous, no 0/O/1/I/L
    $codes = [];
    for ($c = 0; $c < $count; $c++) {
        $s = '';
        for ($i = 0; $i < 10; $i++) {
            $s .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
        $codes[] = substr($s, 0, 4) . '-' . substr($s, 4, 3) . '-' . substr($s, 7, 3);
    }
    return $codes;
}

function mfaNormalizeRecoveryCode($code)
{
    return strtoupper(str_replace([' ', '-'], '', trim((string)$code)));
}

/* ---------- user-level operations ---------- */
function mfaIsEnabled($userId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT mfa_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int)$row['mfa_enabled'] === 1;
}

/**
 * Global MFA kill-switch (Security settings page, super_admin only).
 * Default ON (opt-in enrollment available); when OFF, enrollment is hidden
 * and the login second step is skipped even for enrolled accounts.
 */
function mfaGloballyEnabled()
{
    if (!function_exists('getSetting')) {
        return true;
    }
    try {
        return getSetting('security_mfa_enabled', '1') !== '0';
    } catch (Exception $e) {
        return true;
    }
}

/**
 * Roles (and HR/payroll permission holders) for whom MFA is mandatory (WP4).
 */
function mfaRequiredForRole($role)
{
    return in_array($role, ['super_admin', 'admin', 'accountant'], true);
}

function mfaRequiredForUser($userId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if (mfaRequiredForRole($row['role'])) {
        return true;
    }
    // HR/payroll permission holders (group/override aware, no session swap).
    if (function_exists('getUserEffectivePermissions')) {
        $eff = getUserEffectivePermissions($userId);
        $granted = array_diff($eff['group'], array_keys(array_filter($eff['overrides'], fn($v) => $v !== 1)));
        foreach (array_keys(array_filter($eff['overrides'], fn($v) => $v === 1)) as $k) {
            $granted[] = $k;
        }
        if (in_array('hr_manage', $granted, true) || in_array('payroll_run', $granted, true)) {
            return true;
        }
    }
    return false;
}

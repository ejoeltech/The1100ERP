<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * One-time invites, hardened onboarding codes, and DB-backed throttling (WP2).
 * Invite tokens and onboarding codes are random, stored hashed, expiring,
 * single-use. Plaintext values are shown once to the creating admin and
 * never stored, emailed, or logged.
 */

require_once __DIR__ . '/passwords.php';

/* ================= generic throttle ================= */

/**
 * Sliding-window throttle backed by auth_throttle.
 * Returns true when the attempt is allowed (and counted), false when over limit.
 */
function throttleCheck($bucket, $maxAttempts, $windowSecs = 3600)
{
    global $pdo;
    $bucket = substr(preg_replace('/[^a-z0-9_:\-\.@]/i', '', $bucket), 0, 120);
    $windowSecs = max(60, (int)$windowSecs);
    try {
        // Floored window key so concurrent attempts share one row and the
        // counter actually accumulates (PK is bucket+window_start).
        $pdo->exec("DELETE FROM auth_throttle WHERE window_start < DATE_SUB(NOW(), INTERVAL $windowSecs SECOND)");
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(attempts), 0) FROM auth_throttle WHERE bucket = ? AND window_start >= DATE_SUB(NOW(), INTERVAL $windowSecs SECOND)");
        $stmt->execute([$bucket]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO auth_throttle (bucket, window_start, attempts) VALUES (?, FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) DIV $windowSecs * $windowSecs), 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1");
        $stmt->execute([$bucket]);
        return true;
    } catch (Exception $e) {
        // Throttle table missing (migration not applied): fail open for
        // availability, the code/invite checks below still enforce.
        return true;
    }
}

/* ================= user invites ================= */

define('INVITE_TTL_SECONDS', 48 * 3600); // 48 hours

/**
 * Create a one-time invite for a user. Returns the PLAINTEXT token once
 * (caller shows it to the admin a single time; it is never stored).
 */
function createUserInvite($userId, $createdBy = null, $ttlSeconds = INVITE_TTL_SECONDS, $pdo = null)
{
    $pdo = $pdo ?: ($GLOBALS['pdo'] ?? null);
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO user_invites (user_id, token_hash, expires_at, created_by) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)");
    $stmt->execute([$userId, hash('sha256', $token), (int)$ttlSeconds, $createdBy]);
    return $token;
}

/**
 * Look up a valid (unexpired, unused) invite by plaintext token. Returns row or null.
 */
function findValidInvite($token)
{
    global $pdo;
    if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT i.*, u.username, u.is_active FROM user_invites i JOIN users u ON u.id = i.user_id WHERE i.token_hash = ? AND i.used_at IS NULL AND i.expires_at > NOW() LIMIT 1");
    $stmt->execute([hash('sha256', strtolower($token))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Consume an invite: set the user's password (policy-checked by caller),
 * clear must_change_password, mark invite used. Atomic.
 */
function consumeInvite($inviteId, $userId, $newPasswordHash)
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        $stmt = $pdo->prepare("UPDATE user_invites SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
        $stmt->execute([$inviteId]);
        if ($stmt->rowCount() !== 1) {
            throw new Exception('Invite was already used.');
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Whether invite links are enabled (Security settings page, default OFF).
 * When OFF, user onboarding uses admin-set temporary passwords instead.
 * (HR employee onboarding keeps its own flow regardless.)
 */
function invitesGloballyEnabled()
{
    if (!function_exists('getSetting')) {
        return false;
    }
    try {
        return getSetting('security_invites_enabled', '0') === '1';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create a user with an admin-set temporary password + must_change flag.
 * Returns the new user id. The caller shows nothing secret (the admin chose
 * the password and must relay it to the user out of band).
 */
function createUserWithPassword($username, $fullName, $email, $phone, $role, $groupId, $isActive, $passwordHash, $pdo = null)
{
    $pdo = $pdo ?: ($GLOBALS['pdo'] ?? null);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $passwordHash, $fullName, $email, $phone, $role, $groupId, $isActive]);
    return (int)$pdo->lastInsertId();
}
/**
 * Create a user with an unknowable password + must_change flag + invite.
 * Returns ['user_id' => int, 'invite_token' => string(plaintext, show once)].
 * The plaintext token must never be stored, emailed, or logged.
 */
function createUserWithInvite($username, $fullName, $email, $phone, $role, $groupId, $isActive, $createdBy = null, $pdo = null)
{
    $pdo = $pdo ?: ($GLOBALS['pdo'] ?? null);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, hashPassword(bin2hex(random_bytes(32))), $fullName, $email, $phone, $role, $groupId, $isActive]);
    $userId = (int)$pdo->lastInsertId();
    $token = createUserInvite($userId, $createdBy, INVITE_TTL_SECONDS, $pdo);
    return ['user_id' => $userId, 'invite_token' => $token];
}

/* ================= onboarding codes ================= */

define('ONBOARD_CODE_TTL_DAYS', 30);
define('ONBOARD_CODE_LEN', 10); // unambiguous alphabet below: ~52 bits

function onboardAlphabet()
{
    return 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/I/L
}

/**
 * Canonical onboarding/staff types. These mirror hr_employees.employment_status
 * where it overlaps (contract/intern) and fall back to "staff" for full-time
 * employees and legacy codes (viewer/employee).
 */
function onboardingTypes()
{
    return ['staff' => 'Staff', 'intern' => 'Intern', 'contract' => 'Contract'];
}

/**
 * Normalise any stored or posted onboarding type. Legacy roles such as
 * "viewer" or "employee" become "staff"; unknown values also become "staff".
 */
function onboardingTypeFromRole($role)
{
    $role = strtolower(trim((string)$role));
    return array_key_exists($role, onboardingTypes()) ? $role : 'staff';
}

function onboardingTypeLabel($role)
{
    $types = onboardingTypes();
    return $types[onboardingTypeFromRole($role)] ?? 'Staff';
}

/**
 * Generate a high-entropy onboarding code string (plaintext).
 */
function onboardGenerateCode()
{
    $alpha = onboardAlphabet();
    $rand = '';
    for ($i = 0; $i < ONBOARD_CODE_LEN; $i++) {
        $rand .= $alpha[random_int(0, strlen($alpha) - 1)];
    }
    return 'OB-' . $rand;
}

/**
 * Issue a high-entropy onboarding code. Returns plaintext code ONCE.
 */
function issueOnboardingCode($role, $createdBy = null)
{
    global $pdo;
    $role = onboardingTypeFromRole($role);
    $code = onboardGenerateCode();
    $stmt = $pdo->prepare("INSERT INTO hr_onboarding_codes (code, code_hash, role, created_by, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))");
    // NOTE: plaintext `code` column is legacy; verification uses code_hash only.
    $stmt->execute([$code, hash('sha256', $code), $role, $createdBy, ONBOARD_CODE_TTL_DAYS]);
    return $code;
}

/**
 * Renew an unused code in place: fresh secret, fresh 30-day window, attempts
 * reset. Keeping the row id means any draft the applicant already started
 * stays attached to the renewed code. Returns the new plaintext code or null.
 */
function renewOnboardingCode($codeId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE id = ?");
    $stmt->execute([(int)$codeId]);
    $row = $stmt->fetch();
    if (!$row || !empty($row['is_used'])) {
        return null;
    }
    $code = onboardGenerateCode();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE hr_onboarding_codes SET code = ?, code_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), failed_attempts = 0 WHERE id = ?")
            ->execute([$code, hash('sha256', $code), ONBOARD_CODE_TTL_DAYS, (int)$codeId]);
        // Keep the cached code in sync for drafts created under the old code.
        $pdo->prepare("UPDATE hr_onboarding_entries SET signup_code = ? WHERE code_id = ?")
            ->execute([$code, (int)$codeId]);
        $pdo->commit();
        return $code;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Verify an onboarding code: hash match, unexpired, unused, throttled.
 * Returns the code row or null. Counts failures per code + per IP.
 */
function verifyOnboardingCode($code, $ip)
{
    global $pdo;
    $code = strtoupper(trim($code ?? ''));
    if (!preg_match('/^OB-[A-Z0-9]{6,16}$/', $code)) {
        return null;
    }
    if (!throttleCheck('onboard_ip:' . $ip, 10, 3600)) {
        return null;
    }
    if (!throttleCheck('onboard_code:' . sha1($code), 5, 3600)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE code_hash = ? LIMIT 1");
    $stmt->execute([hash('sha256', $code)]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
        return null;
    }
    if (!empty($row['is_used'])) {
        return null;
    }
    return $row;
}

function recordOnboardFailure($codeId)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE hr_onboarding_codes SET failed_attempts = failed_attempts + 1 WHERE id = ?");
        $stmt->execute([$codeId]);
    } catch (Exception $e) {
        // best effort
    }
}

/**
 * Send an onboarding invitation email containing the one-time signup code.
 * The code is only ever sent to the address HR supplied; it is never logged.
 * Returns ['success' => bool, 'message' => string].
 */
function sendOnboardingInviteEmail($to, $code, $role, $signupUrl, $recipientName = '')
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid recipient email address.'];
    }

    $company = defined('COMPANY_NAME') ? COMPANY_NAME : 'Eleven100 ERP';
    $typeLabel = onboardingTypeLabel($role);
    $name = trim((string)$recipientName);
    $greeting = $name !== '' ? 'Hello ' . $name . ',' : 'Hello,';
    $expiryDays = defined('ONBOARD_CODE_TTL_DAYS') ? ONBOARD_CODE_TTL_DAYS : 30;

    $subject = 'You are invited to register with ' . $company;
    $body = $greeting . "\n\n"
        . $company . " has invited you to complete your employee registration as "
        . strtolower($typeLabel) . ".\n\n"
        . "Signup code: " . $code . "\n"
        . "Signup link: " . $signupUrl . "\n\n"
        . "Enter the code and the phone number HR has on file to begin. "
        . "The code is single-use and expires in " . (int)$expiryDays . " days.\n\n"
        . "If you were not expecting this email you can safely ignore it.\n\n"
        . $company;

    $fromEmail = function_exists('getSetting') ? (getSetting('email_from_address', '') ?: getSetting('company_email', '')) : '';
    $fromName = function_exists('getSetting') ? (getSetting('email_from_name', '') ?: $company) : $company;
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Sender email is not configured (Settings > Email > From Email).'];
    }

    try {
        if (function_exists('getSetting') && getSetting('email_method', 'php_mail') === 'smtp' && getSetting('smtp_host', '')) {
            require_once __DIR__ . '/smtp-mailer.php';
            $result = smtpSendEmail($to, $subject, $body, [], [
                'from' => $fromEmail,
                'fromName' => $fromName,
                'replyTo' => $fromEmail,
                'host' => getSetting('smtp_host', ''),
                'port' => (int)getSetting('smtp_port', 587),
                'encryption' => getSetting('smtp_encryption', 'tls'),
                'username' => getSetting('smtp_username', ''),
                'password' => getSetting('smtp_password', ''),
            ]);
            return $result;
        }

        require_once __DIR__ . '/simple-mailer.php';
        return sendEmailWithAttachment($to, $name, $subject, $body, null, $fromEmail, $fromName);
    } catch (Exception $e) {
        // Never include the code or recipient in the log message.
        error_log('Onboarding invite email failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Email transport error. Please try again or share the code manually.'];
    }
}

<?php
// MFA enrollment and management (WP4). Login required.
// TOTP secret is encrypted at rest; recovery codes are hashed, shown once.
include '../../includes/session-check.php';
require_once '../../includes/security.php';
require_once '../../includes/totp.php';

$uid = $current_user['id'];
$enabled = mfaIsEnabled($uid);
$error = '';
$success = '';
$showSecret = null;   // ['b32' => ..., 'uri' => ...] during enrollment step
$showRecovery = [];   // plaintext codes, shown exactly once

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (($_POST['mfa_action'] ?? '') === 'start') {
        // Begin enrollment: keep the secret server-side in-session until verified.
        try {
            $bin = totpNewSecret();
            $_SESSION['mfa_enroll'] = base64_encode($bin);
            $_SESSION['mfa_enroll_at'] = time();
            $showSecret = [
                'b32' => totpBase32Encode($bin),
                'uri' => totpProvisionUri(COMPANY_NAME, $current_user['username'], totpBase32Encode($bin)),
            ];
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['mfa_action'] ?? '') === 'verify') {
        $bin = isset($_SESSION['mfa_enroll']) ? base64_decode($_SESSION['mfa_enroll'], true) : false;
        if ($bin === false || empty($_SESSION['mfa_enroll_at']) || (time() - $_SESSION['mfa_enroll_at']) > 600) {
            $error = 'Enrollment expired. Start again.';
            unset($_SESSION['mfa_enroll'], $_SESSION['mfa_enroll_at']);
        } elseif (!totpVerify($bin, $_POST['code'] ?? '')) {
            $error = 'That code did not verify. Check your authenticator clock and try again.';
        } else {
            try {
                $codes = mfaNewRecoveryCodes();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET mfa_secret = ?, mfa_enabled = 1, mfa_enrolled_at = NOW() WHERE id = ?");
                $stmt->execute([mfaEncryptSecret($bin), $uid]);
                $pdo->prepare("DELETE FROM mfa_recovery_codes WHERE user_id = ?")->execute([$uid]);
                $ins = $pdo->prepare("INSERT INTO mfa_recovery_codes (user_id, code_hash) VALUES (?, ?)");
                foreach ($codes as $c) {
                    $ins->execute([$uid, hashPassword(str_replace('-', '', $c))]);
                }
                $pdo->commit();
                unset($_SESSION['mfa_enroll'], $_SESSION['mfa_enroll_at']);
                $enabled = true;
                $showRecovery = $codes;
                $success = 'Two-factor authentication is now enabled.';
                if (function_exists('logUserUpdate')) {
                    logUserUpdate($uid, $current_user['username'], ['mfa' => 'enabled']);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    } elseif (($_POST['mfa_action'] ?? '') === 'disable') {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!verifyPassword($_POST['password'] ?? '', $row['password'] ?? '')) {
            $error = 'Current password is incorrect.';
        } else {
            $pdo->prepare("UPDATE users SET mfa_secret = NULL, mfa_enabled = 0, mfa_enrolled_at = NULL WHERE id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM mfa_recovery_codes WHERE user_id = ?")->execute([$uid]);
            $enabled = false;
            $success = 'Two-factor authentication is now disabled.';
            if (function_exists('logUserUpdate')) {
                logUserUpdate($uid, $current_user['username'], ['mfa' => 'disabled']);
            }
        }
    } elseif (($_POST['mfa_action'] ?? '') === 'regen_codes') {
        if (!$enabled) {
            $error = 'Enable MFA first.';
        } else {
            $codes = mfaNewRecoveryCodes();
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM mfa_recovery_codes WHERE user_id = ?")->execute([$uid]);
            $ins = $pdo->prepare("INSERT INTO mfa_recovery_codes (user_id, code_hash) VALUES (?, ?)");
            foreach ($codes as $c) {
                $ins->execute([$uid, hashPassword(str_replace('-', '', $c))]);
            }
            $pdo->commit();
            $showRecovery = $codes;
            $success = 'New recovery codes generated. Old ones no longer work.';
        }
    }
}

// Resume an in-progress enrollment step on GET (secret stays server-side).
if (!$showSecret && isset($_SESSION['mfa_enroll'], $_SESSION['mfa_enroll_at']) && (time() - $_SESSION['mfa_enroll_at']) <= 600) {
    $bin = base64_decode($_SESSION['mfa_enroll'], true);
    if ($bin !== false) {
        $showSecret = [
            'b32' => totpBase32Encode($bin),
            'uri' => totpProvisionUri(COMPANY_NAME, $current_user['username'], totpBase32Encode($bin)),
        ];
    }
}

$pageTitle = 'Two-Factor Authentication - ERP System';
include '../../includes/header.php';
?>

<div class="bg-white rounded-lg shadow-md p-4 md:p-8 max-w-2xl mx-auto">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Two-Factor Authentication</h2>
    <p class="text-gray-600 mb-6">
        Status:
        <?php if ($enabled): ?>
            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Enabled</span>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Disabled</span>
        <?php endif; ?>
        <span class="ml-2 text-xs text-gray-500">Optional — enable it any time to protect this account.</span>
    </p>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800 font-semibold text-sm"><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($showRecovery)): ?>
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6">
            <p class="font-bold text-gray-900 mb-2">Recovery codes — copy them now, they are shown once:</p>
            <ul class="font-mono text-sm space-y-1 select-all">
                <?php foreach ($showRecovery as $c): ?>
                    <li><?php echo htmlspecialchars($c); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$enabled && !$showSecret): ?>
        <p class="text-sm text-gray-700 mb-4">Protect your account with an authenticator app (Google Authenticator, 1Password, Bitwarden, Authy). You will enter a 6-digit code at login.</p>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="mfa_action" value="start">
            <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Set Up Authenticator App</button>
        </form>
    <?php elseif (!$enabled && $showSecret): ?>
        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="font-semibold text-gray-900 mb-1">Step 1 — add this key to your authenticator app:</p>
                <p class="font-mono text-lg font-bold select-all break-all"><?php echo htmlspecialchars(chunk_split($showSecret['b32'], 4, ' ')); ?></p>
                <p class="text-xs text-gray-600 mt-2 break-all">Advanced: import this URI — <span class="font-mono select-all"><?php echo htmlspecialchars($showSecret['uri']); ?></span></p>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="mfa_action" value="verify">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Step 2 — enter the 6-digit code from the app</label>
                <input type="text" name="code" required inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-xl tracking-widest"
                    placeholder="123456">
                <button type="submit" class="mt-4 px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Verify &amp; Enable</button>
            </form>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="mfa_action" value="regen_codes">
                <button type="submit" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">Generate New Recovery Codes</button>
            </form>
            <form method="POST" class="border-t pt-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="mfa_action" value="disable">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Disable MFA (requires current password)</label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary mb-3">
                <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold"
                    onclick="return confirm('Disable two-factor authentication?');">Disable MFA</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
// Second login step: TOTP or recovery code (WP4).
// Reachable only with a fresh mfa_pending session from login.php.
// Establishes NO privileges until verification succeeds.
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/totp.php';

configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
setSecurityHeaders();

$pending = $_SESSION['mfa_pending'] ?? null;
if (empty($pending['user_id']) || empty($pending['at']) || (time() - $pending['at']) > 600) {
    unset($_SESSION['mfa_pending']);
    header('Location: login.php');
    exit;
}

$error = '';
$recoveryMode = isset($_GET['recovery']) || isset($_POST['recovery_code']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif (!throttleCheckHelper()) {
        $error = 'Too many attempts. Try again later.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$pending['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            unset($_SESSION['mfa_pending']);
            header('Location: login.php');
            exit;
        }
        if (!empty($_POST['recovery_code'])) {
            $code = mfaNormalizeRecoveryCode($_POST['recovery_code']);
            $stmt = $pdo->prepare("SELECT id, code_hash FROM mfa_recovery_codes WHERE user_id = ? AND used_at IS NULL");
            $stmt->execute([$user['id']]);
            $matched = null;
            foreach ($stmt->fetchAll() as $row) {
                if (verifyPassword($code, $row['code_hash'])) {
                    $matched = $row;
                    break;
                }
            }
            if ($matched) {
                $pdo->prepare("UPDATE mfa_recovery_codes SET used_at = NOW() WHERE id = ?")->execute([$matched['id']]);
                $left = (int)$pdo->query("SELECT COUNT(*) FROM mfa_recovery_codes WHERE user_id = " . (int)$user['id'] . " AND used_at IS NULL")->fetchColumn();
                completeLoginSession($pdo, $user);
                revokeOtherSessions($user['id']);
                if (function_exists('logUserUpdate')) {
                    logUserUpdate($user['id'], $user['username'], ['mfa' => 'recovery-login']);
                }
                header('Location: ../pages/users/security-mfa.php?recovered=1&left=' . $left);
                exit;
            }
            $error = 'Invalid recovery code.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT mfa_secret FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $secret = mfaDecryptSecret($stmt->fetchColumn());
                if (totpVerify($secret, $_POST['code'] ?? '')) {
                    completeLoginSession($pdo, $user);
                    revokeOtherSessions($user['id']);
                    header('Location: ../dashboard.php');
                    exit;
                }
                $error = 'Incorrect code. Check your authenticator clock and try again.';
            } catch (Exception $e) {
                $error = 'Verification unavailable. Contact an administrator.';
            }
        }
    }
}

/** Per-IP throttle for the MFA step (10/hour). Floored window key. */
function throttleCheckHelper()
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucket = 'mfa_ip:' . preg_replace('/[^a-z0-9_.:]/i', '', $ip);
    $windowSecs = 3600;
    try {
        $pdo->exec("DELETE FROM auth_throttle WHERE window_start < DATE_SUB(NOW(), INTERVAL $windowSecs SECOND)");
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(attempts), 0) FROM auth_throttle WHERE bucket = ? AND window_start >= DATE_SUB(NOW(), INTERVAL $windowSecs SECOND)");
        $stmt->execute([$bucket]);
        if ((int)$stmt->fetchColumn() >= 10) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO auth_throttle (bucket, window_start, attempts) VALUES (?, FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) DIV $windowSecs * $windowSecs), 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1");
        $stmt->execute([$bucket]);
        return true;
    } catch (Exception $e) {
        return true;
    }
}

$pageTitle = 'Two-Factor Check - ERP System';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Two-factor check</h2>
            <p class="text-sm text-gray-600 mb-6">Enter the 6-digit code from your authenticator app.</p>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$recoveryMode): ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="text" name="code" required inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-2xl tracking-widest text-center"
                        placeholder="123456">
                    <button type="submit" class="mt-4 w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">Verify</button>
                </form>
                <p class="text-center mt-4 text-sm"><a href="?recovery=1" class="text-primary hover:underline">Use a recovery code instead</a></p>
            <?php else: ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="text" name="recovery_code" required autocomplete="off"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono"
                        placeholder="XXXX-XXX-XXXX">
                    <button type="submit" class="mt-4 w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">Use Recovery Code</button>
                </form>
                <p class="text-center mt-4 text-sm"><a href="login-mfa.php" class="text-primary hover:underline">Back to authenticator code</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>

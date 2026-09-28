<?php
// Forced password change (first login with a temporary password).
// Reachable ONLY with a fresh pwd_change_required session flag set by
// login.php after verifying the temporary password. The flag authorizes
// nothing else and expires in 15 minutes.
require_once '../../config.php';
require_once '../../includes/passwords.php';
require_once '../../includes/security.php';

configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
setSecurityHeaders();

$flag = $_SESSION['pwd_change_required'] ?? null;
if (empty($flag['user_id']) || empty($flag['at']) || (time() - $flag['at']) > 900) {
    unset($_SESSION['pwd_change_required']);
    header('Location: ../../login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, must_change_password FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([(int)$flag['user_id']]);
            $user = $stmt->fetch();
            if (!$user || empty($user['must_change_password'])) {
                unset($_SESSION['pwd_change_required']);
                header('Location: ../../login.php?changed=1');
                exit;
            }
            if (!verifyPassword($current, $user['password'])) {
                $error = 'Current temporary password is incorrect.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } else {
                $problems = validatePasswordPolicy($new, $user['password'], $user['username']);
                if ($problems) {
                    $error = implode(' ', $problems);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([hashPassword($new), $user['id']]);
                    require_once '../../includes/audit.php';
                    if (function_exists('logAudit')) {
                        logAudit('password_change', 'user', $user['id'], ['username' => $user['username'], 'via' => 'first-login']);
                    }
                    unset($_SESSION['pwd_change_required']);
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                    header('Location: ../../login.php?changed=1');
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log('Forced password change error: ' . $e->getMessage());
            $error = 'Could not change password. Try again or contact your administrator.';
        }
    }
}

$pageTitle = 'Set a New Password - ERP System';
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
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Set a new password</h2>
            <p class="text-sm text-gray-600 mb-6">Your administrator gave you a temporary password. Choose your own password to continue.</p>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?php echo csrfField(); ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Temporary password</label>
                    <input type="password" name="current_password" required autocomplete="current-password"
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                    <input type="password" name="new_password" required autocomplete="new-password"
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</label>
                    <input type="password" name="confirm_password" required autocomplete="new-password"
                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">Set Password &amp; Continue</button>
            </form>
        </div>
    </div>
</body>

</html>

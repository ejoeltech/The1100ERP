<?php
// Accept a one-time user invite and set an initial password (public, WP2).
// The token in the URL is the only credential; it is hashed in the DB,
// expires in 48h, and is single-use. No login required or possible here.
require_once '../../config.php';
require_once '../../includes/passwords.php';
require_once '../../includes/security.php';
require_once '../../includes/invites.php';

configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$invite = $token !== '' ? findValidInvite($token) : null;

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload and try again.';
    } elseif (!throttleCheck('invite_ip:' . $ip, 10, 3600)) {
        $error = 'Too many attempts. Try again later.';
    } elseif (!$invite) {
        $error = 'This invite link is invalid, expired, or already used.';
    } else {
        $pw1 = $_POST['password'] ?? '';
        $pw2 = $_POST['confirm_password'] ?? '';
        if ($pw1 !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            $problems = validatePasswordPolicy($pw1, null, $invite['username'] ?? '');
            if ($problems) {
                $error = implode(' ', $problems);
            } else {
                try {
                    consumeInvite($invite['id'], $invite['user_id'], hashPassword($pw1));
                    $done = true;
                } catch (Exception $e) {
                    $error = 'This invite link is invalid, expired, or already used.';
                }
            }
        }
    }
}

$pageTitle = 'Set Your Password - ERP System';
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
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Set your password</h2>
            <p class="text-sm text-gray-600 mb-6">
                <?php echo $invite ? 'Welcome, ' . htmlspecialchars($invite['username']) . '. Choose a password of at least 12 characters.' : 'Invite validation.'; ?>
            </p>

            <?php if ($done): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-green-800 font-semibold">✓ Password set. Your invite link is now spent.</p>
                </div>
                <a href="../../login.php" class="block text-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">Go to Login</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($invite && !$error): ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New password</label>
                            <input type="password" name="password" required minlength="12" autocomplete="new-password"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="Minimum 12 characters">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm password</label>
                            <input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="Re-enter password">
                        </div>
                        <button type="submit"
                            class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                            Set Password
                        </button>
                    </form>
                <?php elseif (!$invite): ?>
                    <p class="text-sm text-gray-600">This invite link is invalid, expired, or already used. Ask an administrator for a new one.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>

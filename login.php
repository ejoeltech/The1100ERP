<?php
require_once 'includes/security.php';
configureSessionCookies();
session_start();
require_once 'config.php';
require_once 'includes/auth.php';

// Secure Session
secureSession();
setSecurityHeaders();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 2. Rate limits (WP4): global + per-IP + per-username, DB-backed so
        // clearing cookies does not reset them. Generic messages throughout.
        require_once 'includes/invites.php'; // throttleCheck()
        $rateError = null;
        if (!throttleCheck('login_global', 300, 3600)) {
            $rateError = 'Too many login attempts right now. Try again later.';
        } elseif (!throttleCheck('login_ip:' . preg_replace('/[^a-z0-9_.:]/i', '', $ip), 30, 3600)) {
            $rateError = 'Too many login attempts from your network. Try again later.';
        } elseif (!throttleCheck('login_user:' . md5(strtolower($username)), 10, 900)) {
            $rateError = 'Too many login attempts for this account. Try again later.';
        }
        if ($rateError !== null) {
            loginAuditLog($pdo, $username, $ip, 'rate_limited');
            $error = $rateError;
        } else {
            // Small progressive delay blunts online guessing (bounded).
            usleep(500000);
            // 3. Attempt login
            if (login($pdo, $username, $password)) {
                // WP2: accounts flagged for forced reset cannot start a session.
                // They don't know any password (random), so this only fires for
                // inconsistencies — direct them to their invite link / admin.
                // Missing column (migration not applied yet) means no flag.
                $mustChange = false;
                try {
                    $flagStmt = $pdo->prepare("SELECT must_change_password FROM users WHERE username = ?");
                    $flagStmt->execute([$username]);
                    $flagRow = $flagStmt->fetch();
                    $mustChange = $flagRow && !empty($flagRow['must_change_password']);
                } catch (Exception $e) {
                    $mustChange = false;
                }
                if ($mustChange) {
                    // Temporary password verified: park a single-purpose flag
                    // and send the user to set their own password. The flag
                    // authorizes nothing else and expires in 15 minutes.
                    $flagUserId = null;
                    try {
                        $idStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $idStmt->execute([$username]);
                        $idRow = $idStmt->fetch();
                        $flagUserId = $idRow ? (int)$idRow['id'] : null;
                    } catch (Exception $e) {
                        $flagUserId = null;
                    }
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                    if ($flagUserId) {
                        $_SESSION['pwd_change_required'] = ['user_id' => $flagUserId, 'at' => time()];
                        header('Location: pages/users/force-password.php');
                    } else {
                        $error = 'A password change is required on this account before you can log in. Contact your administrator for a temporary password.';
                    }
                    exit;
                } else {
                    // WP4: accounts with MFA pause here with zero privileges until
                    // the second step (pages/login-mfa.php) verifies the code.
                    require_once 'includes/totp.php';
                    $loginUid = $_SESSION['user_id'] ?? null;
                    if ($loginUid && function_exists('mfaGloballyEnabled') && !mfaGloballyEnabled()) {
                        // MFA switched off globally: straight through, even for enrolled accounts.
                        loginAuditLog($pdo, $username, $ip, 'success');
                        header('Location: dashboard.php');
                        exit;
                    }
                    if ($loginUid && mfaIsEnabled($loginUid)) {
                        session_regenerate_id(true);
                        $_SESSION['mfa_pending'] = ['user_id' => $loginUid, 'at' => time()];
                        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);
                        loginAuditLog($pdo, $username, $ip, 'mfa_challenge');
                        header('Location: pages/login-mfa.php');
                        exit;
                    }
                    loginAuditLog($pdo, $username, $ip, 'success');
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // Identical message + comparable timing for unknown users and
                // wrong passwords (WP4: no oracle, no timing leak).
                dummyPasswordVerify();
                loginAuditLog($pdo, $username, $ip, 'failed');
                $error = 'Invalid username or password';
            }
        }
    }
}

/**
 * Best-effort audit row for login outcomes (never includes passwords).
 */
function loginAuditLog($pdo, $username, $ip, $outcome)
{
    try {
        require_once 'includes/audit.php';
        if (function_exists('logAudit')) {
            logAudit('login_' . $outcome, 'user', null, ['username' => mb_substr($username, 0, 50), 'ip' => $ip]);
        }
    } catch (Exception $e) {
        // logging must never break login
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0076BE',
                        secondary: '#34A853',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Responsive CSS -->
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        <!-- Logo/Brand -->
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl mb-4">
                <span class="text-white font-bold text-3xl">11</span>
            </div>
            <h1
                class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-2">
                1100-ERP
            </h1>
            <p class="text-gray-600">Enterprise Resource Planning</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Login</h2>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-800 text-sm">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['changed'])): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-green-800 font-semibold text-sm">Password updated. Log in with your new password.</p>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Username
                    </label>
                    <input type="text" name="username" required autofocus
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="Enter your username">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Password
                    </label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="Enter your password">
                </div>

                <?php echo csrfField(); ?>

                <button type="submit"
                    class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-700 font-semibold transition-colors">
                    Login
                </button>
            </form>


        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            <p>
                ©
                <?php echo date('Y'); ?> <?php echo defined('COMPANY_NAME') ? COMPANY_NAME : 'Your Company Name'; ?>
            </p>
        </div>
    </div>

</body>

</html>
<?php
require_once 'config.php';
// Public Signup Landing Page

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $phone = trim($_POST['phone']);

    if (empty($code)) {
        $error = "Please enter your signup user code.";
    } else {
        // 1. Check if Code Exists
        $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE code = ?");
        $stmt->execute([$code]);
        $codeData = $stmt->fetch();

        if (!$codeData) {
            $error = "Invalid Signup Code.";
        } elseif ($codeData['is_used']) {
            $error = "This code has already been used and locked.";
        } else {
            // Code is valid. Check if entry exists for this code (created previously) or create new sess
            // We use session to track "logged in" state for onboarding
            session_start();
            $_SESSION['onboarding_code_id'] = $codeData['id'];
            $_SESSION['onboarding_code'] = $codeData['code'];
            $_SESSION['onboarding_phone'] = $phone; // used for verification if returning

            // Redirect to Form
            header("Location: modules/hr/pages/signup-form.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Signup |
        <?php echo COMPANY_NAME; ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Employee Onboarding</h1>
            <p class="text-gray-500">Enter your code to begin registration</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-200">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Signup Code</label>
                <input type="text" name="code" placeholder="OB-XXXXXX" required
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase tracking-widest text-center font-bold text-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="tel" name="phone" placeholder="080..." required
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">This will be your initial password.</p>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg">
                Proceed to Registration
            </button>
        </form>
    </div>

</body>

</html>
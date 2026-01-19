<?php
// factory-reset.php
// WARNING: THIS SCRIPT WIPES THE ENTIRE DATABASE AND RESETS CONFIGURATION

if (isset($_POST['confirm']) && $_POST['confirm'] === 'YES') {

    // 1. Load config to get DB credentials
    if (file_exists('config.php')) {
        require_once 'config.php';

        try {
            // Disable FK checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            // Drop each table
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        } catch (Exception $e) {
            die("Error wiping database: " . $e->getMessage());
        }
    }

    // 2. Delete Config Files
    $filesToDelete = [
        'config.php',
        'config.php.bak',
        'setup/lock'
    ];

    foreach ($filesToDelete as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // 3. Redirect to Setup
    header("Location: setup/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>FACTORY RESET - WARNING</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-red-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full text-center border-t-8 border-red-600">
        <svg class="w-20 h-20 text-red-600 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
            </path>
        </svg>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">FACTORY RESET</h1>

        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 text-left text-sm">
            <p class="font-bold mb-2">WARNING: IRREVERSIBLE ACTION</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>All Customers, Quotes, Invoices, Receipts will be DELETED.</li>
                <li>All Settings & Company Info will be DELETED.</li>
                <li>All Admin Accounts will be DELETED.</li>
                <li>The system will return to the Installation Wizard.</li>
            </ul>
        </div>

        <p class="text-gray-600 mb-8">
            Are you absolutely sure you want to proceed? This cannot be undone.
        </p>

        <form method="POST">
            <input type="hidden" name="confirm" value="YES">
            <div class="flex flex-col gap-3">
                <button type="submit"
                    class="w-full px-6 py-4 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 shadow-lg transition-colors">
                    ⚠️ YES, DELETE EVERYTHING
                </button>
                <a href="dashboard.php"
                    class="w-full px-6 py-3 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                    No, Cancel
                </a>
            </div>
        </form>
    </div>
</body>

</html>
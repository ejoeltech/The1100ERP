<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?? 'Bluedots Technologies' ?>
    </title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <!-- Minimal header for public pages -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-900">
                    <?= COMPANY_NAME ?? 'Bluedots Technologies' ?>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="../login.php" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Login</a>
                    <a href="../register.php"
                        class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">Sign
                        Up</a>
                <?php else: ?>
                    <a href="../dashboard.php"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Dashboard</a>
                    <a href="../logout.php"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
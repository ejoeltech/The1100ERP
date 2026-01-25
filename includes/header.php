<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Bluedots Technologies'; ?></title>

    <?php
    // Determine base path for relative links
    $base_path = '';
    if (file_exists('config.php')) {
        $base_path = '.';
    } elseif (file_exists('../config.php')) {
        $base_path = '..';
    } elseif (file_exists('../../config.php')) {
        $base_path = '../..';
    } elseif (file_exists('../../../config.php')) {
        $base_path = '../../..';
    }

    // Dynamic favicon from uploaded logo
    $favicon_path = 'uploads/logo/favicon.png';
    if (file_exists(__DIR__ . '/../' . $favicon_path)) {
        echo '<link rel="icon" type="image/png" href="' . $base_path . '/' . $favicon_path . '">';
    }
    ?>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Tailwind Config -->
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
        // Expose base path to JS
        window.AppConfig = {
            basePath: '<?php echo $base_path; ?>'
        };
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Responsive CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/responsive.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        @media (min-width: 1024px) {
            html {
                font-size: 82%;
            }
        }

        .naira::before {
            content: '₦';
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>

    <!-- Mobile Menu Drawer -->
    <div id="mobileMenu" class="mobile-menu">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Menu</h2>
            <button onclick="toggleMobileMenu()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <?php if (isset($current_user)): ?>
            <nav class="space-y-1">
                <a href="<?php echo $base_path; ?>/dashboard.php"
                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg font-semibold">
                    📊 Dashboard
                </a>

                <!-- Inventory Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Inventory</p>
                    <a href="<?php echo $base_path; ?>/pages/products/manage-products.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📦 Products
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/customers/manage-customers.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        👥 Customers
                    </a>
                </div>

                <!-- Documents Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Documents</p>
                    <a href="<?php echo $base_path; ?>/pages/view-quotes.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📄 Quotes
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/readymade-quotes.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        ⚡ Ready-Made Quotes
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/view-invoices.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📋 Invoices
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/view-receipts.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💰 Receipts
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/payments/manage-payments.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💳 Payments
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/archives.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📦 Archives
                    </a>
                </div>

                <!-- AI Tools Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">AI Tools</p>
                    <a href="<?php echo $base_path; ?>/pages/system-designer.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🛠️ System Designer
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/create-proposal.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        ✨ AI Proposal Creator
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/roi-calculator.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💰 ROI Calculator
                    </a>
                </div>

                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <!-- Admin Section -->
                    <div class="border-t border-gray-200 pt-2 mt-2">
                        <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Admin</p>
                        <a href="<?php echo $base_path; ?>/pages/users/manage-users.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            👤 Manage Users
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            ⚙️ Settings
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/audit-log.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            📊 Audit Log
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/ai-settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            🤖 AI Settings
                        </a>
                    </div>
                <?php endif; ?>

                <!-- User Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Account</p>
                    <a href="<?php echo $base_path; ?>/pages/users/profile.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        👤 My Profile
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/users/change-password.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🔒 Change Password
                    </a>
                    <a href="<?php echo $base_path; ?>/logout.php"
                        class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-semibold">
                        🚪 Logout
                    </a>
                </div>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo - Clickable -->
                <a href="<?php echo $base_path; ?>/dashboard.php"
                    class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <?php
                    // Use uploaded logo if available
                    $logo_path = 'uploads/logo/company_logo_';
                    $logo_files = glob(__DIR__ . '/../uploads/logo/company_logo_*');
                    if (!empty($logo_files)) {
                        $latest_logo = basename(end($logo_files));
                        echo '<img src="' . $base_path . '/uploads/logo/' . htmlspecialchars($latest_logo) . '" alt="Company Logo" class="h-12 object-contain">';
                    } else {
                        // Default logo circles
                        echo '<div class="flex items-center gap-1">';
                        echo '<div class="w-3 h-3 bg-sky-500 rounded-full"></div>';
                        echo '<div class="w-5 h-5 bg-sky-600 rounded-full border-2 border-secondary"></div>';
                        echo '<div class="w-8 h-8 bg-sky-700 rounded-full"></div>';
                        echo '<div class="w-10 h-10 border-4 border-secondary rounded-full"></div>';
                        echo '</div>';
                        echo '<div>';
                        echo '<h1 class="text-2xl font-bold text-gray-900">Bluedots</h1>';
                        echo '<p class="text-[8px] tracking-[0.3em] uppercase font-bold text-gray-600">TECHNOLOGIES</p>';
                        echo '</div>';
                    }
                    ?>
                </a>

                <!-- Navigation -->
                <?php if (isset($current_user)): ?>
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="<?php echo $base_path; ?>/dashboard.php"
                            class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold transition-colors">Dashboard</a>

                        <!-- Inventory Dropdown -->
                        <div class="relative group">
                            <button
                                class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                Inventory
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <a href="<?php echo $base_path; ?>/pages/products/manage-products.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">Products</div>
                                    <div class="text-xs text-gray-500">Manage catalog</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/customers/manage-customers.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">Customers</div>
                                    <div class="text-xs text-gray-500">Manage clients</div>
                                </a>
                            </div>
                        </div>

                        <!-- Documents Dropdown -->
                        <div class="relative group">
                            <button
                                class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                Documents
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <a href="<?php echo $base_path; ?>/pages/view-quotes.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">Quotes</div>
                                    <div class="text-xs text-gray-500">View all quotes</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/readymade-quotes.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Ready-Made Quotes</div>
                                    <div class="text-xs text-gray-500">Quick templates</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/view-invoices.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Invoices</div>
                                    <div class="text-xs text-gray-500">View all invoices</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/view-receipts.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Receipts</div>
                                    <div class="text-xs text-gray-500">Payment receipts</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/payments/manage-payments.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Payments</div>
                                    <div class="text-xs text-gray-500">Track allocations</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/archives.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">Archives</div>
                                    <div class="text-xs text-gray-500">View archived docs</div>
                                </a>
                            </div>
                        </div>

                        <!-- AI Tools Dropdown -->
                        <div class="relative group">
                            <button
                                class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                AI Tools
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <a href="<?php echo $base_path; ?>/pages/system-designer.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">🛠️ System Designer</div>
                                    <div class="text-xs text-gray-500">Compatibility check</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/create-proposal.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">✨ Proposal Creator</div>
                                    <div class="text-xs text-gray-500">Generate with AI</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/roi-calculator.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">💰 ROI Calculator</div>
                                    <div class="text-xs text-gray-500">Solar savings analysis</div>
                                </a>
                            </div>
                        </div>

                        <?php if (function_exists('isAdmin') && isAdmin()): ?>
                            <!-- Admin Dropdown -->
                            <div class="relative group">
                                <button
                                    class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                    Admin
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div
                                    class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                    <a href="<?php echo $base_path; ?>/pages/users/manage-users.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                        <div class="font-semibold">Manage Users</div>
                                        <div class="text-xs text-gray-500">Users & roles</div>
                                    </a>
                                    <a href="<?php echo $base_path; ?>/pages/settings.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">Settings</div>
                                        <div class="text-xs text-gray-500">System config</div>
                                    </a>
                                    <a href="<?php echo $base_path; ?>/pages/audit-log.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">Audit Log</div>
                                        <div class="text-xs text-gray-500">Activity history</div>
                                    </a>
                                    <a href="<?php echo $base_path; ?>/pages/ai-settings.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                        <div class="font-semibold">AI Settings</div>
                                        <div class="text-xs text-gray-500">Configure AI features</div>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <span class="text-gray-300">|</span>

                        <!-- User Menu -->
                        <div class="relative group">
                            <button class="text-gray-600 hover:text-primary font-medium flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <div class="px-4 py-3 border-b border-gray-200">
                                    <p class="text-xs text-gray-500">Signed in as</p>
                                    <p class="text-sm font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($current_user['username']); ?>
                                    </p>
                                </div>
                                <a href="<?php echo $base_path; ?>/pages/users/profile.php"
                                    class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                    👤 My Profile
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/users/change-password.php"
                                    class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                    🔒 Change Password
                                </a>
                                <hr class="my-1">
                                <a href="<?php echo $base_path; ?>/logout.php"
                                    class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-b-lg font-semibold">
                                    🚪 Logout
                                </a>
                            </div>
                        </div>
                    </nav>

                    <!-- Mobile Hamburger Menu Button -->
                    <button onclick="toggleMobileMenu()" class="md:hidden hamburger" aria-label="Toggle menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                <?php else: ?>
                    <nav class="flex gap-4">
                        <a href="<?php echo $base_path; ?>/login.php"
                            class="text-gray-600 hover:text-primary font-medium">Login</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Dropdown Styles -->
    <style>
        .group:hover .group-hover\:opacity-100 {
            opacity: 1;
        }

        .group:hover .group-hover\:visible {
            visibility: visible;
        }
    </style>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
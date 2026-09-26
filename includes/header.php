<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo function_exists('generateCSRFToken') ? htmlspecialchars(generateCSRFToken()) : ''; ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? 'ERP System', ENT_QUOTES, 'UTF-8'); ?></title>

<?php
if (function_exists('setSecurityHeaders')) {
    setSecurityHeaders();
}
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
                        primary: '<?php echo function_exists('getSetting') ? htmlspecialchars(getSetting('theme_color', '#0076BE')) : '#0076BE'; ?>',
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
    <script src="<?php echo $base_path; ?>/assets/js/helpers.js?v=<?php echo time(); ?>"></script>

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

                <!-- Store Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Store</p>
                    <a href="<?php echo $base_path; ?>/pages/store/items.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🏬 Items
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/store/categories.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📂 Categories
                    </a>
                </div>

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
                    <a href="<?php echo $base_path; ?>/pages/leads/manage-leads.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🎯 Leads
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

                <!-- HR Section -->
                <?php $hrActive = isset($currentPage) && strpos($currentPage, 'hr_') === 0; ?>
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">HR Management</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/dashboard.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_dashboard'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        📊 HR Dashboard
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/employees.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_employees'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        👥 Employees
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/attendance.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_attendance'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🕒 Attendance
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/leave.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_leave'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        📅 Leave
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/payroll.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_payroll'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        💰 Payroll
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/voting.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_voting'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🗳️ Staff Voting
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-maker.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idcards'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🎨 ID Templates
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-produce.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idproduce'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🪪 Produce ID Cards
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-auto.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idauto'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        ⚡ Bulk ID Production
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/onboarding-admin.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_onboarding'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🚀 Onboarding
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
                        <a href="<?php echo $base_path; ?>/pages/users/manage-groups.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            🛡️ Groups & Permissions
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            ⚙️ Settings
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/audit-log.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            📊 Audit Log
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/system-update.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            🔧 System Update
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/ai-settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg flex items-center gap-2">
                            <img src="<?php echo $base_path; ?>/assets/icons/magic.png" class="w-4 h-4" alt="Icon">
                            Smart Tools
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
                    // Use uploaded logo if available, else placeholder (change in Settings → Company Information)
                    $logo_files = glob(__DIR__ . '/../uploads/logo/company_logo_*');
                    if (!empty($logo_files)) {
                        $latest_logo = basename(end($logo_files));
                        echo '<img src="' . $base_path . '/uploads/logo/' . htmlspecialchars($latest_logo) . '" alt="' . htmlspecialchars(COMPANY_NAME) . '" class="h-12 object-contain">';
                    } else {
                        echo '<img src="' . $base_path . '/assets/img/logo-placeholder.svg" alt="Logo Placeholder" class="h-10 object-contain opacity-80" title="Upload your logo in Settings → Company Information">';
                    }
                    ?>
                </a>

                <!-- Navigation -->
                <?php if (isset($current_user)): ?>
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="<?php echo $base_path; ?>/dashboard.php"
                            class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold transition-colors">Dashboard</a>

                        <!-- Store Dropdown -->
                        <div class="relative group">
                            <button
                                class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                Store
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <a href="<?php echo $base_path; ?>/pages/store/items.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">Items</div>
                                    <div class="text-xs text-gray-500">Inventory Items</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/store/categories.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Categories</div>
                                    <div class="text-xs text-gray-500">Item Groups</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/accessories/items.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">Accessories</div>
                                    <div class="text-xs text-gray-500">Internal tools & consumables</div>
                                </a>
                            </div>
                        </div>

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
                                <a href="<?php echo $base_path; ?>/pages/leads/manage-leads.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">🎯 Leads</div>
                                    <div class="text-xs text-gray-500">Capture & follow-up</div>
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
                                Tools
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

                        <!-- HR Dropdown -->
                        <div class="relative group">
                            <button
                                class="px-4 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg font-semibold flex items-center gap-1">
                                HR
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/dashboard.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">📊 HR Dashboard</div>
                                    <div class="text-xs text-gray-500">Overview & Stats</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/employees.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">👥 Employees</div>
                                    <div class="text-xs text-gray-500">Manage Staff</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/attendance.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">🕒 Attendance</div>
                                    <div class="text-xs text-gray-500">Track Time</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/leave.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">📅 Leave</div>
                                    <div class="text-xs text-gray-500">Requests & Status</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/payroll.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">💰 Payroll</div>
                                    <div class="text-xs text-gray-500">Salaries & Payslips</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/voting.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">🗳️ Staff Voting</div>
                                    <div class="text-xs text-gray-500">Vote for Colleague</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/id-maker.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">🎨 ID Templates</div>
                                    <div class="text-xs text-gray-500">Design card templates</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/id-produce.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">🪪 Produce ID Cards</div>
                                    <div class="text-xs text-gray-500">Manual card production</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/id-auto.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">⚡ Bulk ID Production</div>
                                    <div class="text-xs text-gray-500">Generate from HR records</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/onboarding-admin.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">🚀 Onboarding</div>
                                    <div class="text-xs text-gray-500">Manage Signups</div>
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
                                    <a href="<?php echo $base_path; ?>/pages/users/manage-groups.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">Groups & Permissions</div>
                                        <div class="text-xs text-gray-500">Access control</div>
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

    <!-- Chat Widget -->
    <?php include_once __DIR__ . '/chat-widget.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
if (function_exists('setSecurityHeaders')) {
    setSecurityHeaders();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo function_exists('generateCSRFToken') ? htmlspecialchars(generateCSRFToken()) : ''; ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? 'ERP System', ENT_QUOTES, 'UTF-8'); ?></title>

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

    // Menu permissions: hide links the current user cannot use.
    $canPerm = function ($key) {
        return function_exists('hasPermission') ? hasPermission($key) : (function_exists('isAdmin') && isAdmin());
    };
    $canHrView = $canPerm('hr_view');
    $canHrManage = $canPerm('hr_manage');
    $canPayrollView = $canPerm('payroll_view');
    $canRecruit = $canPerm('recruitment_manage');
    $canHrAny = $canHrView || $canHrManage || $canPayrollView || $canRecruit;
    $showHrMenu = true; // Attendance and Leave are self-service for every signed-in user.
    $canSales = $canPerm('manage_customers') || $canPerm('manage_leads');
    $canInventory = $canPerm('manage_products') || $canPerm('manage_store') || $canPerm('manage_accessories');
    $canProposal = $canPerm('create_document') || $canPerm('create_quote');
    $canAdminMenu = $canPerm('manage_users') || $canPerm('manage_access') || $canPerm('manage_settings') || $canPerm('view_audit_log');
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isDashboard = (($currentPage ?? '') === 'dashboard') || preg_match('#/(dashboard|index)\.php$#', $scriptPath);
    $isSalesActive = strpos($scriptPath, '/pages/customers/') !== false || strpos($scriptPath, '/pages/leads/') !== false;
    $isInventoryActive = (strpos($scriptPath, '/pages/products/') !== false
        || strpos($scriptPath, '/pages/store/') !== false
        || strpos($scriptPath, '/pages/accessories/') !== false)
        && strpos($scriptPath, '/pages/products/equipment-datasheets.php') === false;
    $isDocumentsActive = (bool)preg_match('#/pages/(view-|create-|edit-|readymade|payments|archives|email-document)#', $scriptPath);
    $isToolsActive = (bool)preg_match('#/pages/(system-designer|create-proposal|roi-calculator)\.php$#', $scriptPath)
        || strpos($scriptPath, '/pages/products/equipment-datasheets.php') !== false;
    $isAdminActive = (bool)preg_match('#/pages/(users/(manage-users|manage-groups|create-user|edit-user|user-permissions|security-settings)|audit-log|settings|system-update|ai-settings)\.php$#', $scriptPath);
    $hrActive = strpos($scriptPath, '/modules/hr/') !== false || strpos(($currentPage ?? ''), 'hr_') === 0;
    $navButtonClass = function ($active) {
        return 'px-4 py-2 ' . ($active ? 'bg-blue-50 text-primary' : 'text-gray-700 hover:text-primary hover:bg-gray-50')
            . ' rounded-lg font-semibold flex items-center gap-1';
    };
    ?>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Tailwind Config -->
    <script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
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
            basePath: '<?php echo $base_path; ?>',
            vatRate: <?php echo defined('VAT_RATE') ? ((float)VAT_RATE / 100) : 0.075; ?>
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
    <div id="mobileMenuOverlay" class="mobile-menu-overlay" onclick="toggleMobileMenu()" aria-hidden="true"></div>

    <!-- Mobile Menu Drawer -->
    <div id="mobileMenu" class="mobile-menu" role="dialog" aria-modal="true" aria-label="Main menu" aria-hidden="true" inert>
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Menu</h2>
            <button type="button" onclick="toggleMobileMenu()" class="text-gray-500 hover:text-gray-700" aria-label="Close menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <?php if (isset($current_user)): ?>
            <nav class="space-y-1" aria-label="Mobile navigation">
                <a href="<?php echo $base_path; ?>/dashboard.php"
                    class="block px-4 py-3 <?php echo $isDashboard ? 'bg-blue-50 text-primary font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?> rounded-lg font-semibold">
                    📊 Dashboard
                </a>

                <?php if ($canSales): ?>
                <!-- Sales Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Sales</p>
                    <?php if ($canPerm('manage_customers')): ?>
                    <a href="<?php echo $base_path; ?>/pages/customers/manage-customers.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        👥 Customers
                    </a>
                    <?php endif; ?>
                    <?php if ($canPerm('manage_leads')): ?>
                    <a href="<?php echo $base_path; ?>/pages/leads/manage-leads.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🎯 Leads
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($canInventory): ?>
                <!-- Inventory Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Inventory</p>
                    <?php if ($canPerm('manage_products')): ?>
                    <p class="px-4 pt-1 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Catalog</p>
                    <a href="<?php echo $base_path; ?>/pages/products/manage-products.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📦 Products
                    </a>
                    <?php endif; ?>
                    <?php if ($canPerm('manage_store') || $canPerm('manage_accessories')): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Stock</p>
                    <?php if ($canPerm('manage_store')): ?>
                    <a href="<?php echo $base_path; ?>/pages/store/items.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🏬 Store Items
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/store/categories.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📂 Categories
                    </a>
                    <?php endif; ?>
                    <?php if ($canPerm('manage_accessories')): ?>
                    <a href="<?php echo $base_path; ?>/pages/accessories/items.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🧰 Accessories
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Documents Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Documents</p>
                    <p class="px-4 pt-1 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Sales documents</p>
                    <a href="<?php echo $base_path; ?>/pages/view-quotes.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📄 Quotes
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/readymade-quotes.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        ⚡ Ready-Made Quotes
                    </a>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Finance</p>
                    <a href="<?php echo $base_path; ?>/pages/view-invoices.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📋 Invoices
                    </a>
                    <a href="<?php echo $base_path; ?>/pages/view-receipts.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💰 Receipts
                    </a>
                    <?php if ($canPerm('view_payments')): ?>
                    <a href="<?php echo $base_path; ?>/pages/payments/manage-payments.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💳 Payments
                    </a>
                    <?php endif; ?>
                    <?php if ($canPerm('view_reports')): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Records</p>
                    <a href="<?php echo $base_path; ?>/pages/archives.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📦 Archives
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Tools Section -->
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Tools</p>
                    <p class="px-4 pt-1 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Design &amp; Propose</p>
                    <a href="<?php echo $base_path; ?>/pages/system-designer.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        🛠️ System Designer
                    </a>
                    <?php if ($canProposal): ?>
                    <a href="<?php echo $base_path; ?>/pages/create-proposal.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        ✨ Proposal Creator
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo $base_path; ?>/pages/roi-calculator.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        💰 ROI Calculator
                    </a>
                    <?php if ($canPerm('manage_products')): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Data</p>
                    <a href="<?php echo $base_path; ?>/pages/products/equipment-datasheets.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        📋 Datasheet Catalog
                    </a>
                    <?php endif; ?>
                </div>

                <!-- HR Section -->
                <?php $hrActive = isset($currentPage) && strpos($currentPage, 'hr_') === 0; ?>
                <?php if ($showHrMenu): ?>
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">HR Management</p>
                    <?php if ($canHrView): ?>
                    <p class="px-4 pt-1 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Overview</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/dashboard.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_dashboard'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        📊 HR Dashboard
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/employees.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_employees'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        👥 Employees
                    </a>
                    <?php endif; ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Time &amp; Attendance</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/attendance.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_attendance'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🕒 Attendance
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/leave.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_leave'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        📅 Leave
                    </a>
                    <?php if ($canHrManage): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">People Operations</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/departments.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_departments'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🏢 Departments
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/voting.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_voting'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🗳️ Staff Voting
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/documents.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_documents'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        📄 HR Documents
                    </a>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">ID Cards</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-maker.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idcards'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🎨 ID Card Templates
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-produce.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idproduce'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🪪 Produce ID Cards
                    </a>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/id-auto.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_idauto'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        ⚡ Bulk ID Production
                    </a>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Onboarding</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/onboarding-admin.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_onboarding'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🚀 Onboarding
                    </a>
                    <?php endif; ?>
                    <?php if ($canPayrollView): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Payroll</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/payroll.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_payroll'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        💰 Payroll
                    </a>
                    <?php endif; ?>
                    <?php if ($canRecruit): ?>
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Recruitment</p>
                    <a href="<?php echo $base_path; ?>/modules/hr/pages/recruitment.php"
                        class="block px-4 py-2 rounded-lg <?php echo ($currentPage ?? '')==='hr_recruitment'?'bg-blue-50 text-primary font-semibold':'text-gray-700 hover:bg-blue-50 hover:text-primary'; ?>">
                        🧑‍💼 Recruitment
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($canAdminMenu): ?>
                    <!-- Admin Section -->
                    <div class="border-t border-gray-200 pt-2 mt-2">
                        <p class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Admin</p>
                        <?php if ($canPerm('manage_users') || $canPerm('manage_access') || (function_exists('isSuperAdmin') && isSuperAdmin())): ?>
                        <p class="px-4 pt-1 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Access</p>
                        <?php if ($canPerm('manage_users')): ?>
                        <a href="<?php echo $base_path; ?>/pages/users/manage-users.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            👤 Manage Users
                        </a>
                        <?php endif; ?>
                        <?php if ($canPerm('manage_access')): ?>
                        <a href="<?php echo $base_path; ?>/pages/users/manage-groups.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            🛡️ Groups & Permissions
                        </a>
                        <?php endif; ?>
                        <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
                            <a href="<?php echo $base_path; ?>/pages/users/security-settings.php"
                                class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                                🔐 Security Settings
                            </a>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canPerm('manage_settings') || $canPerm('view_audit_log')): ?>
                        <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">System</p>
                        <?php if ($canPerm('manage_settings')): ?>
                        <a href="<?php echo $base_path; ?>/pages/settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            ⚙️ Settings
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/system-update.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            🔧 System Update
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/ai-settings.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg flex items-center gap-2">
                            <img src="<?php echo $base_path; ?>/assets/icons/magic.png" class="w-4 h-4" alt="">
                            AI Settings
                        </a>
                        <?php endif; ?>
                        <?php if ($canPerm('view_audit_log')): ?>
                        <a href="<?php echo $base_path; ?>/pages/audit-log.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            📊 Audit Log
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
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
                    <?php if (!function_exists('mfaGloballyEnabled') || mfaGloballyEnabled()): ?>
                        <a href="<?php echo $base_path; ?>/pages/users/security-mfa.php"
                            class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                            📱 Two-Factor Auth
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $base_path; ?>/pages/about.php"
                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-lg">
                        ℹ️ About
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
                    <nav class="hidden md:flex items-center gap-1" aria-label="Main navigation">
                        <a href="<?php echo $base_path; ?>/dashboard.php"
                            class="px-4 py-2 <?php echo $isDashboard ? 'bg-blue-50 text-primary' : 'text-gray-700 hover:text-primary hover:bg-gray-50'; ?> rounded-lg font-semibold transition-colors"<?php echo $isDashboard ? ' aria-current="page"' : ''; ?>>Dashboard</a>

                        <?php if ($canSales): ?>
                        <!-- Sales Dropdown -->
                        <div class="relative group dropdown">
                            <button class="<?php echo $navButtonClass($isSalesActive); ?>" aria-haspopup="true" aria-expanded="false">
                                Sales
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="absolute left-0 mt-1 w-56 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <?php if ($canPerm('manage_customers')): ?>
                                <a href="<?php echo $base_path; ?>/pages/customers/manage-customers.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-t-lg">
                                    <div class="font-semibold">Customers</div>
                                    <div class="text-xs text-gray-500">Manage clients</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canPerm('manage_leads')): ?>
                                <a href="<?php echo $base_path; ?>/pages/leads/manage-leads.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">🎯 Leads</div>
                                    <div class="text-xs text-gray-500">Capture &amp; follow-up</div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($canInventory): ?>
                        <!-- Inventory Dropdown -->
                        <div class="relative group dropdown">
                            <button class="<?php echo $navButtonClass($isInventoryActive); ?>" aria-haspopup="true" aria-expanded="false">
                                Inventory
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="absolute left-0 mt-1 w-60 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <?php if ($canPerm('manage_products')): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Catalog</div>
                                <a href="<?php echo $base_path; ?>/pages/products/manage-products.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">Products</div>
                                    <div class="text-xs text-gray-500">Manage catalog</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canPerm('manage_store') || $canPerm('manage_accessories')): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Stock</div>
                                <?php if ($canPerm('manage_store')): ?>
                                <a href="<?php echo $base_path; ?>/pages/store/items.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">Store Items</div>
                                    <div class="text-xs text-gray-500">Inventory items</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/store/categories.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Categories</div>
                                    <div class="text-xs text-gray-500">Item groups</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canPerm('manage_accessories')): ?>
                                <a href="<?php echo $base_path; ?>/pages/accessories/items.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                    <div class="font-semibold">Accessories</div>
                                    <div class="text-xs text-gray-500">Internal tools &amp; consumables</div>
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Documents Dropdown -->
                        <div class="relative group dropdown">
                            <button class="<?php echo $navButtonClass($isDocumentsActive); ?>" aria-haspopup="true" aria-expanded="false">
                                Documents
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="absolute left-0 mt-1 w-60 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Sales documents</div>
                                <a href="<?php echo $base_path; ?>/pages/view-quotes.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">Quotes</div>
                                    <div class="text-xs text-gray-500">View all quotes</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/readymade-quotes.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Ready-Made Quotes</div>
                                    <div class="text-xs text-gray-500">Quick templates</div>
                                </a>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Finance</div>
                                <a href="<?php echo $base_path; ?>/pages/view-invoices.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">Invoices</div>
                                    <div class="text-xs text-gray-500">View all invoices</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/pages/view-receipts.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Receipts</div>
                                    <div class="text-xs text-gray-500">Payment receipts</div>
                                </a>
                                <?php if ($canPerm('view_payments')): ?>
                                <a href="<?php echo $base_path; ?>/pages/payments/manage-payments.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">Payments</div>
                                    <div class="text-xs text-gray-500">Track allocations</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canPerm('view_reports')): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Records</div>
                                <a href="<?php echo $base_path; ?>/pages/archives.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-b-lg">
                                    <div class="font-semibold">Archives</div>
                                    <div class="text-xs text-gray-500">View archived docs</div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tools Dropdown -->
                        <div class="relative group dropdown">
                            <button class="<?php echo $navButtonClass($isToolsActive); ?>" aria-haspopup="true" aria-expanded="false">
                                Tools
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-60 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Design &amp; Propose</div>
                                <a href="<?php echo $base_path; ?>/pages/system-designer.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">🛠️ System Designer</div>
                                    <div class="text-xs text-gray-500">Plan panels, wiring and breakers; export PDF</div>
                                </a>
                                <?php if ($canProposal): ?>
                                <a href="<?php echo $base_path; ?>/pages/create-proposal.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">✨ Proposal Creator</div>
                                    <div class="text-xs text-gray-500">Draft an AI proposal and convert to a quote</div>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo $base_path; ?>/pages/roi-calculator.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">💰 ROI Calculator</div>
                                    <div class="text-xs text-gray-500">Estimate savings and payback</div>
                                </a>
                                <?php if ($canPerm('manage_products')): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Data</div>
                                <a href="<?php echo $base_path; ?>/pages/products/equipment-datasheets.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-b-lg">
                                    <div class="font-semibold">📋 Datasheet Catalog</div>
                                    <div class="text-xs text-gray-500">Inverter, battery and panel specs</div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- HR Dropdown -->
                        <?php if ($showHrMenu): ?>
                        <div class="relative group dropdown">
                            <button class="<?php echo $navButtonClass($hrActive); ?>" aria-haspopup="true" aria-expanded="false">
                                HR
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute left-0 mt-1 w-60 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <?php if ($canHrView): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Overview</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/dashboard.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">📊 HR Dashboard</div>
                                    <div class="text-xs text-gray-500">Overview & Stats</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/employees.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">👥 Employees</div>
                                    <div class="text-xs text-gray-500">Manage Staff</div>
                                </a>
                                <?php endif; ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Time &amp; Attendance</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/attendance.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">🕒 Attendance</div>
                                    <div class="text-xs text-gray-500">Track Time</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/leave.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">📅 Leave</div>
                                    <div class="text-xs text-gray-500">Requests & Status</div>
                                </a>
                                <?php if ($canHrManage): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">People Operations</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/departments.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">🏢 Departments</div>
                                    <div class="text-xs text-gray-500">Teams & Job Titles</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/voting.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">🗳️ Staff Voting</div>
                                    <div class="text-xs text-gray-500">Vote for Colleague</div>
                                </a>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/documents.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                    <div class="font-semibold">📄 HR Documents</div>
                                    <div class="text-xs text-gray-500">Generate letters</div>
                                </a>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">ID Cards</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/id-maker.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">🎨 ID Card Templates</div>
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
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Onboarding</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/onboarding-admin.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">🚀 Onboarding</div>
                                    <div class="text-xs text-gray-500">Manage Signups</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canPayrollView): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Payroll</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/payroll.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                    <div class="font-semibold">💰 Payroll</div>
                                    <div class="text-xs text-gray-500">Salaries & Payslips</div>
                                </a>
                                <?php endif; ?>
                                <?php if ($canRecruit): ?>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">Recruitment</div>
                                <a href="<?php echo $base_path; ?>/modules/hr/pages/recruitment.php"
                                    class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary rounded-b-lg">
                                    <div class="font-semibold">🧑‍💼 Recruitment</div>
                                    <div class="text-xs text-gray-500">Manage vacancies</div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($canAdminMenu): ?>
                            <!-- Admin Dropdown -->
                            <div class="relative group dropdown">
                                <button class="<?php echo $navButtonClass($isAdminActive); ?>" aria-haspopup="true" aria-expanded="false">
                                    Admin
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div
                                    class="absolute right-0 mt-1 w-60 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                    <?php if ($canPerm('manage_users') || $canPerm('manage_access') || (function_exists('isSuperAdmin') && isSuperAdmin())): ?>
                                    <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Access</div>
                                    <?php if ($canPerm('manage_users')): ?>
                                    <a href="<?php echo $base_path; ?>/pages/users/manage-users.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                        <div class="font-semibold">Manage Users</div>
                                        <div class="text-xs text-gray-500">Users &amp; roles</div>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($canPerm('manage_access')): ?>
                                    <a href="<?php echo $base_path; ?>/pages/users/manage-groups.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">Groups &amp; Permissions</div>
                                        <div class="text-xs text-gray-500">Access control</div>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
                                        <a href="<?php echo $base_path; ?>/pages/users/security-settings.php"
                                            class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                            <div class="font-semibold">🔐 Security Settings</div>
                                            <div class="text-xs text-gray-500">2FA, invites, passwords</div>
                                        </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($canPerm('manage_settings') || $canPerm('view_audit_log')): ?>
                                    <div class="px-4 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide border-t">System</div>
                                    <?php if ($canPerm('manage_settings')): ?>
                                    <a href="<?php echo $base_path; ?>/pages/settings.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                                        <div class="font-semibold">Settings</div>
                                        <div class="text-xs text-gray-500">System config</div>
                                    </a>
                                    <a href="<?php echo $base_path; ?>/pages/system-update.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">System Update</div>
                                        <div class="text-xs text-gray-500">Patches &amp; maintenance</div>
                                    </a>
                                    <a href="<?php echo $base_path; ?>/pages/ai-settings.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t">
                                        <div class="font-semibold">AI Settings</div>
                                        <div class="text-xs text-gray-500">Configure AI features</div>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($canPerm('view_audit_log')): ?>
                                    <a href="<?php echo $base_path; ?>/pages/audit-log.php"
                                        class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-primary border-t rounded-b-lg">
                                        <div class="font-semibold">Audit Log</div>
                                        <div class="text-xs text-gray-500">Activity history</div>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <span class="text-gray-300">|</span>

                        <!-- User Menu -->
                        <div class="relative group dropdown">
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
                                class="absolute right-0 mt-2 w-56 dropdown-panel bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
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
                                <?php if (!function_exists('mfaGloballyEnabled') || mfaGloballyEnabled()): ?>
                                    <a href="<?php echo $base_path; ?>/pages/users/security-mfa.php"
                                        class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                        📱 Two-Factor Auth
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo $base_path; ?>/pages/about.php"
                                    class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                    ℹ️ About
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
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden hamburger" aria-label="Toggle menu" aria-controls="mobileMenu" aria-expanded="false">
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

        .dropdown-panel {
            max-height: 70vh;
            overflow-y: auto;
        }

        .dropdown.open > .dropdown-panel {
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 640px) {
            .dropdown-panel {
                max-height: 60vh;
            }
        }
    </style>

    <script nonce="<?php echo function_exists('cspNonce') ? cspNonce() : ''; ?>">
        (function () {
            const dropdowns = Array.from(document.querySelectorAll('.dropdown'));
            const closeAll = (except) => {
                dropdowns.forEach((d) => {
                    if (d === except) return;
                    d.classList.remove('open');
                    const b = d.querySelector('button');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
            };
            dropdowns.forEach((d) => {
                const btn = d.querySelector('button');
                if (!btn) return;
                btn.setAttribute('aria-haspopup', 'true');
                btn.setAttribute('aria-expanded', d.classList.contains('open') ? 'true' : 'false');
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const open = d.classList.contains('open');
                    closeAll();
                    if (!open) {
                        d.classList.add('open');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                });
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) closeAll();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                const open = document.querySelector('.dropdown.open');
                closeAll();
                if (open) {
                    const b = open.querySelector('button');
                    if (b) b.focus();
                }
            });
        })();
    </script>

    <!-- Chat Widget -->
    <?php include_once __DIR__ . '/chat-widget.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
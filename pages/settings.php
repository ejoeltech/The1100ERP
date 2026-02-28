<?php
include '../includes/session-check.php';

// Only admins can access settings
// Note: requirePermission() always exists (defined as fallback in session-check.php)
requirePermission('manage_settings');

$pageTitle = 'System Settings - ERP System';

// Fetch current settings from database
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // Settings table might not exist yet
    $settings_rows = [];
}

// Helper function to get setting value is now in config.php

include '../includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900">System Settings</h2>
    <p class="text-gray-600 mt-1">Configure your ERP system preferences and business information</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-800 font-semibold">✓ Settings saved successfully!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800 text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md">
    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="flex flex-wrap -mb-px">
            <button onclick="switchTab('company')" id="tab-company"
                class="tab-button active px-6 py-4 text-sm font-semibold border-b-2 border-primary text-primary">
                Company Info
            </button>
            <button onclick="switchTab('email')" id="tab-email"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Email Settings
            </button>
            <button onclick="switchTab('system')" id="tab-system"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                System
            </button>
            <button onclick="switchTab('bank')" id="tab-bank"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Bank Accounts
            </button>
            <button onclick="switchTab('display')" id="tab-display"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Display
            </button>
            <button onclick="switchTab('integrations')" id="tab-integrations"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Integrations (API)
            </button>
            <button onclick="switchTab('audit')" id="tab-audit"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Audit Settings
            </button>
            <button onclick="switchTab('appendices')" id="tab-appendices"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Quote Appendices
            </button>
            <button onclick="switchTab('maintenance')" id="tab-maintenance"
                class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
                Maintenance
            </button>
        </nav>
    </div>

    <form id="settingsForm" method="POST" action="../api/save-settings.php" class="p-8" enctype="multipart/form-data">

        <!-- Company Info Tab -->
        <div id="content-company" class="tab-content">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Company Information</h3>

            <div class="space-y-6 max-w-2xl">
                <!-- Logo Upload Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Company Logo</h4>

                    <?php
                    $current_logo = getSetting('company_logo', '');
                    $logo_path = $current_logo ? '../' . $current_logo : '';
                    ?>

                    <?php if ($current_logo && file_exists(__DIR__ . '/' . $logo_path)): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-2">Current Logo:</p>
                            <img src="<?php echo $logo_path; ?>" alt="Company Logo"
                                class="h-20 object-contain bg-white p-2 rounded border border-gray-300">
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Upload New Logo
                        </label>
                        <input type="file" name="company_logo" accept="image/png,image/jpeg,image/jpg,image/gif"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            onchange="previewLogo(this)">
                        <p class="text-xs text-gray-500 mt-1">
                            Recommended: PNG or JPG, max 3MB. Will be auto-resized and used as favicon.
                        </p>

                        <!-- Preview -->
                        <div id="logoPreview" class="mt-3 hidden">
                            <p class="text-sm text-gray-600 mb-2">Preview:</p>
                            <img id="logoPreviewImg" src="" alt="Logo Preview"
                                class="h-20 object-contain bg-white p-2 rounded border border-gray-300">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Company Name</label>
                    <input type="text" name="company_name"
                        value="<?php echo htmlspecialchars(getSetting('company_name', 'Your Company Name')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Business Address</label>
                    <textarea name="company_address" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"><?php echo htmlspecialchars(getSetting('company_address', 'Your Company Address, City, State/Province, Country')); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="company_phone"
                            value="<?php echo htmlspecialchars(getSetting('company_phone', '+1234567890')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="company_email"
                            value="<?php echo htmlspecialchars(getSetting('company_email', 'contact@yourcompany.com')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Website</label>
                    <input type="url" name="company_website"
                        value="<?php echo htmlspecialchars(getSetting('company_website', 'www.yourcompany.com')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tax/VAT Registration Number
                        (Optional)</label>
                    <input type="text" name="company_tax_id"
                        value="<?php echo htmlspecialchars(getSetting('company_tax_id', '')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
        </div>

        <!-- Email Settings Tab -->
        <div id="content-email" class="tab-content hidden">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Email Configuration</h3>

            <div class="space-y-6 max-w-2xl">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Method</label>
                    <select name="email_method"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="php_mail" <?php echo getSetting('email_method', 'php_mail') === 'php_mail' ? 'selected' : ''; ?>>PHP mail() - Simple (Default)</option>
                        <option value="smtp" <?php echo getSetting('email_method') === 'smtp' ? 'selected' : ''; ?>>SMTP -
                            Advanced (Recommended for Production)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">PHP mail() works for testing. Use SMTP for production.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Email</label>
                        <input type="email" name="email_from_address"
                            value="<?php echo htmlspecialchars(getSetting('email_from_address', 'noreply@yourcompany.com')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Name</label>
                        <input type="text" name="email_from_name"
                            value="<?php echo htmlspecialchars(getSetting('email_from_name', 'Your Company Name')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h4 class="font-semibold text-gray-900 mb-4">SMTP Configuration (If using SMTP)</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Host</label>
                            <input type="text" name="smtp_host"
                                value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.gmail.com')); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="smtp.gmail.com">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Port</label>
                            <input type="number" name="smtp_port"
                                value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="587">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Username</label>
                            <input type="text" name="smtp_username"
                                value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="your-email@gmail.com">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Password</label>
                            <input type="password" name="smtp_password"
                                value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="App password or account password">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Encryption</label>
                        <select name="smtp_encryption"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo getSetting('smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>
                                SSL</option>
                        </select>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-2">📧 Gmail SMTP Setup:</h4>
                    <ol class="text-sm text-gray-700 space-y-1 ml-4 list-decimal">
                        <li>Enable 2-Step Verification on your Gmail</li>
                        <li>Go to Google Account → Security → App Passwords</li>
                        <li>Generate an App Password for "Mail"</li>
                        <li>Use that as your SMTP password here</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- System Settings Tab -->
        <div id="content-system" class="tab-content hidden">
            <h3 class="text-xl font-bold text-gray-900 mb-6">System Configuration</h3>

            <div class="space-y-6 max-w-2xl">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">VAT Rate (%)</label>
                    <input type="number" step="0.01" name="vat_rate"
                        value="<?php echo htmlspecialchars(getSetting('vat_rate', '7.5')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">Current Nigerian VAT is 7.5%</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Quote Prefix</label>
                        <input type="text" name="quote_prefix"
                            value="<?php echo htmlspecialchars(getSetting('quote_prefix', 'QUOT-')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix"
                            value="<?php echo htmlspecialchars(getSetting('invoice_prefix', 'INV-')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Receipt Prefix</label>
                        <input type="text" name="receipt_prefix"
                            value="<?php echo htmlspecialchars(getSetting('receipt_prefix', 'REC-')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Currency Symbol</label>
                    <input type="text" name="currency_symbol"
                        value="<?php echo htmlspecialchars(getSetting('currency_symbol', '₦')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">TinyMCE API Key</label>
                    <input type="text" name="tinymce_api_key"
                        value="<?php echo htmlspecialchars(getSetting('tinymce_api_key', 'no-api-key')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">Get your free key at <a href="https://www.tiny.cloud/"
                            target="_blank" class="text-blue-600 hover:underline">tiny.cloud</a> to remove the warning.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date Format</label>
                    <select name="date_format"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="d/m/Y" <?php echo getSetting('date_format', 'd/m/Y') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (31/12/2024)</option>
                        <option value="m/d/Y" <?php echo getSetting('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>
                            MM/DD/YYYY (12/31/2024)</option>
                        <option value="Y-m-d" <?php echo getSetting('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>
                            YYYY-MM-DD (2024-12-31)</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="auto_archive_days" value="90" <?php echo getSetting('auto_archive_days') ? 'checked' : ''; ?>
                            class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                        <span class="text-sm font-semibold text-gray-700">Auto-archive documents after 90 days</span>
                    </label>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="mt-12 pt-8 border-t border-red-200">
                <h4 class="text-xl font-bold text-red-600 mb-4">Danger Zone</h4>
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h5 class="font-bold text-gray-900 mb-2">Factory Reset</h5>
                    <p class="text-sm text-gray-700 mb-4">
                        This action will <strong>permanently delete all data</strong>, including users, documents,
                        settings, and logs.
                        The application will be reset to its initial state, ready for a fresh installation.
                        <strong>This cannot be undone.</strong>
                    </p>
                    <button type="button" onclick="showResetModal()"
                        class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold transition-colors">
                        Reset Application
                    </button>
                </div>
            </div>
        </div>

        <!-- Bank Accounts Tab -->
        <div id="content-bank" class="tab-content hidden">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Bank Account Details</h3>
                    <p class="text-gray-600 mt-1">Manage your bank accounts. Select at least 3 to display on documents.
                    </p>
                </div>
                <button type="button" onclick="showAddBankModal()"
                    class="w-full md:w-auto px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Bank Account
                </button>
            </div>

            <?php
            $bank_accounts = getAllBankAccounts();
            $selected_count = getSelectedBankAccountsCount();
            ?>

            <?php if ($selected_count < 3): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800 font-semibold">
                        ⚠️ You need to select at least 3 bank accounts to display on documents. Currently selected:
                        <?= $selected_count ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($bank_accounts)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                        </path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No bank accounts yet</h3>
                    <p class="text-gray-600 mb-4">Add your first bank account to display on documents</p>
                    <button type="button" onclick="showAddBankModal()"
                        class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                        Add Bank Account
                    </button>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($bank_accounts as $account): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow"
                            id="bank-<?= $account['id'] ?>">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <input type="checkbox" <?= $account['show_on_documents'] ? 'checked' : '' ?>
                                            onchange="toggleBankDisplay(<?= $account['id'] ?>)"
                                            id="bank-check-<?= $account['id'] ?>"
                                            class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary cursor-pointer">
                                        <label for="bank-check-<?= $account['id'] ?>" class="cursor-pointer">
                                            <h4 class="text-lg font-bold text-gray-900">
                                                <?= htmlspecialchars($account['bank_name']) ?>
                                            </h4>
                                        </label>
                                        <?php if ($account['show_on_documents']): ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                                Showing on documents
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                        <p class="text-gray-700"><strong>Account Number:</strong>
                                            <?= htmlspecialchars($account['account_number']) ?></p>
                                        <p class="text-gray-600"><strong>Account Name:</strong>
                                            <?= htmlspecialchars($account['account_name']) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick='editBank(<?= json_encode($account) ?>)'
                                        class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 font-semibold text-sm">
                                        Edit
                                    </button>
                                    <button type="button"
                                        onclick="deleteBank(<?= $account['id'] ?>, '<?= htmlspecialchars($account['bank_name'], ENT_QUOTES) ?>')"
                                        class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-semibold text-sm">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Display Settings Tab -->
        <div id="content-display" class="tab-content hidden">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Display Preferences</h3>

            <div class="space-y-6 max-w-2xl">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Items Per Page</label>
                    <select name="items_per_page"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="10" <?php echo getSetting('items_per_page', '25') === '10' ? 'selected' : ''; ?>>10
                        </option>
                        <option value="25" <?php echo getSetting('items_per_page', '25') === '25' ? 'selected' : ''; ?>>25
                            (Default)</option>
                        <option value="50" <?php echo getSetting('items_per_page', '25') === '50' ? 'selected' : ''; ?>>50
                        </option>
                        <option value="100" <?php echo getSetting('items_per_page', '25') === '100' ? 'selected' : ''; ?>>
                            100</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_dashboard_charts" value="1" <?php echo getSetting('show_dashboard_charts', '1') ? 'checked' : ''; ?>
                            class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                        <span class="text-sm font-semibold text-gray-700">Show charts on dashboard</span>
                    </label>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_recent_activity" value="1" <?php echo getSetting('show_recent_activity', '1') ? 'checked' : ''; ?>
                            class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                        <span class="text-sm font-semibold text-gray-700">Show recent activity on dashboard</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">PDF Export Quality</label>
                    <select name="pdf_quality"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="standard" <?php echo getSetting('pdf_quality', 'high') === 'standard' ? 'selected' : ''; ?>>Standard (Faster)</option>
                        <option value="high" <?php echo getSetting('pdf_quality', 'high') === 'high' ? 'selected' : ''; ?>>High (Default)</option>
                    </select>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-6 mt-6">
                <h4 class="font-semibold text-gray-900 mb-4">Document Styling</h4>
                <p class="text-sm text-gray-600 mb-4">Customize the look of your Quotes, Invoices, and Receipts.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Theme Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="theme_color"
                                value="<?php echo htmlspecialchars(getSetting('theme_color', '#0076BE')); ?>"
                                class="h-10 w-20 border border-gray-300 rounded cursor-pointer">
                            <span class="text-sm text-gray-500">Pick a primary brand color</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Footer Text</label>
                        <textarea name="footer_text" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="e.g. We appreciate your business!"><?php echo htmlspecialchars(getSetting('footer_text', 'We appreciate your business! Thank you')); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Appears at the bottom of all PDF documents</p>
                    </div>
                </div>
            </div>
        </div>
</div>

<!-- Audit Settings Tab -->
<div id="content-audit" class="tab-content hidden">
    <h3 class="text-xl font-bold text-gray-900 mb-6">Audit Log Settings</h3>
    <p class="text-gray-600 mb-6">Configure what gets logged and manage audit log retention</p>

    <div class="space-y-8 max-w-3xl">
        <!-- Quick Actions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-semibold text-gray-900 mb-4">📊 Audit Log Actions</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="audit-log.php"
                    class="block px-4 py-3 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 font-semibold">
                    View Audit Log
                </a>
                <button type="button" onclick="exportAuditLog()"
                    class="px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                    Export to CSV
                </button>
                <button type="button" onclick="confirmClearLogs()"
                    class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                    Clear Old Logs
                </button>
            </div>
        </div>

        <!-- Log Retention -->
        <div>
            <h4 class="font-semibold text-gray-900 mb-4">⏱️ Log Retention Period</h4>
            <p class="text-sm text-gray-600 mb-4">Automatically delete audit logs older than this period</p>

            <select name="audit_retention_days"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="30" <?php echo getSetting('audit_retention_days', '90') === '30' ? 'selected' : ''; ?>>30
                    days</option>
                <option value="60" <?php echo getSetting('audit_retention_days', '90') === '60' ? 'selected' : ''; ?>>60
                    days</option>
                <option value="90" <?php echo getSetting('audit_retention_days', '90') === '90' ? 'selected' : ''; ?>>90
                    days (Recommended)</option>
                <option value="180" <?php echo getSetting('audit_retention_days', '90') === '180' ? 'selected' : ''; ?>>
                    180 days (6 months)</option>
                <option value="365" <?php echo getSetting('audit_retention_days', '90') === '365' ? 'selected' : ''; ?>>
                    365 days (1 year)</option>
                <option value="0" <?php echo getSetting('audit_retention_days', '90') === '0' ? 'selected' : ''; ?>>Never
                    delete (Not recommended)</option>
            </select>
        </div>

        <!-- What to Log -->
        <div>
            <h4 class="font-semibold text-gray-900 mb-4">📝 What to Log</h4>
            <p class="text-sm text-gray-600 mb-4">Select which activities should be tracked in the audit log</p>

            <div class="space-y-3">
                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_user_actions" value="1" <?php echo getSetting('log_user_actions', '1') ? 'checked' : ''; ?> class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">User Login/Logout</span>
                        <p class="text-xs text-gray-600">Track when users log in and out of the system</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_document_create" value="1" <?php echo getSetting('log_document_create', '1') ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">Document Creation</span>
                        <p class="text-xs text-gray-600">Log when quotes, invoices, and receipts are created</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_document_edit" value="1" <?php echo getSetting('log_document_edit', '1') ? 'checked' : ''; ?> class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">Document Edits</span>
                        <p class="text-xs text-gray-600">Track modifications to existing documents</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_document_delete" value="1" <?php echo getSetting('log_document_delete', '1') ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">Document Deletion/Archive</span>
                        <p class="text-xs text-gray-600">Record when documents are deleted or archived</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_user_management" value="1" <?php echo getSetting('log_user_management', '1') ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">User Management</span>
                        <p class="text-xs text-gray-600">Log user creation, updates, and permission changes</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_settings_changes" value="1" <?php echo getSetting('log_settings_changes', '1') ? 'checked' : ''; ?>
                        class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">Settings Changes</span>
                        <p class="text-xs text-gray-600">Track when system settings are modified</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="log_email_sent" value="1" <?php echo getSetting('log_email_sent', '1') ? 'checked' : ''; ?> class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                    <div>
                        <span class="font-semibold text-gray-900">Email Sending</span>
                        <p class="text-xs text-gray-600">Log when documents are emailed to customers</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Current Statistics -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <h4 class="font-semibold text-gray-900 mb-4">📈 Current Statistics</h4>
            <?php
            try {
                $totalLogs = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
                $oldestLog = $pdo->query("SELECT MIN(created_at) FROM audit_log")->fetchColumn();
                $newestLog = $pdo->query("SELECT MAX(created_at) FROM audit_log")->fetchColumn();
                $totalSize = $pdo->query("SELECT SUM(LENGTH(details)) FROM audit_log")->fetchColumn();
            } catch (Exception $e) {
                $totalLogs = 0;
                $oldestLog = null;
                $newestLog = null;
                $totalSize = 0;
            }
            ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Total Log Entries</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalLogs); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Approximate Size</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo round($totalSize / 1024, 2); ?> KB</p>
                </div>
                <?php if ($oldestLog): ?>
                    <div>
                        <p class="text-sm text-gray-600">Oldest Entry</p>
                        <p class="text-sm font-semibold text-gray-900"><?php echo date('Y-m-d', strtotime($oldestLog)); ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if ($newestLog): ?>
                    <div>
                        <p class="text-sm text-gray-600">Latest Entry</p>
                        <p class="text-sm font-semibold text-gray-900"><?php echo date('Y-m-d', strtotime($newestLog)); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Warning -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm text-yellow-800">
                <strong>⚠️ Important:</strong> Audit logs are critical for security and compliance.
                Only disable logging if absolutely necessary. Clearing logs is irreversible.
            </p>
        </div>
    </div>
</div>
</div>
</div>

<!-- Quote Appendices Tab -->
<div id="content-appendices" class="tab-content hidden">
    <h3 class="text-xl font-bold text-gray-900 mb-6">Quote Appendices</h3>
    <p class="text-gray-600 mb-6">Content added here will be appended as separate pages to the end of every PDF Quote.
    </p>

    <div class="space-y-8 max-w-4xl">
        <div>
            <label class="block text-lg font-semibold text-gray-900 mb-2">Terms & Conditions</label>
            <p class="text-sm text-gray-500 mb-2">Appears on a new page after the quote.</p>
            <textarea name="quote_terms" id="quote_terms" rows="10"
                class="w-full border border-gray-300 rounded-lg"><?php echo htmlspecialchars(getSetting('quote_terms', '')); ?></textarea>
        </div>

        <div>
            <label class="block text-lg font-semibold text-gray-900 mb-2">Warranty Information</label>
            <p class="text-sm text-gray-500 mb-2">Appears on the last page.</p>
            <textarea name="quote_warranty" id="quote_warranty" rows="10"
                class="w-full border border-gray-300 rounded-lg"><?php echo htmlspecialchars(getSetting('quote_warranty', '')); ?></textarea>
        </div>
    </div>
</div>

<!-- Integrations Tab -->
<div id="content-integrations" class="tab-content hidden">
    <h3 class="text-xl font-bold text-gray-900 mb-6">API Integrations</h3>

    <div class="space-y-6 max-w-2xl">
        <!-- AI Integration -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-purple-100 rounded-full p-2">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h4 class="font-semibold text-gray-900">Artificial Intelligence (AI)</h4>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Groq API Key
                </label>
                <div class="relative">
                    <input type="password" name="groq_api_key"
                        value="<?php echo htmlspecialchars(getSetting('groq_api_key', '')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary pr-10">
                    <button type="button" onclick="togglePasswordVisibility(this)"
                        class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Required for AI Quote Descriptions and Solar System Design.
                    <a href="https://console.groq.com" target="_blank" class="text-primary hover:underline">Get a free
                        key here</a>.
                </p>
            </div>

            <!-- Test Connection Area -->
            <div class="flex items-center gap-3 mt-3">
                <button type="button" onclick="testGroqConnection()" id="btn-test-groq"
                    class="px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Test Connection
                </button>
                <div id="groq-test-status" class="text-sm"></div>
            </div>

        </div>

        <script>
            function testGroqConnection() {
                const btn = document.getElementById('btn-test-groq');
                const status = document.getElementById('groq-test-status');
                const apiKey = document.querySelector('input[name="groq_api_key"]').value;

                if (!apiKey) {
                    status.innerHTML = '<span class="text-red-600 font-semibold">Please enter an API Key first</span>';
                    return;
                }

                // UI Loading State
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                status.innerHTML = '<span class="text-gray-600 animate-pulse">Testing connection...</span>';

                // API Call
                fetch('../api/test/test-ai-connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ api_key: apiKey })
                })
                    .then(res => res.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-75', 'cursor-not-allowed');

                        if (data.success) {
                            status.innerHTML = `<span class="text-green-600 font-bold">✓ Success! (${data.latency})</span>`;
                        } else {
                            status.innerHTML = `<span class="text-red-600 font-bold">✗ Failed: ${data.error}</span>`;
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-75', 'cursor-not-allowed');
                        status.innerHTML = '<span class="text-red-600 font-bold">✗ Network Error</span>';
                        console.error(err);
                    });
            }
        </script>


    </div>

    <script>
        function togglePasswordVisibility(btn) {
            const input = btn.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.classList.toggle('text-primary');
        }
    </script>
</div>

<!-- Maintenance Tab -->
<div id="content-maintenance" class="tab-content hidden">
    <h3 class="text-xl font-bold text-gray-900 mb-6">System Maintenance</h3>
    <p class="text-gray-600 mb-6">Perform backups, restore data, and manage system updates.</p>

    <div class="space-y-8 max-w-4xl">

        <!-- Backup & Restore -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                    </path>
                </svg>
                Backup & Restore
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Backup -->
                <div>
                    <h5 class="font-semibold text-gray-800 mb-2">System Backup</h5>
                    <p class="text-sm text-gray-600 mb-4">Download a backup of your data.</p>

                    <div class="mb-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="backupMedia" onchange="updateBackupLink()"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Include Media Files (Images, Uploads)</span>
                        </label>
                    </div>

                    <a href="../api/system/backup.php?type=db" id="backupBtn" target="_blank"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold shadow-sm text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download Backup
                    </a>

                    <script>
                        function updateBackupLink() {
                            const btn = document.getElementById('backupBtn');
                            const includeMedia = document.getElementById('backupMedia').checked;
                            btn.href = '../api/system/backup.php?type=' + (includeMedia ? 'full' : 'db');
                            btn.innerHTML = includeMedia ?
                                '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Download Full Backup (ZIP)' :
                                '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Download Database Only';
                        }
                    </script>
                </div>

                <!-- Restore -->
                <div class="border-l border-blue-200 pl-8">
                    <h5 class="font-semibold text-gray-800 mb-2">Restoration</h5>
                    <p class="text-sm text-gray-600 mb-4">Upload a SQL file to restore your database. <strong>Warning:
                            This overwrites all data.</strong></p>
                    <div class="mb-3 mt-2">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="restoreMedia" checked
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Include Media Files (from ZIP)</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <input type="file" id="restoreFile" accept=".sql,.zip" class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-100 file:text-blue-700
                                hover:file:bg-blue-200">
                        <button type="button" onclick="restoreDatabase()"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold text-sm">
                            Restore
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patches & Updates -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                System Updates
            </h4>

            <div class="mb-6">
                <h5 class="font-semibold text-gray-800 mb-2">Apply Update Patch</h5>
                <p class="text-sm text-gray-600 mb-4">Upload a valid <code>.zip</code> update package provided by the
                    developer.
                    <span class="block mt-1 text-xs text-blue-600">
                        Server Max Upload Size: <?php echo ini_get('upload_max_filesize'); ?>
                        (Post Max: <?php echo ini_get('post_max_size'); ?>)
                    </span>
                </p>
                <div class="flex gap-2 items-center">
                    <input type="file" id="patchFile" accept=".zip" class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-green-100 file:text-green-700
                            hover:file:bg-green-200">
                    <button type="button" onclick="applyPatch()"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold whitespace-nowrap">
                        Apply Update
                    </button>
                </div>
                <div id="patch-status" class="mt-2 text-sm font-mono"></div>
            </div>
        </div>

        <!-- Developer Tools -->
        <div class="bg-gray-100 border border-gray-200 rounded-lg p-6">
            <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                Developer Tools
            </h4>

            <div>
                <h5 class="font-semibold text-gray-800 mb-2">Create Update Package</h5>
                <p class="text-sm text-gray-600 mb-4">Generates a deployable <code>.zip</code> of the current system
                    (excludes local config/uploads).</p>

                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Changes in last:</label>
                        <div class="flex items-center gap-1">
                            <input type="number" id="patchHours" value="0" min="0" onchange="updatePatchLink()"
                                class="w-16 px-2 py-1 border border-gray-300 rounded text-sm text-center">
                            <span class="text-xs text-gray-600">hrs</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <input type="number" id="patchMinutes" value="10" min="0" onchange="updatePatchLink()"
                                class="w-16 px-2 py-1 border border-gray-300 rounded text-sm text-center">
                            <span class="text-xs text-gray-600">mins</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 p-3 bg-gray-50 rounded border border-gray-200">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="excludeVendor" onchange="updatePatchLink()"
                                class="rounded text-green-600 focus:ring-green-500">
                            Exclude Vendor (mpdf/libs)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="excludeSetup" onchange="updatePatchLink()"
                                class="rounded text-green-600 focus:ring-green-500">
                            Exclude Setup Folder
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="excludeDatabase" onchange="updatePatchLink()"
                                class="rounded text-green-600 focus:ring-green-500">
                            Exclude Database Scripts
                        </label>
                    </div>

                    <div class="flex gap-2">
                        <a href="../api/system/create-patch.php?hours=0&minutes=10" id="patchBtn"
                            class="inline-flex items-center px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 font-semibold shadow-sm text-sm whitespace-nowrap">
                            Generate Patch ZIP
                        </a>
                        <button type="button" onclick="setFullPatch()"
                            class="px-3 py-2 text-xs text-blue-600 hover:text-blue-800 font-semibold border border-blue-200 rounded hover:bg-blue-50">
                            Reset to Full System
                        </button>
                    </div>
                </div>

                <script>
                    function updatePatchLink() {
                        const h = document.getElementById('patchHours').value || 0;
                        const m = document.getElementById('patchMinutes').value || 0;

                        const excludeVendor = document.getElementById('excludeVendor').checked ? 1 : 0;
                        const excludeSetup = document.getElementById('excludeSetup').checked ? 1 : 0;
                        const excludeDatabase = document.getElementById('excludeDatabase').checked ? 1 : 0;

                        let url = `../api/system/create-patch.php?hours=${h}&minutes=${m}`;
                        if (excludeVendor) url += '&exclude_vendor=1';
                        if (excludeSetup) url += '&exclude_setup=1';
                        if (excludeDatabase) url += '&exclude_database=1';

                        document.getElementById('patchBtn').href = url;
                        document.getElementById('patchBtn').innerHTML = `Generate Patch (${h}h ${m}m)`;
                    }

                    function setFullPatch() {
                        document.getElementById('patchHours').value = '';
                        document.getElementById('patchMinutes').value = '';
                        document.getElementById('excludeVendor').checked = false;
                        document.getElementById('excludeSetup').checked = false;
                        document.getElementById('excludeDatabase').checked = false;
                        document.getElementById('patchBtn').href = '../api/system/create-patch.php?timeframe=full';
                        document.getElementById('patchBtn').innerHTML = 'Generate Full Patch';
                    }
                </script>

                <script>
                    function updatePatchLink() {
                        const timeframe = document.getElementById('patchTimeframe').value;
                        document.getElementById('patchBtn').href = '../api/system/create-patch.php?timeframe=' + timeframe;
                    }
                </script>
            </div>
        </div>

        <!-- System Doctor -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
            <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <img src="../assets/icons/doctor.png" class="w-6 h-6" alt="Doctor">
                System Doctor
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Log Analysis -->
                <div class="bg-white p-4 rounded-lg border border-purple-100 shadow-sm">
                    <h5 class="font-semibold text-gray-800 mb-2">Error Log Analysis</h5>
                    <p class="text-xs text-gray-600 mb-4">Reads server logs and attempts to diagnose root causes using
                        AI.</p>
                    <button onclick="runDiagnostics('logs')" id="btn-logs"
                        class="w-full px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition">
                        Run Log Analysis
                    </button>
                </div>

                <!-- DB Optimization -->
                <div class="bg-white p-4 rounded-lg border border-purple-100 shadow-sm">
                    <h5 class="font-semibold text-gray-800 mb-2">Database Optimizer</h5>
                    <p class="text-xs text-gray-600 mb-4">Scans schema for missing indexes and inefficiency.</p>
                    <button onclick="runDiagnostics('db')" id="btn-db"
                        class="w-full px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition">
                        Check Database Health
                    </button>
                </div>
            </div>

            <!-- Results Display -->
            <div id="diag-results" class="mt-4 hidden">
                <div
                    class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-xs overflow-x-auto whitespace-pre-wrap max-h-96">
                </div>
            </div>

            <script>
                function runDiagnostics(type) {
                    const btn = document.getElementById('btn-' + type);
                    const results = document.getElementById('diag-results');
                    const output = results.querySelector('div');

                    const endpoint = type === 'logs' ? '../api/system/analyze-logs.php' : '../api/system/optimize-db.php';

                    // Reset UI
                    btn.disabled = true;
                    btn.classList.add('opacity-75');
                    btn.innerText = 'Analyzing...';
                    results.classList.remove('hidden');
                    output.innerText = 'Connecting to AI...';

                    fetch(endpoint)
                        .then(res => res.json())
                        .then(data => {
                            if (data.error) {
                                output.innerText = 'Error: ' + data.error;
                            } else if (data.analysis) {
                                output.innerText = data.analysis;
                            } else {
                                output.innerText = JSON.stringify(data, null, 2);
                            }
                        })
                        .catch(err => {
                            output.innerText = 'Network/Server Error: ' + err.message;
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.classList.remove('opacity-75');
                            btn.innerText = type === 'logs' ? 'Run Log Analysis' : 'Check Database Health';
                        });
                }
            </script>
        </div>

    </div>

    <script>
        function restoreDatabase() {
            const fileInput = document.getElementById('restoreFile');
            if (!fileInput.files[0]) {
                alert('Please select a SQL file first.');
                return;
            }
            if (!confirm('CRITICAL WARNING: This will DELETE existing data and import the selected file. Are you absolutely sure?')) {
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('include_media', document.getElementById('restoreMedia').checked ? '1' : '0');

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Restoring...';
            btn.disabled = true;

            fetch('../api/system/restore.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Database restored successfully! The page will now reload.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    alert('Upload failed: ' + err.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function applyPatch() {
            const fileInput = document.getElementById('patchFile');
            if (!fileInput.files[0]) {
                alert('Please select a ZIP file first.');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            const btn = event.target;
            const statusDiv = document.getElementById('patch-status');
            btn.innerHTML = 'Applying...';
            btn.disabled = true;
            statusDiv.innerHTML = 'Uploading and extracting...';

            fetch('../api/system/apply-patch.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    btn.innerHTML = 'Apply Update';
                    btn.disabled = false;
                    if (data.success) {
                        statusDiv.innerHTML = '<span class="text-green-600">' + data.message + '</span>';
                        alert('Patch applied successfully!');
                    } else {
                        statusDiv.innerHTML = '<span class="text-red-600">Error: ' + data.message + '</span>';
                    }
                })
                .catch(err => {
                    btn.innerHTML = 'Apply Update';
                    btn.disabled = false;
                    statusDiv.innerHTML = '<span class="text-red-600">Request failed: ' + err.message + '</span>';
                });
        }
    </script>
</div>



<!-- Save Button -->
<div class="mt-8 pt-6 border-t border-gray-200">
    <button type="button" onclick="document.getElementById('settingsForm').submit()"
        class="px-8 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
        Save Settings
    </button>
</div>
</form>
</div>

<!-- TinyMCE -->
<!-- TinyMCE -->
<script src="../assets/vendors/tinymce/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#quote_terms, #quote_warranty',
        height: 600,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        },
        content_style: 'body { font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6 }'
    });
</script>

<script>
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active class from all tabs
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active', 'border-primary', 'text-primary');
            button.classList.add('border-transparent', 'text-gray-600');
        });

        // Show selected tab content
        document.getElementById('content-' + tabName).classList.remove('hidden');

        // Add active class to selected tab
        const activeTab = document.getElementById('tab-' + tabName);
        activeTab.classList.add('active', 'border-primary', 'text-primary');
        activeTab.classList.remove('border-transparent', 'text-gray-600');
    }

    function previewLogo(input) {
        const preview = document.getElementById('logoPreview');
        const previewImg = document.getElementById('logoPreviewImg');

        if (input.files && input.files[0]) {
            // Check file size (3MB = 3145728 bytes)
            if (input.files[0].size > 3145728) {
                alert('File size must be less than 3MB');
                input.value = '';
                preview.classList.add('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.classList.add('hidden');
        }
    }
</script>

<!-- Bank Account Modal -->
<!-- Factory Reset Modal -->
<div id="resetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full border-t-4 border-red-600">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-2">⚠️ Factory Reset</h3>
            <p class="text-gray-600 mb-6">
                Are you absolutely sure? This will <strong>wipe everything</strong>.
                Enter your admin password to confirm.
            </p>

            <form id="resetForm" onsubmit="performFactoryReset(event)">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admin Password</label>
                    <input type="password" id="resetPassword" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeResetModal()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                        Confirm Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showResetModal() {
        document.getElementById('resetModal').classList.remove('hidden');
        document.getElementById('resetPassword').value = '';
        document.getElementById('resetPassword').focus();
    }

    function closeResetModal() {
        document.getElementById('resetModal').classList.add('hidden');
    }

    async function performFactoryReset(event) {
        event.preventDefault();
        const password = document.getElementById('resetPassword').value;

        if (!confirm('Final Warning: This will delete ALL data. Continue?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('password', password);

            const response = await fetch('../api/factory-reset.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('System reset successfully. Redirecting to setup...');
                window.location.href = '../index.php';
            } else {
                alert('Reset Failed: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
</script>

<div id="bankModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Add Bank Account</h3>
                <button type="button" onclick="closeBankModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form id="bankForm" onsubmit="saveBankAccount(event)">
                <input type="hidden" id="bankId" name="id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Name *</label>
                        <input type="text" id="bankName" name="bank_name" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="e.g., Access Bank, UBA, GTBank">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Number *</label>
                        <input type="text" id="bankAccount" name="account_number" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="e.g., 0107309773">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Name *</label>
                        <input type="text" id="bankAccountName" name="account_name" required
                            value="<?= htmlspecialchars(COMPANY_NAME) ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="e.g., Your Company Name">
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" id="bankShowOnDocs" name="show_on_documents" checked
                                class="w-5 h-5 text-primary rounded focus:ring-2 focus:ring-primary">
                            <span class="text-sm font-semibold text-gray-700">Show on documents</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 justify-end mt-6">
                    <button type="button" onclick="closeBankModal()"
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                        Save Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Bank Account Management Functions
    function showAddBankModal() {
        document.getElementById('modalTitle').textContent = 'Add Bank Account';
        document.getElementById('bankForm').reset();
        document.getElementById('bankId').value = '';
        document.getElementById('bankShowOnDocs').checked = true;
        document.getElementById('bankModal').classList.remove('hidden');
    }

    function editBank(account) {
        document.getElementById('modalTitle').textContent = 'Edit Bank Account';
        document.getElementById('bankId').value = account.id;
        document.getElementById('bankName').value = account.bank_name;
        document.getElementById('bankAccount').value = account.account_number;
        document.getElementById('bankAccountName').value = account.account_name;
        document.getElementById('bankShowOnDocs').checked = account.show_on_documents == 1;
        document.getElementById('bankModal').classList.remove('hidden');
    }

    function closeBankModal() {
        document.getElementById('bankModal').classList.add('hidden');
    }

    async function saveBankAccount(event) {
        event.preventDefault();

        const formData = new FormData(event.target);

        try {
            const response = await fetch('../api/bank-accounts/save.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error saving bank account: ' + error.message);
        }
    }

    async function toggleBankDisplay(id) {
        const formData = new FormData();
        formData.append('id', id);

        try {
            const response = await fetch('../api/bank-accounts/toggle-display.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                // Revert checkbox if error
                const checkbox = document.getElementById('bank-check-' + id);
                if (checkbox) checkbox.checked = !checkbox.checked;
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function deleteBank(id, bankName) {
        if (!confirm(`Are you sure you want to delete "${bankName}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('id', id);

        try {
            const response = await fetch('../api/bank-accounts/delete.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeBankModal();
        }
    });

    // Audit Log Management Functions
    async function exportAuditLog() {
        if (!confirm('Export all audit logs to CSV file?')) {
            return;
        }

        try {
            window.location.href = '../api/export-audit-log.php';
            alert('Audit log export started. Your download will begin shortly.');
        } catch (error) {
            alert('Error exporting audit log: ' + error.message);
        }
    }

    async function confirmClearLogs() {
        const retention = document.querySelector('select[name="audit_retention_days"]').value;

        if (retention == '0') {
            alert('Please set a retention period before clearing logs.');
            return;
        }

        const days = retention;
        const message = `This will permanently delete all audit logs older than ${days} days.\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?`;

        if (!confirm(message)) {
            return;
        }

        // Second confirmation
        if (!confirm('Final confirmation: Delete old audit logs?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('retention_days', days);

            const response = await fetch('../api/clear-audit-logs.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(`Successfully deleted ${result.deleted_count} old audit log entries.`);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error clearing logs: ' + error.message);
        }
    }

    // Handle URL parameters for tab switching
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab && document.getElementById('content-' + tab)) {
            switchTab(tab);
        }
    });
</script>

<style>
    .tab-button.active {
        border-bottom-color: #0076BE;
        color: #0076BE;
    }
</style>


<?php include '../includes/footer.php'; ?>
<?php
include '../includes/session-check.php';

// Only admins can access settings
// Note: requirePermission() always exists (defined as fallback in session-check.php)
requirePermission('manage_settings');

$pageTitle = 'System Settings - Bluedots Technologies';

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
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex -mb-px min-w-max">
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
                            value="<?php echo htmlspecialchars(getSetting('email_from_address', 'noreply@bluedots.com.ng')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Name</label>
                        <input type="text" name="email_from_name"
                            value="<?php echo htmlspecialchars(getSetting('email_from_name', 'Bluedots Technologies')); ?>"
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
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
                            <button type="button" onclick="togglePasswordVisibility(this)" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Required for AI Quote Descriptions and Solar System Design. 
                            <a href="https://console.groq.com" target="_blank" class="text-primary hover:underline">Get a free key here</a>.
                        </p>
                    </div>
                </div>

                <!-- Editor Integration -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-blue-100 rounded-full p-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900">Rich Text Editor</h4>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            TinyMCE API Key
                        </label>
                        <div class="relative">
                            <input type="text" name="tinymce_api_key" 
                                value="<?php echo htmlspecialchars(getSetting('tinymce_api_key', 'no-api-key')); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Required for the rich text editor on quote/invoice pages.
                        </p>
                    </div>
                </div>
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

        <!-- Save Button -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <button type="button" onclick="document.getElementById('settingsForm').submit()" class="px-8 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 font-semibold">
                Save Settings
            </button>
        </div>
    </form>
</div>

<!-- TinyMCE -->
<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
        setup: function(editor) {
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
</script>

<style>
    .tab-button.active {
        border-bottom-color: #0076BE;
        color: #0076BE;
    }
</style>


<?php include '../includes/footer.php'; ?>
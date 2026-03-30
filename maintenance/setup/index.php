<?php
/**
 * 1100-ERP Setup Wizard
 * Main installation interface
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/lock')) {
    die('
        <h1>Already Installed</h1>
        <p>1100-ERP is already installed on this server.</p>
        <p>To reinstall, delete the file: <code>setup/lock</code></p>
        <p><a href="../login.php">Go to Login</a></p>
    ');
}

// Check PHP requirements
$requirements = checkRequirements();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1100-ERP Setup Wizard</title>
    <link rel="stylesheet" href="assets/wizard.css">
</head>

<body>
    <div class="wizard-container">
        <!-- Header -->
        <div class="wizard-header">
            <div class="wizard-logo">11</div>
            <h1>1100-ERP Installation</h1>
            <p>Let's get your ERP system up and running!</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line"></div>
                <div class="progress-line-active" style="width: 0%;"></div>

                <div class="progress-step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Welcome</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Database</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Admin</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">5</div>
                    <div class="step-label">Company</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">6</div>
                    <div class="step-label">Install</div>
                </div>
            </div>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer" style="padding: 0 40px;"></div>

        <!-- Wizard Body -->
        <div class="wizard-body">
            <!-- Step 1: Welcome -->
            <div id="step1" class="step-content active">
                <h2>Welcome to 1100-ERP!</h2>
                <p class="description">
                    Thank you for choosing 1100-ERP. This wizard will guide you through the installation process,
                    which should take approximately 3-5 minutes.
                </p>

                <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="margin-bottom: 10px;">What this wizard will do:</h3>
                    <ul style="list-style-position: inside; line-height: 2;">
                        <li>✅ Check server requirements & PDO connectivity</li>
                        <li>✅ Set up your database connection & prefixes</li>
                        <li>✅ **Intelligent Readymade Quotes**: Pre-configured templates</li>
                        <li>✅ **Advanced ID Card Designer**: High-fidelity generation</li>
                        <li>✅ **GitHub-Powered Updates**: One-click system sync</li>
                        <li>✅ Create your admin account & company profile</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <strong>📋 Before you begin:</strong> Make sure you have your database credentials ready
                    (hostname, database name, username, and password).
                </div>
            </div>

            <!-- Step 2: Requirements Check -->
            <div id="step2" class="step-content">
                <h2>System Requirements</h2>
                <p class="description">Checking if your server meets the minimum requirements...</p>

                <ul class="requirement-list">
                    <?php foreach ($requirements as $req): ?>
                        <li class="requirement-item <?php echo $req['status']; ?>">
                            <span class="requirement-icon"><?php echo $req['icon']; ?></span>
                            <div class="requirement-text">
                                <strong><?php echo $req['name']; ?></strong>
                                <small><?php echo $req['message']; ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (hasErrors($requirements)): ?>
                    <div class="alert alert-error">
                        <strong>⚠️ Action Required:</strong> Please resolve the errors above before proceeding.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Step 3: Database Configuration -->
            <div id="step3" class="step-content">
                <h2>Database Configuration</h2>
                <p class="description">Enter your database connection details below.</p>

                <form id="databaseForm">
                    <input type="hidden" id="dbConnectionTested" value="0">

                    <!-- Installation Mode Selection -->
                    <div class="mb-6 bg-white p-4 rounded-lg border border-gray-200" style="margin-bottom: 20px;">
                        <label class="block font-medium mb-2">Installation Mode</label>
                        <div class="flex gap-4" style="display: flex; gap: 15px;">
                            <label class="flex items-center"
                                style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" name="install_mode" value="fresh" checked
                                    onchange="toggleInstallMode()">
                                <span>Fresh Installation</span>
                            </label>
                            <label class="flex items-center"
                                style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="radio" name="install_mode" value="restore" onchange="toggleInstallMode()">
                                <span>Restore from Backup</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_host">Database Host *</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        <div class="form-group">
                            <label for="db_name">Database Name *</label>
                            <input type="text" id="db_name" name="db_name" value="1100erp" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_user">Username *</label>
                            <input type="text" id="db_user" name="db_user" value="root" required>
                        </div>
                        <div class="form-group">
                            <label for="db_password">Password</label>
                            <input type="password" id="db_password" name="db_password">
                        </div>
                    </div>

                    <!-- Fresh Install Options -->
                    <div id="freshInstallOptions">
                        <div class="form-group">
                            <label for="db_prefix">Table Prefix</label>
                            <input type="text" id="db_prefix" name="db_prefix" value="erp_">
                            <small style="color: #6b7280; display: block; margin-top: 4px;">
                                Leave as default unless you have a specific reason to change it.
                            </small>
                        </div>
                    </div>

                    <!-- Restore Options -->
                    <div id="restoreOptions"
                        style="display: none; border-top: 1px solid #eee; padding-top: 15px; margin-top: 5px;">
                        <div class="form-group">
                            <label for="backup_file">Upload Backup File (.sql or .zip)</label>
                            <input type="file" id="backup_file" name="backup_file" accept=".sql,.zip">
                            <small style="color: #6b7280; display: block; margin-top: 4px;">
                                Creates database and restores data automatically.
                            </small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="button" id="testDbConnection" class="btn btn-primary">
                            Test Connection
                        </button>
                        <button type="button" id="restoreBtn" class="btn btn-success"
                            style="display: none; background: #10B981; border: none; color: white;"
                            onclick="performSetupRestore()">
                            Restore & Install
                        </button>
                    </div>

                    <div id="restoreStatus" class="alert" style="display: none; margin-top: 15px;"></div>
                </form>

                <script>
                    function toggleInstallMode() {
                        const mode = document.querySelector('input[name="install_mode"]:checked').value;
                        const freshOpts = document.getElementById('freshInstallOptions');
                        const restoreOpts = document.getElementById('restoreOptions');
                        const testBtn = document.getElementById('testDbConnection');
                        const restoreBtn = document.getElementById('restoreBtn');

                        if (mode === 'restore') {
                            freshOpts.style.display = 'none';
                            restoreOpts.style.display = 'block';
                            testBtn.style.display = 'none'; // Hide test, show restore action
                            restoreBtn.style.display = 'inline-block';
                            // We still need to test connection implicitly before restore
                        } else {
                            freshOpts.style.display = 'block';
                            restoreOpts.style.display = 'none';
                            testBtn.style.display = 'inline-block';
                            restoreBtn.style.display = 'none';
                        }
                    }
                </script>
            </div>

            <!-- Step 4: Admin Account -->
            <div id="step4" class="step-content">
                <h2>Create Admin Account</h2>
                <p class="description">Set up your administrator account.</p>

                <form id="adminForm">
                    <div class="form-group">
                        <label for="admin_name">Full Name *</label>
                        <input type="text" id="admin_name" name="admin_name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_username">Username *</label>
                            <input type="text" id="admin_username" name="admin_username" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_email">Email *</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_password">Password *</label>
                            <input type="password" id="admin_password" name="admin_password" required>
                            <small style="color: #6b7280; display: block; margin-top: 4px;">
                                Minimum 8 characters
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="admin_password_confirm">Confirm Password *</label>
                            <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Step 5: Company Information -->
            <div id="step5" class="step-content">
                <h2>Company Information</h2>
                <p class="description">Configure your company details. You can update these later in settings.</p>

                <form id="companyForm">
                    <div class="form-group">
                        <label for="company_name">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="company_email">Email</label>
                            <input type="email" id="company_email" name="company_email">
                        </div>
                        <div class="form-group">
                            <label for="company_phone">Phone</label>
                            <input type="text" id="company_phone" name="company_phone">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="company_address">Address</label>
                        <textarea id="company_address" name="company_address" rows="3"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="vat_rate">VAT/Tax Rate (%)</label>
                            <input type="number" id="vat_rate" name="vat_rate" value="7.5" step="0.1" min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" value="₦">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Step 6: Installation Progress -->
            <div id="step6" class="step-content">
                <div class="installation-progress">
                    <h2>Installing Database</h2>
                    <p class="description" id="installStatus">Preparing installation...</p>

                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>

                    <ul class="installation-steps" id="installSteps">
                        <li>Creating database</li>
                        <li>Creating tables</li>
                        <li>Creating admin account</li>
                        <li>Initializing settings</li>
                        <li>Finalizing installation</li>
                    </ul>

                    <div class="alert alert-success" id="installComplete" style="margin-top: 20px; display: none;">
                        <h3 style="margin: 0 0 10px 0;">🎉 Installation Complete!</h3>
                        <p style="margin-bottom: 20px;">Your 1100-ERP system has been installed successfully.</p>

                        <div style="background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <strong style="color: #c53030; display: block; margin-bottom: 8px;">🛡️ CRITICAL SECURITY LOCKDOWN:</strong>
                            <p style="margin-bottom: 12px; font-size: 0.9em; color: #742a2a;">To prevent unauthorized access to your database structure and credentials, you <strong>MUST</strong> perform the following deletions:</p>
                            <ul style="font-size: 0.85em; list-style-type: none; padding: 0; color: #742a2a; margin-bottom: 15px;">
                                <li style="margin-bottom: 4px;">📂 Delete folder: <code style="background: #fffaf0; padding: 2px 4px;">maintenance/setup/</code></li>
                                <li style="margin-bottom: 4px;">📄 Delete file: <code style="background: #fffaf0; padding: 2px 4px;">maintenance/bluedots_1100erp.sql</code></li>
                                <li style="margin-bottom: 4px;">📄 Delete file: <code style="background: #fffaf0; padding: 2px 4px;">maintenance/migrate_readymade.php</code></li>
                            </ul>
                            <small style="color: #c53030; font-weight: bold;">Failure to delete these files is a high security risk.</small>
                        </div>

                        <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <strong style="color: #0369a1;">📋 Final Step:</strong>
                            <p style="margin: 5px 0 15px 0; font-size: 0.9em;">Click the button below to ensure all database tables are perfectly synchronized.</p>
                            <a href="../run-schema-update.php" target="_blank" class="btn btn-primary"
                                style="background: #0369a1; border-color: #0369a1; width: 100%; display: block; text-align: center; text-decoration: none;">
                                Run Database Final Check
                            </a>
                        </div>

                        <a href="../login.php" class="btn btn-secondary"
                            style="width: 100%; display: block; text-align: center; text-decoration: none;">
                            Go to Login Page
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Navigation -->
        <div class="wizard-footer">
            <button class="btn btn-secondary" data-prev style="visibility: hidden;">
                ← Previous
            </button>
            <button class="btn btn-primary" data-next>
                Next →
            </button>
        </div>
    </div>

    <script src="assets/wizard.js"></script>
    <script>
        // Auto-start installation on step 6
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new MutationObserver((mutations) => {
                const step6 = document.getElementById('step6');
                if (step6 && step6.classList.contains('active')) {
                    // Start installation automatically
                    setTimeout(() => {
                        window.wizard.installDatabase().then(success => {
                            if (success) {
                                // Show completion message
                                const installComplete = document.getElementById('installComplete');
                                if (installComplete) {
                                    installComplete.style.display = 'block';
                                    document.querySelector('.progress-bar').style.display = 'none';
                                    document.querySelector('.installation-steps').style.display = 'none';
                                    document.getElementById('installStatus').textContent = "Installation Successful!";
                                }
                            }
                        });
                    }, 500);
                }
            });

            observer.observe(document.querySelector('.wizard-body'), {
                attributes: true,
                subtree: true,
                attributeFilter: ['class']
            });
        });
    </script>
</body>

</html>

<?php
/**
 * Check System Requirements
 */
function checkRequirements()
{
    $requirements = [];

    // PHP Version
    $phpVersion = phpversion();
    $requirements[] = [
        'name' => 'PHP Version',
        'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'success' : 'error',
        'icon' => version_compare($phpVersion, '7.4.0', '>=') ? '✓' : '✗',
        'message' => "Your PHP version: $phpVersion (Required: 7.4+)"
    ];

    // PDO Extension
    $requirements[] = [
        'name' => 'PDO Extension',
        'status' => extension_loaded('pdo') ? 'success' : 'error',
        'icon' => extension_loaded('pdo') ? '✓' : '✗',
        'message' => extension_loaded('pdo') ? 'PDO extension is installed' : 'PDO extension is required'
    ];

    // MySQL Extension
    $requirements[] = [
        'name' => 'MySQL Extension',
        'status' => extension_loaded('pdo_mysql') ? 'success' : 'error',
        'icon' => extension_loaded('pdo_mysql') ? '✓' : '✗',
        'message' => extension_loaded('pdo_mysql') ? 'MySQL extension is installed' : 'MySQL extension is required'
    ];

    // mbstring Extension
    $requirements[] = [
        'name' => 'mbstring Extension',
        'status' => extension_loaded('mbstring') ? 'success' : 'warning',
        'icon' => extension_loaded('mbstring') ? '✓' : '!',
        'message' => extension_loaded('mbstring') ? 'mbstring extension is installed' : 'Recommended for better text handling'
    ];

    // JSON Extension
    $requirements[] = [
        'name' => 'JSON Extension',
        'status' => extension_loaded('json') ? 'success' : 'error',
        'icon' => extension_loaded('json') ? '✓' : '✗',
        'message' => extension_loaded('json') ? 'JSON extension is installed' : 'JSON extension is required'
    ];

    // File Permissions
    $configWritable = is_writable(dirname(__DIR__, 2));
    $requirements[] = [
        'name' => 'File Permissions',
        'status' => $configWritable ? 'success' : 'error',
        'icon' => $configWritable ? '✓' : '✗',
        'message' => $configWritable ? 'Directory is writable' : 'Directory must be writable to create config.php'
    ];

    return $requirements;
}

function hasErrors($requirements)
{
    foreach ($requirements as $req) {
        if ($req['status'] === 'error') {
            return true;
        }
    }
    return false;
}
?>
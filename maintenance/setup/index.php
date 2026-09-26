<?php
/**
 * 1100-ERP Setup Wizard
 * Main installation interface
 */

session_start();

// Refuse on configured systems: the wizard must never run where a config exists.
// (Allows safe reset flow: reset deletes config.php, wizard runs, cleanup deletes setup/.)
require_once __DIR__ . '/install-guard.php';
install_require_claim_ui();

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
                <div class="progress-step">
                    <div class="step-circle">7</div>
                    <div class="step-label">Cleanup</div>
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
                                <input type="radio" name="install_mode" value="fresh" checked>
                                <span>Fresh Installation</span>
                            </label>
                            <!-- WP14: web restore removed (pre-auth backup restore is
                                 too dangerous behind a claim file); restore via CLI:
                                 mysql -u USER -p DB < backup.sql (see INSTALL_RUNBOOK). -->
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

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="button" id="testDbConnection" class="btn btn-primary">
                            Test Connection
                        </button>
                    </div>
                </form>
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
                                Minimum 12 characters (not a common password)
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
                </div>
            </div>

            <!-- Step 7: Final Check + Cleanup -->
            <div id="step7" class="step-content">
                <h2>Final Check &amp; Cleanup</h2>
                <p class="description">Two one-click actions finish the job. No separate login needed.</p>

                <div id="cleanupStatus" class="alert alert-info" style="margin-bottom: 20px;">
                    Installation complete. Run the final check, then delete the installer.
                </div>

                <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <strong style="color: #0369a1;">Step 1 — Final check:</strong>
                    <p style="margin: 5px 0 15px 0; font-size: 0.9em;">Synchronizes all database tables with the codebase. The report appears below.</p>
                    <button type="button" id="step7CheckBtn" class="btn btn-primary"
                        style="background: #0369a1; border-color: #0369a1; width: 100%; display: block; text-align: center;"
                        onclick="runStep7Check()">
                        Run Database Final Check
                    </button>
                    <div id="step7Report" style="margin-top: 12px; max-height: 300px; overflow-y: auto; font-size: 0.85em;"></div>
                </div>

                <div id="step7Delete" style="background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <strong style="color: #c53030; display: block; margin-bottom: 8px;">Step 2 — Delete installer:</strong>
                    <p style="margin: 5px 0 15px 0; font-size: 0.9em; color: #742a2a;">Removes the entire <code>maintenance/</code> folder. After this, use <strong>System Update</strong> inside the app. Requires your current admin password.</p>
                    <div style="margin-bottom: 10px;">
                        <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:0.9em;">Current admin password</label>
                        <input type="password" id="step7Password" autocomplete="current-password"
                            style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <button type="button" id="step7CleanupBtn" class="btn btn-secondary"
                        style="width: 100%; display: block; background: #c53030; border-color: #c53030; color: white;"
                        onclick="runStep7Cleanup()">
                        Delete Installer Now
                    </button>
                </div>

                <a href="../../login.php" class="btn btn-secondary"
                    style="width: 100%; display: block; text-align: center; text-decoration: none;">
                    Go to Login Page
                </a>
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
        // Step 7: final schema check via the gated install.php action
        // (replaces the deleted run-schema-update.php).
        async function runStep7Check() {
            const report = document.getElementById('step7Report');
            const btn = document.getElementById('step7CheckBtn');
            btn.disabled = true;
            btn.textContent = 'Checking...';
            report.innerHTML = '<p>Running schema check...</p>';
            try {
                const formData = new FormData();
                formData.append('action', 'final_check');
                const response = await fetch('install.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success && result.entries) {
                    let html = '<ul style="list-style:none;padding:0;">';
                    result.entries.forEach(e => {
                        const color = e.status === 'ok' ? 'green' : (e.status === 'error' ? 'red' : 'blue');
                        html += '<li style="color:' + color + ';">'
                            + (e.status === 'ok' ? '✓' : (e.status === 'error' ? '✗' : 'ℹ'))
                            + ' ' + e.message.replace(/</g, '&lt;') + '</li>';
                    });
                    report.innerHTML = html + '</ul>';
                } else {
                    report.innerHTML = '<p style="color:red;">Check failed: '
                        + (result.message || 'unknown error').replace(/</g, '&lt;') + '</p>';
                }
            } catch (e) {
                report.innerHTML = '<p style="color:red;">Error: ' + e.message.replace(/</g, '&lt;') + '</p>';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Run Database Final Check';
                document.getElementById('step7Delete').style.display = 'block';
            }
        }

        // Step 7: one-click installer delete (works: installer auto-logs in the new admin)
        async function runStep7Cleanup() {
            const status = document.getElementById('cleanupStatus');
            const btn = document.getElementById('step7CleanupBtn');
            const password = document.getElementById('step7Password').value;
            if (!window.wizard || !window.wizard.installDone) {
                status.className = 'alert alert-error';
                status.textContent = 'Complete the installation first (Step 6), then clean up.';
                return;
            }
            if (!password) {
                status.className = 'alert alert-error';
                status.textContent = 'Enter your current admin password to confirm deletion.';
                return;
            }
            if (!confirm('Permanently delete the entire maintenance/ folder?')) return;
            btn.disabled = true;
            btn.textContent = 'Deleting...';
            status.className = 'alert alert-info';
            status.textContent = 'Deleting installer...';
            try {
                // Mint a CSRF token for this session, then post the delete.
                const csrfForm = new FormData();
                csrfForm.append('action', 'csrf_token');
                const csrfResp = await fetch('install.php', { method: 'POST', body: csrfForm });
                const csrfData = await csrfResp.json();
                const formData = new FormData();
                formData.append('confirm', 'YES');
                formData.append('format', 'json');
                formData.append('password', password);
                if (csrfData.csrf_token) formData.append('csrf_token', csrfData.csrf_token);
                const response = await fetch('cleanup.php', { method: 'POST', body: formData });
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    // HTML means: not logged in (e.g. restore-mode install). Login first.
                    status.className = 'alert alert-error';
                    status.innerHTML = 'Cleanup needs an admin login. <a href="../../login.php">Log in</a>, then open <strong>System Update &gt; Delete Installer</strong>.';
                    btn.disabled = false;
                    btn.textContent = 'Delete Installer Now';
                    return;
                }
                if (result.success) {
                    status.className = 'alert alert-success';
                    status.innerHTML = '<strong>Installer deleted.</strong> ' + result.message + ' <a href="../../login.php">Go to Login Page</a>';
                    btn.textContent = 'Deleted ✓';
                } else {
                    status.className = 'alert alert-error';
                    status.textContent = 'Cleanup incomplete: ' + result.message;
                    btn.disabled = false;
                    btn.textContent = 'Delete Installer Now';
                }
            } catch (e) {
                status.className = 'alert alert-error';
                status.textContent = 'Error: ' + e.message;
                btn.disabled = false;
                btn.textContent = 'Delete Installer Now';
            }
        }

        // Auto-start installation on step 6
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new MutationObserver((mutations) => {
                const step6 = document.getElementById('step6');
                if (step6 && step6.classList.contains('active')) {
                    // Render the manual per-step buttons once.
                    // Completion is handled by runSingleStep(): last step advances to Step 7.
                    setTimeout(() => {
                        window.wizard.installDatabase();
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
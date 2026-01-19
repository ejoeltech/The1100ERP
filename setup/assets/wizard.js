// 1100-ERP Setup Wizard JavaScript
// Handles wizard navigation, validation, and AJAX requests

class SetupWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 6;
        this.init();
    }

    init() {
        this.updateProgress();
        this.attachEventListeners();
    }

    attachEventListeners() {
        // Next button
        const nextBtns = document.querySelectorAll('[data-next]');
        nextBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.nextStep();
            });
        });

        // Previous button
        const prevBtns = document.querySelectorAll('[data-prev]');
        prevBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.prevStep();
            });
        });

        // Test database connection button
        const testDbBtn = document.getElementById('testDbConnection');
        if (testDbBtn) {
            testDbBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.testDatabaseConnection();
            });
        }
    }

    nextStep() {
        // Validate current step before proceeding
        if (this.validateStep(this.currentStep)) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateStep();
            }
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStep();
        }
    }

    updateStep() {
        // Hide all steps
        document.querySelectorAll('.step-content').forEach(step => {
            step.classList.remove('active');
        });

        // Show current step
        const currentStepEl = document.getElementById(`step${this.currentStep}`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }

        this.updateProgress();
        this.updateButtons();

        // Auto-scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    updateProgress() {
        // Update progress circles
        document.querySelectorAll('.progress-step').forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'completed');

            if (stepNum < this.currentStep) {
                step.classList.add('completed');
                step.querySelector('.step-circle').textContent = '✓';
            } else if (stepNum === this.currentStep) {
                step.classList.add('active');
                step.querySelector('.step-circle').textContent = stepNum;
            } else {
                step.querySelector('.step-circle').textContent = stepNum;
            }
        });

        // Update progress line
        const progressPercent = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
        const progressLine = document.querySelector('.progress-line-active');
        if (progressLine) {
            progressLine.style.width = `${progressPercent}%`;
        }
    }

    updateButtons() {
        // Update prev button visibility
        const prevBtn = document.querySelector('[data-prev]');
        if (prevBtn) {
            prevBtn.style.visibility = this.currentStep > 1 ? 'visible' : 'hidden';
        }

        // Update next button text on company step (before installation)
        const nextBtn = document.querySelector('[data-next]');
        if (nextBtn && this.currentStep === 5) {
            nextBtn.textContent = 'Start Installation →';
        } else if (nextBtn) {
            nextBtn.textContent = 'Next →';
        }
    }

    validateStep(stepNum) {
        switch (stepNum) {
            case 1: // Welcome
                return true;

            case 2: // Requirements
                const failedReqs = document.querySelectorAll('.requirement-item.error');
                if (failedReqs.length > 0) {
                    this.showAlert('Please resolve all requirement errors before proceeding.', 'error');
                    return false;
                }
                return true;

            case 3: // Database
                return this.validateDatabaseForm();

            case 4: // Admin
                return this.validateAdminForm();

            case 5: // Company
                return this.validateCompanyForm();

            case 6: // Install (automated)
                return true;

            default:
                return true;
        }
    }

    validateDatabaseForm() {
        const dbHost = document.getElementById('db_host');
        const dbName = document.getElementById('db_name');
        const dbUser = document.getElementById('db_user');

        if (!dbHost.value || !dbName.value || !dbUser.value) {
            this.showAlert('Please fill in all required database fields.', 'error');
            return false;
        }

        // Check if connection was tested
        const connectionTested = document.getElementById('dbConnectionTested');
        if (!connectionTested || connectionTested.value !== '1') {
            this.showAlert('Please test the database connection before proceeding.', 'warning');
            return false;
        }

        return true;
    }

    validateAdminForm() {
        const fullName = document.getElementById('admin_name');
        const username = document.getElementById('admin_username');
        const email = document.getElementById('admin_email');
        const password = document.getElementById('admin_password');
        const confirmPassword = document.getElementById('admin_password_confirm');

        if (!fullName.value || !username.value || !email.value || !password.value || !confirmPassword.value) {
            this.showAlert('Please fill in all admin account fields.', 'error');
            return false;
        }

        if (password.value !== confirmPassword.value) {
            this.showAlert('Passwords do not match.', 'error');
            return false;
        }

        if (password.value.length < 8) {
            this.showAlert('Password must be at least 8 characters long.', 'error');
            return false;
        }

        if (!this.validateEmail(email.value)) {
            this.showAlert('Please enter a valid email address.', 'error');
            return false;
        }

        return true;
    }

    validateCompanyForm() {
        const companyName = document.getElementById('company_name');

        if (!companyName.value) {
            this.showAlert('Company name is required.', 'error');
            return false;
        }

        return true;
    }

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    async testDatabaseConnection() {
        const btn = document.getElementById('testDbConnection');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Testing...';

        const formData = new FormData();
        formData.append('action', 'test_connection');
        formData.append('db_host', document.getElementById('db_host').value);
        formData.append('db_name', document.getElementById('db_name').value);
        formData.append('db_user', document.getElementById('db_user').value);
        formData.append('db_password', document.getElementById('db_password').value);

        try {
            const response = await fetch('install.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('✓ Database connection successful!', 'success');
                document.getElementById('dbConnectionTested').value = '1';
                btn.textContent = '✓ Connection Successful';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
            } else {
                this.showAlert('✗ ' + (result.message || 'Connection failed'), 'error');
                document.getElementById('dbConnectionTested').value = '0';
                btn.textContent = originalText;
            }
        } catch (error) {
            this.showAlert('Error testing connection: ' + error.message, 'error');
            btn.textContent = originalText;
        } finally {
            btn.disabled = false;
        }
    }

    async installDatabase() {
        const progressBar = document.querySelector('.progress-bar-fill');
        const statusText = document.getElementById('installStatus');
        const stepsList = document.getElementById('installSteps');

        // Reset UI initially
        statusText.innerHTML = '<strong>Ready to install</strong><br><small>Click the button below to start each step manually.</small>';
        stepsList.innerHTML = ''; // Clear existing list to rebuild with buttons

        // Create verbose log container if not exists
        let verboseLog = document.getElementById('verboseLog');
        if (!verboseLog) {
            verboseLog = document.createElement('div');
            verboseLog.id = 'verboseLog';
            verboseLog.style.cssText = 'margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb;';
            stepsList.parentElement.appendChild(verboseLog);
        }

        const log = (message, type = 'info') => {
            const timestamp = new Date().toLocaleTimeString();
            const colors = { info: '#6b7280', success: '#10b981', error: '#ef4444', warning: '#f59e0b', processing: '#3b82f6' };
            const entry = document.createElement('div');
            entry.style.color = colors[type] || colors.info;
            entry.innerHTML = `[${timestamp}] ${message}`;
            verboseLog.appendChild(entry);
            verboseLog.scrollTop = verboseLog.scrollHeight;
            console.log(`[INSTALL] ${message}`);
        };

        log('🚀 Ready for manual installation', 'info');
        log(`Database: ${document.getElementById('db_name').value}@${document.getElementById('db_host').value}`, 'info');

        const steps = [
            { id: 0, action: 'create_database', label: 'Create Database', detail: 'Set up database structure' },
            { id: 1, action: 'import_schema', label: 'Create Tables', detail: 'Import schema and tables' },
            { id: 2, action: 'create_admin', label: 'Create Admin Account', detail: 'Set up administrator' },
            { id: 3, action: 'init_settings', label: 'Initialize Settings', detail: 'Configure system defaults' },
            { id: 4, action: 'finalize', label: 'Finalize Installation', detail: 'Write config and finish' }
        ];

        // Render steps with buttons
        steps.forEach((step, index) => {
            const li = document.createElement('li');
            li.id = `step-item-${index}`;
            li.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee;';

            const infoDiv = document.createElement('div');
            infoDiv.innerHTML = `<strong style="display:block; margin-bottom: 2px;">${index + 1}. ${step.label}</strong><small style="color:#666;">${step.detail}</small>`;

            const actionDiv = document.createElement('div');
            actionDiv.id = `step-action-${index}`;

            // First step is active initially
            if (index === 0) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-primary btn-sm';
                btn.textContent = 'Run Step';
                btn.onclick = () => this.runSingleStep(index, steps, log);
                actionDiv.appendChild(btn);
            } else {
                actionDiv.innerHTML = '<span style="color: #999; font-size: 12px;">Waiting...</span>';
            }

            li.appendChild(infoDiv);
            li.appendChild(actionDiv);
            stepsList.appendChild(li);
        });
    }

    async runSingleStep(index, steps, log) {
        const step = steps[index];
        const stepItem = document.getElementById(`step-item-${index}`);
        const actionDiv = document.getElementById(`step-action-${index}`);
        const statusText = document.getElementById('installStatus');
        const progressBar = document.querySelector('.progress-bar-fill');

        // Update UI state
        if (actionDiv) actionDiv.innerHTML = '<span class="spinner"></span> Running...';
        statusText.innerHTML = `<strong>Executing: ${step.label}</strong><br><small>Please wait...</small>`;
        log(`\n▶ Starting Step ${index + 1}: ${step.label}`, 'processing');

        // Prepare form data
        const formData = new FormData();
        formData.append('action', step.action);
        formData.append('db_host', document.getElementById('db_host').value);
        formData.append('db_name', document.getElementById('db_name').value);
        formData.append('db_user', document.getElementById('db_user').value);
        formData.append('db_password', document.getElementById('db_password').value);
        formData.append('db_prefix', document.getElementById('db_prefix').value);

        if (step.action === 'create_admin') {
            formData.append('admin_name', document.getElementById('admin_name').value);
            formData.append('admin_username', document.getElementById('admin_username').value);
            formData.append('admin_email', document.getElementById('admin_email').value);
            formData.append('admin_password', document.getElementById('admin_password').value);
        }

        if (step.action === 'init_settings') {
            formData.append('company_name', document.getElementById('company_name').value);
            formData.append('company_email', document.getElementById('company_email').value || '');
            formData.append('company_phone', document.getElementById('company_phone').value || '');
            formData.append('company_address', document.getElementById('company_address').value || '');
            formData.append('vat_rate', document.getElementById('vat_rate').value || '7.5');
            formData.append('currency_symbol', document.getElementById('currency_symbol').value || '₦');
        }

        try {
            const startTime = Date.now();
            const response = await fetch('install.php', { method: 'POST', body: formData });
            const responseTime = Date.now() - startTime;

            log(`   Response: ${response.status} ${response.statusText} (${responseTime}ms)`, response.ok ? 'info' : 'warning');
            const responseText = await response.text();

            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                log(`   Invalid JSON: ${responseText.substring(0, 100)}...`, 'error');
                throw new Error('Server returned invalid JSON response');
            }

            if (!result.success) throw new Error(result.message);

            // SUCCESS HANDLER
            log(`   ✓ Success: ${result.message}`, 'success');

            // distinct visual success
            if (actionDiv) actionDiv.innerHTML = '<span style="color: #10b981; font-weight: bold;">✓ Completed</span>';
            stepItem.style.backgroundColor = '#f0fdf4'; // Light green bg

            // Update global progress
            const progressPercent = Math.round(((index + 1) / steps.length) * 100);
            progressBar.style.width = `${progressPercent}%`;
            progressBar.textContent = `${progressPercent}%`;

            // Setup NEXT step
            const nextIndex = index + 1;
            if (nextIndex < steps.length) {
                const nextActionDiv = document.getElementById(`step-action-${nextIndex}`);
                if (nextActionDiv) {
                    const btn = document.createElement('button');
                    btn.className = 'btn btn-primary btn-sm';
                    btn.textContent = `Run Step ${nextIndex + 1} →`;
                    btn.onclick = () => this.runSingleStep(nextIndex, steps, log);
                    nextActionDiv.innerHTML = '';
                    nextActionDiv.appendChild(btn);

                    // Auto-focus logic or auto-scroll
                    statusText.innerHTML = `<strong>Step ${index + 1} Complete!</strong><br><small>Click 'Run Step ${nextIndex + 1}' to continue.</small>`;
                }
            } else {
                // ALL DONE
                statusText.innerHTML = `<strong style="color: #10b981;">Installation Complete!</strong><br><small>Redirecting to login...</small>`;
                log('🎉 All steps finished successfully!', 'success');
                setTimeout(() => {
                    if (result.redirect) window.location.href = result.redirect;
                }, 2000);
            }

        } catch (error) {
            log(`   ❌ Error: ${error.message}`, 'error');
            statusText.innerHTML = `<strong style="color: #ef4444;">Step Failed</strong><br><small>${error.message}</small>`;

            if (actionDiv) {
                actionDiv.innerHTML = '';
                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn btn-danger btn-sm';
                retryBtn.textContent = 'Retry Step';
                retryBtn.onclick = () => this.runSingleStep(index, steps, log);
                actionDiv.appendChild(retryBtn);
            }
            this.showAlert(`Step failed: ${error.message}`, 'error');
        }
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;

        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;

        alertContainer.innerHTML = '';
        alertContainer.appendChild(alert);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
}

// Initialize wizard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.wizard = new SetupWizard();
});

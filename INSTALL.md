# 1100-ERP Installation Manual

This comprehensive guide covers the installation, configuration, and troubleshooting of 1100-ERP.

## 1. System Requirements
- **PHP Version**: 7.4 or higher (PHP 8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10+
- **Extensions**: `pdo_mysql`, `json`, `mbstring`, `curl`

## 2. Default Installation (Automated)
1100-ERP comes with a built-in setup wizard to simplify installation.

### Step 1: Upload Files
1.  Upload the contents of the latest release archive to your web server (e.g., `public_html` or a subdomain folder).
2.  Ensure file permissions are correct (folders `755`, files `644`).

### Step 2: Create Database
1.  Log in to your hosting control panel (e.g., cPanel).
2.  Go to **MySQL Databases**.
3.  Create a new database (e.g., `yourname_1100erp`).
4.  Create a new database user and generate a password.
5.  **Important**: Grant "ALL PRIVILEGES" to the user for that database.

### Step 3: Run the Setup Wizard
1.  Open your browser and navigate to your site URL: `https://your-site.com/maintenance/setup/`
2.  Follow the on-screen prompts:
    *   **Database Config**: Enter your database name, user, and password.
    *   **Admin Account**: Create your initial administrator login.
    *   **Company Info**: Enter your business details.
3.  Click **Install**. The system will verify the database connection and import the schema automatically.

---

## 3. Manual Installation (If Wizard Fails)
If the setup wizard does not work or you prefer manual control:

1.  **Configure Database**:
    *   Import `maintenance/setup/install-schema.sql` into your database using phpMyAdmin.
2.  **Configure Secrets (WP1: no secrets in code)**:
    *   Copy `config.sample.php` to `config.php` (secret-free loader, gitignored).
    *   Copy `.env.example` to `.env` and fill in real values
        (or export real environment variables / set `APP_SECRETS_FILE`
        outside the web root on production).
    *   Missing or placeholder values fail fast with a generic 503.

---

## 4. Troubleshooting & Re-installation

### Removing the Lock File
If you need to run the installation wizard again (e.g., after a failed install or for a fresh start), you might see a message saying "Installation Locked".

**To unlock the installer:**
1.  Open your File Manager (cPanel or FTP).
2.  Navigate to `maintenance/setup/` inside your 1100-ERP directory.
3.  Look for a file named **`lock`** (it has no file extension).
4.  **Delete** this file.
5.  Navigate back to `https://your-site.com/maintenance/setup/`, and the wizard will restart.

### Updating to a New Version
1.  Upload the new files (overwrite existing ones).
2.  After installing, run the schema patcher from **Settings → Maintenance → System Updates → Run Schema Patcher**.
3.  This script will automatically add any missing database columns or configuration settings required by the new version.

### Common Issues
*   **"Too Many Redirects"**: Clear stale browser assets/cache and verify the configured `APP_URL` matches the real site URL.
*   **White Screen on Quote Page**: Verify the company logo is set in **Settings → Company** and run **System Updates → Run Schema Patcher**.
*   **Mobile Table Issues**: Clear your browser cache to load the latest JavaScript fixes.

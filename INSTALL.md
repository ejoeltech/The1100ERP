# 1100-ERP Installation Manual

This comprehensive guide covers the installation, configuration, and troubleshooting of 1100-ERP.

## 1. System Requirements
- **PHP Version**: 7.4 or higher (PHP 8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10+
- **Extensions**: `pdo_mysql`, `json`, `mbstring`, `curl`

## 2. Default Installation (Automated)
1100-ERP comes with a built-in setup wizard to simplify installation.

### Step 1: Upload Files
1.  Upload the contents of the `1100erp_production_v5.zip` (or latest version) to your web server (e.g., `public_html` or a subdomain folder).
2.  Ensure file permissions are correct (folders `755`, files `644`).

### Step 2: Create Database
1.  Log in to your hosting control panel (e.g., cPanel).
2.  Go to **MySQL Databases**.
3.  Create a new database (e.g., `yourname_1100erp`).
4.  Create a new database user and generate a password.
5.  **Important**: Grant "ALL PRIVILEGES" to the user for that database.

### Step 3: Run the Setup Wizard
1.  Open your browser and navigate to your site URL: `https://your-site.com/setup/`
2.  Follow the on-screen prompts:
    *   **Database Config**: Enter your database name, user, and password.
    *   **Admin Account**: Create your initial administrator login.
    *   **Company Info**: Enter your business details.
3.  Click **Install**. The system will verify the database connection and import the schema automatically.

---

## 3. Manual Installation (If Wizard Fails)
If the setup wizard does not work or you prefer manual control:

1.  **Configure Database**:
    *   Import `database/install-schema.sql` into your database using phpMyAdmin.
2.  **Configure Config File**:
    *   Rename `config.sample.php` to `config.php`.
    *   Edit `config.php` and fill in your usage details:
        ```php
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'your_db_name');
        define('DB_USER', 'your_db_user');
        define('DB_PASS', 'your_db_password');
        ```

---

## 4. Troubleshooting & Re-installation

### Removing the Lock File
If you need to run the installation wizard again (e.g., after a failed install or for a fresh start), you might see a message saying "Installation Locked".

**To unlock the installer:**
1.  Open your File Manager (cPanel or FTP).
2.  Navigate to the `setup/` folder inside your 1100-ERP directory.
3.  Look for a file named **`lock`** (it has no file extension).
4.  **Delete** this file.
5.  Navigate back to `https://your-site.com/setup/`, and the wizard will restart.

### Updating to a New Version
1.  Upload the new files (overwrite existing ones).
2.  Run the schema updater by visiting: `https://your-site.com/run-schema-update.php`
3.  This script will automatically add any missing database columns or configuration settings required by the new version.

### Common Issues
*   **"Too Many Redirects"**: Ensure your `config.php` has the fix for CDN assets (change local Tom Select paths to CDN).
*   **White Screen on Quote Page**: Check that `COMPANY_LOGO` is defined in `config.php` (run `run-schema-update.php` to fix this automatically).
*   **Mobile Table Issues**: Clear your browser cache to load the latest JavaScript fixes.

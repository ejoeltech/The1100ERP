<?php
// 1100-ERP System Configuration File
// Rename this file to config.php and update the values below

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', '1100erp');
define('DB_USER', 'root'); // Default XAMPP user
define('DB_PASS', '');     // Default XAMPP password (empty)
define('DB_PREFIX', 'erp_');

// Establish Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to get settings
function getSetting($key, $default = '')
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Load basic settings into constants
define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));
define('COMPANY_LOGO', getSetting('company_logo', ''));
define('COMPANY_ADDRESS', getSetting('company_address', ''));
define('COMPANY_PHONE', getSetting('company_phone', ''));
define('COMPANY_EMAIL', getSetting('company_email', ''));
define('COMPANY_WEBSITE', getSetting('company_website', ''));
define('VAT_RATE', (float) getSetting('vat_rate', 7.5));
define('CURRENCY_SYMBOL', getSetting('currency_symbol', '₦'));
define('DEFAULT_PAYMENT_TERMS', getSetting('default_payment_terms', '80% Initial Deposit'));

// TinyMCE API Key (Get a free key from tiny.cloud)
define('TINYMCE_API_KEY', getSetting('tinymce_api_key', 'your-tinymce-api-key-here'));

// Bank account helper functions
function getBankAccountsForDisplay()
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT * FROM bank_accounts 
            WHERE is_active = 1 AND show_on_documents = 1 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getAllBankAccounts()
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT * FROM bank_accounts 
            WHERE is_active = 1 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getSelectedBankAccountsCount()
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM bank_accounts 
            WHERE is_active = 1 AND show_on_documents = 1
        ");
        $result = $stmt->fetch();
        return $result['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

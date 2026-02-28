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
if (!function_exists('getSetting')) {
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
}

// Load basic settings into constants
if (!defined('COMPANY_NAME'))
    define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));
if (!defined('COMPANY_LOGO'))
    define('COMPANY_LOGO', getSetting('company_logo', ''));
if (!defined('COMPANY_ADDRESS'))
    define('COMPANY_ADDRESS', getSetting('company_address', ''));
if (!defined('COMPANY_PHONE'))
    define('COMPANY_PHONE', getSetting('company_phone', ''));
if (!defined('COMPANY_EMAIL'))
    define('COMPANY_EMAIL', getSetting('company_email', ''));
if (!defined('COMPANY_WEBSITE'))
    define('COMPANY_WEBSITE', getSetting('company_website', ''));
if (!defined('VAT_RATE'))
    define('VAT_RATE', (float) getSetting('vat_rate', 7.5));
if (!defined('CURRENCY_SYMBOL'))
    define('CURRENCY_SYMBOL', getSetting('currency_symbol', '₦'));
if (!defined('DEFAULT_PAYMENT_TERMS'))
    define('DEFAULT_PAYMENT_TERMS', getSetting('default_payment_terms', '80% Initial Deposit'));
if (!defined('TINYMCE_API_KEY'))
    define('TINYMCE_API_KEY', getSetting('tinymce_api_key', 'no-api-key'));
if (!defined('THEME_COLOR'))
    define('THEME_COLOR', getSetting('theme_color', '#0076BE'));
if (!defined('FOOTER_TEXT'))
    define('FOOTER_TEXT', getSetting('footer_text', 'We appreciate your business! Thank you'));
if (!defined('PDF_QUALITY'))
    define('PDF_QUALITY', getSetting('pdf_quality', 'high'));



// Bank account helper functions
if (!function_exists('getBankAccountsForDisplay')) {
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
}

if (!function_exists('getAllBankAccounts')) {
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
}

if (!function_exists('getSelectedBankAccountsCount')) {
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
}

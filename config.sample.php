<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
// 1100-ERP System Configuration Loader (WP1: contains NO secrets - safe to copy)
// SETUP: copy this file to config.php (or let the setup wizard generate it),
// then put real values in environment variables or a .env file (see .env.example).
// Lookup order: real environment > APP_SECRETS_FILE (outside web root) > ./.env
// Missing or placeholder values fail fast with a generic 503 (no details).

require_once __DIR__ . '/includes/dotenv.php';

$secretsFile = getenv('APP_SECRETS_FILE') ?: '';
if ($secretsFile !== '' && is_readable($secretsFile)) {
    loadEnv($secretsFile);
}
loadEnv(__DIR__ . '/.env');

function erpEnv($name, $default = '')
{
    $v = getenv($name);
    return ($v === false) ? $default : $v;
}

function erpFailClosed($hint)
{
    error_log('ERP config error: ' . $hint);
    http_response_code(503);
    die('<h1>Service Unavailable</h1><p>The application is not configured correctly. The server administrator has been notified.</p>');
}

// Placeholder values that must never reach production.
$badValues = ['', 'changeme', 'change_me', 'password', 'your_db_host', 'your_db_user', 'your_db_pass', 'your_db_name'];

$__db = [
    'DB_HOST' => trim(erpEnv('DB_HOST', '')),
    'DB_NAME' => trim(erpEnv('DB_NAME', '')),
    'DB_USER' => trim(erpEnv('DB_USER', '')),
];
// DB_PASS may legitimately be empty on local dev (XAMPP); presence of the
// other three is mandatory, placeholders are rejected everywhere.
$__db['DB_PASS'] = erpEnv('DB_PASS', '');
foreach ($__db as $__k => $__v) {
    if ($__k !== 'DB_PASS' && $__v === '') {
        erpFailClosed('missing database setting ' . $__k);
    }
    if ($__v !== '' && in_array(strtolower($__v), $badValues, true)) {
        erpFailClosed('placeholder database setting ' . $__k);
    }
}

define('DB_HOST', $__db['DB_HOST']);
define('DB_NAME', $__db['DB_NAME']);
define('DB_USER', $__db['DB_USER']);
define('DB_PASS', $__db['DB_PASS']);
define('DB_PREFIX', erpEnv('DB_PREFIX', 'erp_'));
unset($__db, $__k, $__v, $badValues, $secretsFile);

// Establish Database Connection (errors are logged, never shown)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    error_log('ERP database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('<h1>Service Unavailable</h1><p>The application is not configured correctly. The server administrator has been notified.</p>');
}

// Helper function to get settings
function getSetting($key, $default = '') {
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

// Helper function to save/update a setting
function setSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([$key, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("setSetting error for key '$key': " . $e->getMessage());
        return false;
    }
}

// Load basic settings into constants
define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));
define('COMPANY_ADDRESS', getSetting('company_address', ''));
define('COMPANY_PHONE', getSetting('company_phone', ''));
define('COMPANY_EMAIL', getSetting('company_email', ''));
define('COMPANY_WEBSITE', getSetting('company_website', ''));
define('COMPANY_LOGO', getSetting('company_logo', ''));
define('VAT_RATE', (float)getSetting('vat_rate', 7.5));
define('CURRENCY_SYMBOL', getSetting('currency_symbol', '₦'));

// Additional Display Settings
define('THEME_COLOR', getSetting('theme_color', '#0076BE'));
define('FOOTER_TEXT', getSetting('footer_text', 'We appreciate your business! Thank you'));
define('PDF_QUALITY', getSetting('pdf_quality', 'high'));
define('DEFAULT_PAYMENT_TERMS', getSetting('default_payment_terms', 'Due on Receipt'));

// Bank account helper functions
function getBankAccountsForDisplay() {
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

function getAllBankAccounts() {
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

function getSelectedBankAccountsCount() {
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

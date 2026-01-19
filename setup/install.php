<?php
/**
 * 1100-ERP Setup Wizard - Installation Processor
 * Handles backend installation logic and AJAX requests
 */

// CRITICAL: Start session FIRST before any output
session_start();

// Suppress any output except JSON
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Get action
$action = $_POST['action'] ?? '';

// Response array
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'test_connection':
            testDatabaseConnection();
            break;

        case 'create_database':
            createDatabase();
            break;

        case 'import_schema':
            importSchema();
            break;

        case 'create_admin':
            createAdminUser();
            break;

        case 'init_settings':
            initializeSettings();
            break;

        case 'finalize':
            finalizeInstallation();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;

// ============================================
// FUNCTIONS
// ============================================

function testDatabaseConnection()
{
    global $response;

    $host = $_POST['db_host'] ?? '';
    $dbname = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    if (empty($host) || empty($dbname) || empty($user)) {
        throw new Exception('Please provide all database credentials');
    }

    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $response['success'] = true;
            $response['message'] = 'Connection successful. Database will be created.';
        } else {
            $response['success'] = true;
            $response['message'] = 'Connection successful. Database exists.';
            $response['database_exists'] = true;
        }
    } catch (PDOException $e) {
        throw new Exception('Connection failed: ' . $e->getMessage());
    }
}

function createDatabase()
{
    global $response;

    $host = $_POST['db_host'] ?? '';
    $dbname = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $response['success'] = true;
        $response['message'] = 'Database created successfully';
    } catch (PDOException $e) {
        throw new Exception('Failed to create database: ' . $e->getMessage());
    }
}

function importSchema()
{
    global $response;

    $dbname = $_POST['db_name'] ?? '';
    $host = $_POST['db_host'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    try {
        // Connect to database
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Read schema file
        $schemaFile = dirname(__DIR__) . '/database/install-schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('Schema file not found');
        }

        $sql = file_get_contents($schemaFile);

        // CRITICAL: Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Parse SQL line by line (memory efficient)
        $lines = explode("\n", $sql);
        $current_statement = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || substr($line, 0, 2) == '--' || substr($line, 0, 2) == '/*') {
                continue;
            }

            $current_statement .= ' ' . $line;

            // Execute when statement ends with semicolon
            if (substr(trim($line), -1) == ';') {
                $stmt_to_exec = trim($current_statement);
                if (!empty($stmt_to_exec)) {
                    $pdo->exec($stmt_to_exec);
                }
                $current_statement = '';
            }
        }

        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $response['success'] = true;
        $response['message'] = 'Database schema imported successfully';
    } catch (PDOException $e) {
        throw new Exception('Failed to import schema: ' . $e->getMessage());
    }
}

function createAdminUser()
{
    global $response;

    $dbname = $_POST['db_name'] ?? '';
    $host = $_POST['db_host'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    $fullName = $_POST['admin_name'] ?? '';
    $username = $_POST['admin_username'] ?? '';
    $email = $_POST['admin_email'] ?? '';
    $adminPassword = $_POST['admin_password'] ?? '';

    if (empty($fullName) || empty($username) || empty($email) || empty($adminPassword)) {
        throw new Exception('All admin fields are required');
    }

    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Clear existing users (for reinstallation)
        $pdo->exec("DELETE FROM users");

        // Hash password
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role, is_active)
            VALUES (?, ?, ?, ?, 'admin', 1)
        ");

        $stmt->execute([$username, $hashedPassword, $fullName, $email]);

        $response['success'] = true;
        $response['message'] = 'Admin account created successfully';
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            throw new Exception('Username or email already exists');
        }
        throw new Exception('Failed to create admin user: ' . $e->getMessage());
    }
}

function initializeSettings()
{
    global $response;

    $dbname = $_POST['db_name'] ?? '';
    $host = $_POST['db_host'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    $companyName = $_POST['company_name'] ?? 'Your Company Name';
    $companyEmail = $_POST['company_email'] ?? '';
    $companyPhone = $_POST['company_phone'] ?? '';
    $companyAddress = $_POST['company_address'] ?? '';
    $vatRate = $_POST['vat_rate'] ?? '7.5';
    $currencySymbol = $_POST['currency_symbol'] ?? '₦';

    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $settings = [
            'company_name' => $companyName,
            'company_email' => $companyEmail,
            'company_phone' => $companyPhone,
            'company_address' => $companyAddress,
            'vat_rate' => $vatRate,
            'currency_symbol' => $currencySymbol,
            'items_per_page' => '25',
            'audit_retention_days' => '90',
            'log_user_actions' => '1',
            'log_document_create' => '1',
            'log_document_edit' => '1',
            'log_document_delete' => '1',
            'log_user_management' => '1',
            'log_settings_changes' => '1',
            'log_email_sent' => '1'
        ];

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $response['success'] = true;
        $response['message'] = 'Settings initialized successfully';
    } catch (PDOException $e) {
        throw new Exception('Failed to initialize settings: ' . $e->getMessage());
    }
}

function finalizeInstallation()
{
    global $response;

    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPassword = $_POST['db_password'] ?? '';
    $dbPrefix = $_POST['db_prefix'] ?? 'erp_';

    try {
        // Generate config.php
        $configContent = generateConfig($dbHost, $dbName, $dbUser, $dbPassword, $dbPrefix);
        $configFile = dirname(__DIR__) . '/config.php';

        // Force cleanup of existing file
        if (file_exists($configFile)) {
            @chmod($configFile, 0777); // Try to make writable
            @unlink($configFile);      // Try to delete
        }

        if (!file_put_contents($configFile, $configContent)) {
            throw new Exception('Failed to write config file. Check directory permissions or try deleting config.php manually.');
        }

        // Create lock file
        $lockFile = __DIR__ . '/lock';
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        @chmod($configFile, 0444);

        $response['success'] = true;
        $response['message'] = 'Installation finalized successfully';
        $response['redirect'] = '../login.php';
    } catch (Exception $e) {
        throw new Exception('Failed to finalize installation: ' . $e->getMessage());
    }
}

function generateConfig($host, $dbname, $user, $password, $prefix)
{
    $date = date('Y-m-d H:i:s');

    return <<<PHP
<?php
// 1100-ERP System Configuration File
// Generated by Setup Wizard on: $date

// Database Configuration
define('DB_HOST', '$host');
define('DB_NAME', '$dbname');
define('DB_USER', '$user');
define('DB_PASS', '$password');
define('DB_PREFIX', '$prefix');

// Establish Database Connection
try {
    \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch(PDOException \$e) {
    die("Database connection failed: " . \$e->getMessage());
}

// Helper function to get settings
function getSetting(\$key, \$default = '') {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        \$stmt->execute([\$key]);
        \$result = \$stmt->fetch();
        return \$result ? \$result['setting_value'] : \$default;
    } catch (PDOException \$e) {
        return \$default;
    }
}

// Load basic settings into constants
define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));
define('COMPANY_ADDRESS', getSetting('company_address', ''));
define('COMPANY_PHONE', getSetting('company_phone', ''));
define('COMPANY_EMAIL', getSetting('company_email', ''));
define('COMPANY_WEBSITE', getSetting('company_website', ''));
define('VAT_RATE', (float)getSetting('vat_rate', 7.5));
define('CURRENCY_SYMBOL', getSetting('currency_symbol', '₦'));

// Bank account helper functions
function getBankAccountsForDisplay() {
    global \$pdo;
    try {
        \$stmt = \$pdo->query("
            SELECT * FROM bank_accounts 
            WHERE is_active = 1 AND show_on_documents = 1 
            ORDER BY display_order ASC
        ");
        return \$stmt->fetchAll();
    } catch (PDOException \$e) {
        return [];
    }
}

function getAllBankAccounts() {
    global \$pdo;
    try {
        \$stmt = \$pdo->query("
            SELECT * FROM bank_accounts 
            WHERE is_active = 1 
            ORDER BY display_order ASC
        ");
        return \$stmt->fetchAll();
    } catch (PDOException \$e) {
        return [];
    }
}

function getSelectedBankAccountsCount() {
    global \$pdo;
    try {
        \$stmt = \$pdo->query("
            SELECT COUNT(*) as count 
            FROM bank_accounts 
            WHERE is_active = 1 AND show_on_documents = 1
        ");
        \$result = \$stmt->fetch();
        return \$result['count'];
    } catch (PDOException \$e) {
        return 0;
    }
}
PHP;
}
?>
<?php
/**
 * 1100-ERP Setup Wizard - Installation Processor
 * Handles backend installation logic and AJAX requests
 */

// CRITICAL: Start session FIRST before any output
session_start();

require_once __DIR__ . '/../../includes/passwords.php';

// Block once configured, locked, or marked installed. Delete maintenance/setup/ after install.
require_once __DIR__ . '/install-guard.php';
if (install_is_installed()) {
    // Narrow Step-7 exceptions: the final schema check and a CSRF token for
    // the cleanup form, both for logged-in admins only. (config.php exists
    // this late, so the normal session bootstrap works.)
    // Everything else stays refused.
    if (in_array(($_POST['action'] ?? ''), ['final_check', 'csrf_token'], true)) {
        require_once dirname(__DIR__, 2) . '/config.php';
        require_once dirname(__DIR__, 2) . '/includes/session-check.php';
        if (!isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: Administrator privileges required.']);
            exit;
        }
        if (($_POST['action'] ?? '') === 'csrf_token') {
            require_once dirname(__DIR__, 2) . '/includes/security.php';
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'csrf_token' => generateCSRFToken()]);
            exit;
        }
        runFinalCheck();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Already installed. Delete maintenance/setup/ to reinstall.']);
    exit;
}
// Every install action needs the server-side claim file (WP0-C).
install_require_claim_ajax();

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

        case 'final_check':
            // Step-7 schema check (also reachable pre-finalize behind the token).
            runFinalCheck();
            break;

        case 'csrf_token':
            require_once dirname(__DIR__, 2) . '/includes/security.php';
            $response['success'] = true;
            $response['csrf_token'] = generateCSRFToken();
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

// WP6: strict validators for DB identifiers. db_name interpolates into
// CREATE DATABASE / USE (cannot be bound), and db_host into the PDO DSN,
// so both are allow-list validated on every read below.
function validatedDbName($raw)
{
    $name = trim((string)$raw);
    if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $name)) {
        throw new Exception('Invalid database name (letters, digits, _ and $ only, max 64 chars).');
    }
    return $name;
}

function validatedDbHost($raw)
{
    $host = trim((string)$raw);
    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)
        || preg_match('/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9\-.]{0,251}[A-Za-z0-9])?$/', $host)) {
        return $host;
    }
    throw new Exception('Invalid database host.');
}

function testDatabaseConnection()
{
    global $response;

    $host = validatedDbHost($_POST['db_host'] ?? '');
    $dbname = validatedDbName($_POST['db_name'] ?? '');
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
        $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
        $stmt->execute([$dbname]);
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

    $host = validatedDbHost($_POST['db_host'] ?? '');
    $dbname = validatedDbName($_POST['db_name'] ?? '');
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

    $dbname = validatedDbName($_POST['db_name'] ?? '');
    $host = validatedDbHost($_POST['db_host'] ?? '');
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    try {
        // Connect to database
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Read schema file
        $schemaFile = dirname(__DIR__, 2) . '/database/install-schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('Schema file not found');
        }

        $sql = file_get_contents($schemaFile);

        // CRITICAL: Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Parse SQL line by line (memory efficient)
        $lines = explode("\n", $sql);
        $current_statement = '';

        $delimiter = ';';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments (if not inside a statement, though this simple check is usually safe enough for this schema)
            if (empty($line) || substr($line, 0, 2) == '--' || substr($line, 0, 2) == '/*') {
                continue;
            }

            // Handle DELIMITER command
            if (preg_match('/^DELIMITER\s+(\S+)/i', $line, $matches)) {
                $delimiter = $matches[1];
                continue;
            }

            $current_statement .= ' ' . $line;

            // Execute when statement ends with current delimiter
            if (substr($line, -strlen($delimiter)) == $delimiter) {
                // Remove the delimiter from the end
                $stmt_to_exec = trim(substr(trim($current_statement), 0, -strlen($delimiter)));

                if (!empty($stmt_to_exec)) {
                    try {
                        $pdo->exec($stmt_to_exec);
                    } catch (PDOException $e) {
                        // If table exists error, ignore (for idempotency)
                        if ($e->getCode() != '42S01') {
                            throw $e;
                        }
                    }
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

    $dbname = validatedDbName($_POST['db_name'] ?? '');
    $host = validatedDbHost($_POST['db_host'] ?? '');
    $user = $_POST['db_user'] ?? '';
    $password = $_POST['db_password'] ?? '';

    $fullName = $_POST['admin_name'] ?? '';
    $username = $_POST['admin_username'] ?? '';
    $email = $_POST['admin_email'] ?? '';
    $adminPassword = $_POST['admin_password'] ?? '';

    if (empty($fullName) || empty($username) || empty($email) || empty($adminPassword)) {
        throw new Exception('All admin fields are required');
    }

    // WP2 password policy (12+, blocklist).
    $problems = validatePasswordPolicy($adminPassword, null, $username);
    if ($problems) {
        throw new Exception(implode(' ', $problems));
    }

    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // NEVER wipe existing accounts: abort when any user already exists.
        // (A fresh schema import leaves users empty; anything else means this
        //  installer is running against a live database.)
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($existing > 0) {
            throw new Exception('Users table already has accounts. Aborting to protect existing users.');
        }

        // Hash password (using PASSWORD_ARGON2ID)
        $hashedPassword = hashPassword($adminPassword);

        // Insert the first-ever account as super_admin: someone must be able
        // to create super users, and the patcher backstop only runs at Step 7.
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role, is_active)
            VALUES (?, ?, ?, ?, 'super_admin', 1)
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

    $dbname = validatedDbName($_POST['db_name'] ?? '');
    $host = validatedDbHost($_POST['db_host'] ?? '');
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
            'company_website' => '',
            'company_tax_id' => '',
            'vat_rate' => $vatRate,
            'currency_symbol' => $currencySymbol,

            // Email Settings (Defaults)
            'email_method' => 'php_mail',
            'email_from_address' => 'noreply@yourcompany.com',
            'email_from_name' => 'Your Company Name',
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',

            // Display Settings
            'items_per_page' => '25',
            'show_dashboard_charts' => '1',
            'show_recent_activity' => '1',
            'pdf_quality' => 'high',
            'theme_color' => '#0076BE',
            'footer_text' => 'We appreciate your business! Thank you',

            // System Settings
            'quote_prefix' => 'QUOT-',
            'invoice_prefix' => 'INV-',
            'receipt_prefix' => 'REC-',
            'date_format' => 'd/m/Y',
            'auto_archive_days' => '0',

            // Audit Settings
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

/**
 * Step-7 final schema check: runs the shared SchemaPatcher and returns its
 * report as JSON. Replaces the deleted run-schema-update.php.
 */
function runFinalCheck()
{
    global $response, $pdo;

    try {
        require_once dirname(__DIR__, 2) . '/includes/schema-patcher.php';
        // $pdo comes from config.php on the installed path (imported as global);
        // otherwise fall back to posted credentials for the pre-finalize token path.
        if (!isset($pdo)) {
            $dsn = 'mysql:host=' . validatedDbHost($_POST['db_host'] ?? '') . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $_POST['db_user'] ?? '', $_POST['db_password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if (!empty($_POST['db_name'])) {
                $pdo->exec('USE `' . validatedDbName($_POST['db_name']) . '`');
            }
        }
        $entries = SchemaPatcher::run($pdo, dirname(__DIR__, 2));
        $response['success'] = true;
        $response['message'] = 'Final check complete';
        $response['entries'] = $entries;
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Final check failed: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

function finalizeInstallation()
{
    global $response;

    $dbHost = validatedDbHost($_POST['db_host'] ?? '');
    $dbName = validatedDbName($_POST['db_name'] ?? '');
    $dbUser = $_POST['db_user'] ?? '';
    $dbPassword = $_POST['db_password'] ?? '';
    $dbPrefix = $_POST['db_prefix'] ?? 'erp_';

    try {
        // WP1: secrets go to .env (gitignored, never committed); config.php is
        // a static secret-free loader. Reject line breaks (env injection).
        foreach (['db_host' => $dbHost, 'db_name' => $dbName, 'db_user' => $dbUser, 'db_password' => $dbPassword, 'db_prefix' => $dbPrefix] as $label => $val) {
            if (preg_match('/[\r\n]/', $val)) {
                throw new Exception('Invalid characters in ' . $label);
            }
        }
        $rootDir = dirname(__DIR__, 2);
        $envContent = "DB_HOST={$dbHost}\nDB_NAME={$dbName}\nDB_USER={$dbUser}\nDB_PASS={$dbPassword}\nDB_PREFIX={$dbPrefix}\n";
        if (!file_put_contents($rootDir . '/.env', $envContent)) {
            throw new Exception('Failed to write .env file. Check directory permissions.');
        }
        @chmod($rootDir . '/.env', 0600);

        // Static loader: config.sample.php contains no secrets, so copying it
        // is safe. All secrets live in .env (written above).
        $sampleFile = $rootDir . '/config.sample.php';
        if (!file_exists($sampleFile)) {
            throw new Exception('Installer files incomplete (config.sample.php missing).');
        }
        $configFile = $rootDir . '/config.php';
        if (file_exists($configFile)) {
            @chmod($configFile, 0777);
            @unlink($configFile);
        }
        if (!copy($sampleFile, $configFile)) {
            throw new Exception('Failed to write config file. Check directory permissions or try deleting config.php manually.');
        }

        // Create lock file
        $lockFile = __DIR__ . '/lock';
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        // Installed marker OUTSIDE maintenance/ (survives cleanup.php).
        // Wizard entries refuse to run while any of config.php, lock, or this
        // marker exists.
        $storageDir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        @file_put_contents($storageDir . '/installed', date('Y-m-d H:i:s'));
        try {
            $dsnMark = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdoMark = new PDO($dsnMark, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdoMark->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('installed_at', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([date('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            // Non-fatal: file marker above is the primary signal.
        }

        // The install claim is spent: it must never survive finalize.
        install_spend_claim();
        unset($_SESSION['install_token_ok']);

        @chmod($configFile, 0644);

        // Auto-login the new admin in this (wizard) session so Step 7
        // cleanup works immediately with no separate login.
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            if ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['full_name'] = $admin['full_name'];
                $_SESSION['role'] = 'admin';
            }
        } catch (Exception $e) {
            // Non-fatal: user logs in manually, cleanup runs from System Update instead.
        }

        $response['success'] = true;
        $response['message'] = 'Installation finalized successfully';
        $response['redirect'] = '../../login.php';
    } catch (Exception $e) {
        throw new Exception('Failed to finalize installation: ' . $e->getMessage());
    }
}
?>

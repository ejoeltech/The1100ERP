<?php
/**
 * Restore Endpoint for Setup Wizard
 * Allows restoring a backup (SQL or ZIP) using provided DB credentials.
 * Requires the one-time install token (WP0-C). Pending decision: remove from
 * the web wizard entirely and document CLI restore instead.
 */

header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../install-guard.php';
install_require_claim_ajax();

// Helper function to send error
function sendError($message)
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method');
}

// 1. Validate Input
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendError('No file uploaded or upload error');
}

$dbHost = $_POST['db_host'] ?? 'localhost';
$dbName = $_POST['db_name'] ?? '';
$dbUser = $_POST['db_user'] ?? '';
$dbPass = $_POST['db_password'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    sendError('Database credentials missing');
}

// WP6: db_name interpolates into CREATE DATABASE (cannot be bound);
// db_host into the PDO DSN. Strict allow-list on both.
if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $dbName)) {
    sendError('Invalid database name');
}
if (!($dbHost === 'localhost' || filter_var($dbHost, FILTER_VALIDATE_IP)
    || preg_match('/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9\-.]{0,251}[A-Za-z0-9])?$/', $dbHost))) {
    sendError('Invalid database host');
}

// 2. Test Connection
try {
    $dsn = "mysql:host=$dbHost;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Create DB if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    sendError("Database connection failed: " . $e->getMessage());
}

// 3. Process File
$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['sql', 'zip'])) {
    sendError('Invalid file type. Only .sql and .zip files are allowed.');
}

// Command configuration
$mysqlCommand = 'mysql';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $xamppMySQL = 'c:/xampp/mysql/bin/mysql.exe';
    if (file_exists($xamppMySQL)) {
        $mysqlCommand = '"' . $xamppMySQL . '"';
    }
}

try {
    $sqlFileToRestore = null;
    $tempDir = null;

    if ($ext === 'zip') {
        // Handle ZIP extraction
        $zip = new ZipArchive;
        $tempDir = sys_get_temp_dir() . '/setup_restore_' . uniqid();
        mkdir($tempDir);

        if ($zip->open($file['tmp_name']) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();

            // Look for database.sql or any .sql file
            $sqlFiles = glob($tempDir . '/*.sql');
            if (empty($sqlFiles)) {
                throw new Exception('No SQL file found inside the ZIP archive.');
            }
            $sqlFileToRestore = $sqlFiles[0];

            // Restore Uploads if detected
            // Logic: Move contents of extracted 'uploads' folder to system uploads
            $extractedUploads = $tempDir . '/uploads';
            if (is_dir($extractedUploads)) {
                $targetUploads = dirname(__DIR__, 3) . '/uploads';
                if (!is_dir($targetUploads))
                    mkdir($targetUploads, 0777, true);

                // Simple recursive copy/overwrite
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractedUploads, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $subPath = $iterator->getSubPathName();
                    $destination = $targetUploads . '/' . $subPath;
                    if ($item->isDir()) {
                        if (!is_dir($destination)) {
                            mkdir($destination);
                        }
                    } else {
                        copy($item, $destination);
                    }
                }
            }
        } else {
            throw new Exception('Failed to open ZIP file.');
        }
    } else {
        // Direct SQL file
        $sqlFileToRestore = $file['tmp_name'];
    }

    // Perform DB Restore
    $command = sprintf(
        '%s --host=%s --user=%s --password=%s %s < %s',
        $mysqlCommand,
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        '"' . $sqlFileToRestore . '"'
    );

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'cmd /c "' . $command . '"';
    }

    exec($command . ' 2>&1', $output, $returnVar);

    if ($returnVar !== 0) {
        throw new Exception('Restore failed: ' . implode("\n", $output));
    }

    // Write config.php
    $configContent = "<?php
define('DB_HOST', '$dbHost');
define('DB_NAME', '$dbName');
define('DB_USER', '$dbUser');
define('DB_PASS', '$dbPass');
define('COMPANY_NAME', 'Restored Company'); // Temporarily set
define('CURRENCY_SYMBOL', '₦'); // Default
?>";

    file_put_contents(dirname(__DIR__, 3) . '/config.php', $configContent);

    // Create lock file
    file_put_contents(__DIR__ . '/../lock', 'Installed via Restore on ' . date('Y-m-d H:i:s'));

    // Auto-login the first admin so Step 7 cleanup works with no separate login.
    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdoLogin = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdoLogin->query("SELECT id, username, full_name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        if ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['full_name'] = $admin['full_name'];
            $_SESSION['role'] = 'admin';
        }
    } catch (Exception $e) {
        // Non-fatal: user logs in manually instead.
    }

    echo json_encode(['success' => true, 'message' => 'System restored successfully']);

} catch (Exception $e) {
    sendError($e->getMessage());
}
?>
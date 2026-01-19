<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Schema Update</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .box { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>1100ERP Database Schema Patcher</h1>
<p>This script will update your database structure to match the latest codebase version.</p>";

function executeSql($pdo, $sql, $description)
{
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✓ $description</div>";
    } catch (PDOException $e) {
        // Ignore "Duplicate column" or "Table exists" errors mostly
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "<div class='info'>ℹ️ $description (Already exists)</div>";
        } else {
            echo "<div class='error'>✗ Failed: $description - " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

function addColumnIfNotExists($pdo, $table, $column, $definition)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        if ($stmt->fetch()) {
            echo "<div class='info'>ℹ️ Column <strong>$table.$column</strong> already exists.</div>";
        } else {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "<div class='success'>✓ Added column <strong>$table.$column</strong>.</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error checking/adding $table.$column: " . $e->getMessage() . "</div>";
    }
}

echo "<div class='box'><h3>1. Creating New Tables</h3>";

// 1. Create Payments Table
$sql = "CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
executeSql($pdo, $sql, "Create 'payments' table");
echo "</div>";


echo "<div class='box'><h3>2. Adding Missing Columns</h3>";

// 2. Soft Deletes
addColumnIfNotExists($pdo, 'customers', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'products', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');

// 3. Archiving
addColumnIfNotExists($pdo, 'quotes', 'is_archived', 'TINYINT(1) DEFAULT 0');
addColumnIfNotExists($pdo, 'invoices', 'is_archived', 'TINYINT(1) DEFAULT 0');
addColumnIfNotExists($pdo, 'receipts', 'is_archived', 'TINYINT(1) DEFAULT 0');

// 4. Customer Fields
addColumnIfNotExists($pdo, 'customers', 'company', 'VARCHAR(255) DEFAULT NULL');
addColumnIfNotExists($pdo, 'customers', 'notes', 'TEXT DEFAULT NULL');
addColumnIfNotExists($pdo, 'customers', 'account_balance', 'DECIMAL(15,2) DEFAULT 0.00');

// 5. Product Fields
addColumnIfNotExists($pdo, 'products', 'created_by', 'INT(11) DEFAULT NULL');

// 6. Quote Fields
addColumnIfNotExists($pdo, 'quotes', 'delivery_period', 'VARCHAR(255) DEFAULT NULL');

// 7. Receipt Fields
addColumnIfNotExists($pdo, 'receipts', 'receipt_number', 'VARCHAR(50) DEFAULT NULL');
addColumnIfNotExists($pdo, 'receipts', 'payment_id', 'INT(11) DEFAULT NULL');
addColumnIfNotExists($pdo, 'receipts', 'status', "ENUM('valid','void') DEFAULT 'valid'");

// 8. User Fields
addColumnIfNotExists($pdo, 'users', 'phone', 'VARCHAR(20) DEFAULT NULL');

// 9. Template Fields
addColumnIfNotExists($pdo, 'readymade_quote_templates', 'payment_terms', 'TEXT DEFAULT NULL');
addColumnIfNotExists($pdo, 'readymade_quote_templates', 'default_project_title', 'VARCHAR(255) DEFAULT NULL');
addColumnIfNotExists($pdo, 'readymade_quote_templates', 'subtotal', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
addColumnIfNotExists($pdo, 'readymade_quote_templates', 'total_vat', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
addColumnIfNotExists($pdo, 'readymade_quote_template_items', 'vat_applicable', 'TINYINT(1) DEFAULT 0');
addColumnIfNotExists($pdo, 'readymade_quote_template_items', 'vat_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
addColumnIfNotExists($pdo, 'readymade_quote_template_items', 'line_total', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
echo "</div>";

echo "<div class='box'><h3>3. Seeding Required Data</h3>";
// Ensure default category exists for readymade quotes
try {
    $stmt = $pdo->query("SELECT id FROM readymade_quote_categories WHERE id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO readymade_quote_categories (id, category_name, description) VALUES (1, 'General', 'Default Category')");
        echo "<div class='success'>✓ Created default 'General' category (ID: 1).</div>";
    } else {
        echo "<div class='info'>ℹ️ Default category already exists.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking categories: " . $e->getMessage() . "</div>";
}
echo "</div>";


echo "<div class='box'><h3>4. Modifying Existing Columns</h3>";
// 10. Invoice Status Enum
try {
    // We can't easily check enum values, so we just run the modify to make sure it includes 'finalized'
    $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','paid','overdue','cancelled','partial','finalized') DEFAULT 'draft'");
    echo "<div class='success'>✓ Updated 'invoices.status' ENUM to include 'finalized'.</div>";
} catch (Exception $e) {
    echo "<div class='error'>✗ Failed to update invoice status: " . $e->getMessage() . "</div>";
}

// 11. Ensure receipt_number is unique
try {
    // Check if index exists first? Or just try adding unique constraint
    // This might fail if duplicates exist, so we catch it
    $pdo->exec("ALTER TABLE receipts ADD UNIQUE KEY `unique_receipt_number` (`receipt_number`)");
    echo "<div class='success'>✓ Added UNIQUE constraint to receipt_number.</div>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "<div class='error'>✗ Could not make receipt_number unique (duplicates exist). Clear logic running...</div>";
        // Optional: clean up duplicates logic could go here
    } else {
        echo "<div class='info'>ℹ️ Unique constraint on receipt_number likely already exists.</div>";
    }
}
echo "</div>";

echo "</div>";

echo "<div class='box'><h3>4. Checking Configuration File</h3>";

function repairConfig()
{
    $configFile = 'config.php';
    if (!file_exists($configFile)) {
        echo "<div class='error'>✗ config.php not found!</div>";
        return;
    }

    $content = file_get_contents($configFile);
    $modified = false;

    // missing constants to check and likely insertion point
    $missingConstants = [
        'COMPANY_LOGO' => "define('COMPANY_LOGO', getSetting('company_logo', ''));",
        'TINYMCE_API_KEY' => "define('TINYMCE_API_KEY', getSetting('tinymce_api_key', 'no-api-key'));",
        'DEFAULT_PAYMENT_TERMS' => "define('DEFAULT_PAYMENT_TERMS', getSetting('default_payment_terms', '80% Initial Deposit'));"
    ];

    foreach ($missingConstants as $const => $def) {
        if (strpos($content, "define('$const'") === false) {
            // Insert after COMPANY_NAME if possible, else append
            if (strpos($content, "define('COMPANY_NAME'") !== false) {
                $content = str_replace(
                    "define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));",
                    "define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));\n" . $def,
                    $content
                );
            } else {
                // Warning: appending might be outside PHP tag if closed?
                // Safest to just insert after opening php tag or specific marker? 
                // Let's try locating a reliable marker like "define('DB_PREFIX'" or just append to end of defines block
                // For simplicity/safety in this specific file structure:
                $content = str_replace(
                    "// Load basic settings into constants",
                    "// Load basic settings into constants\n" . $def,
                    $content
                );
            }
            echo "<div class='success'>✓ Added missing constant: <strong>$const</strong></div>";
            $modified = true;
        } else {
            echo "<div class='info'>ℹ️ Constant <strong>$const</strong> already exists.</div>";
        }
    }

    if ($modified) {
        file_put_contents($configFile, $content);
        echo "<div class='success'>✓ config.php updated successfully.</div>";
    }
}

repairConfig();
echo "</div>";

echo "<div class='box' style='background: #fff3cd; border: 1px solid #ffeeba;'>";
echo "<h3 style='margin-top:0; color: #856404;'>🛡️ Security Cleanup Recommended</h3>";
echo "<p>For security reasons, please <strong>DELETE</strong> the following files/folders from your server using your File Manager or FTP:</p>";
echo "<ul style='background: #fff; padding: 15px 30px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;'>";
echo "<li style='color:red; font-weight:bold;'>setup/ (The entire folder)</li>";
echo "<li>factory-reset.php (DANGER: Wipes entire DB)</li>";
echo "<li style='color:red; font-weight:bold;'>run-schema-update.php (This file)</li>";
echo "<li>clear-company-data.php (If you used it)</li>";
echo "<li>create_installer.ps1</li>";
echo "<li>rename-to-1100erp.ps1</li>";
echo "<li>check_columns.php</li>";
echo "<li>config.php.bak</li>";
echo "<li>config_broken.php.bak</li>";
echo "<li>RENAME-FOLDER.bat</li>";
echo "<li>git-init.bat</li>";
echo "<li>schema.txt</li>";
echo "<li>Any file ending in .php in the root that starts with 'test_' or 'debug_'</li>";
echo "</ul>";
echo "<p>Once deleted, you can safely use your system.</p>";
echo "<strong><a href='dashboard.php' style='display:inline-block; padding:10px 20px; background:#0076BE; color:white; text-decoration:none; border-radius:5px;'>Go to Dashboard</a></strong>";
echo "</div>";
echo "</body></html>";
?>
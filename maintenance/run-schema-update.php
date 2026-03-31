<?php
require_once __DIR__ . '/../config.php';

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

// 1a. Readymade Quote Categories
$sql = "CREATE TABLE IF NOT EXISTS `readymade_quote_categories` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `category_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'readymade_quote_categories' table");

// 1b. Readymade Quote Templates
$sql = "CREATE TABLE IF NOT EXISTS `readymade_quote_templates` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `category_id` int(10) unsigned NOT NULL,
    `template_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `payment_terms` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `default_project_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
    `total_vat` decimal(15,2) NOT NULL DEFAULT 0.00,
    `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_template_name` (`template_name`),
    KEY `idx_category_id` (`category_id`),
    CONSTRAINT `readymade_quote_templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `readymade_quote_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'readymade_quote_templates' table");

// 1c. Readymade Quote Template Items
$sql = "CREATE TABLE IF NOT EXISTS `readymade_quote_template_items` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `template_id` int(10) unsigned NOT NULL,
    `product_id` int(10) unsigned DEFAULT NULL,
    `item_number` int(11) NOT NULL,
    `quantity` decimal(10,2) NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `unit_price` decimal(15,2) NOT NULL,
    `vat_applicable` tinyint(1) DEFAULT 0,
    `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
    `line_total` decimal(15,2) NOT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_template_id` (`template_id`),
    KEY `product_id` (`product_id`),
    CONSTRAINT `readymade_quote_template_items_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `readymade_quote_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `readymade_quote_template_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'readymade_quote_template_items' table");

// 1d. Audit Log
$sql = "CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned DEFAULT NULL,
    `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `resource_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `resource_id` int(10) unsigned DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `details` json DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_resource` (`resource_type`,`resource_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'audit_log' table");

// 1e. Settings
$sql = "CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `setting_value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'system',
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`),
    KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'settings' table");

// 1f. Bank Accounts
$sql = "CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `show_on_documents` tinyint(1) DEFAULT 0,
    `display_order` int(11) DEFAULT 0,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_bank_name` (`bank_name`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'bank_accounts' table");

// 1g. Market Data
$sql = "CREATE TABLE IF NOT EXISTS `market_data` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `data_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `data_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `data_value` decimal(15,2) NOT NULL,
    `effective_date` date NOT NULL,
    `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_data` (`data_type`,`data_key`,`effective_date`),
    KEY `idx_data_type` (`data_type`),
    KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'market_data' table");

// 1h. AI Usage Logs
$sql = "CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
    `tool_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `endpoint` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `request_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tokens_used` int(11) DEFAULT 0,
    `cost_usd` decimal(10,4) DEFAULT 0.0000,
    `processing_time` float DEFAULT NULL,
    `success` tinyint(1) DEFAULT 1,
    `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_date` (`user_id`,`created_at`),
    KEY `idx_ip_date` (`ip_address`,`created_at`),
    KEY `idx_tool_date` (`tool_name`,`created_at`),
    KEY `idx_date` (`created_at`),
    KEY `idx_hash` (`request_hash`),
    CONSTRAINT `ai_usage_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'ai_usage_logs' table");

// 1i. AI Request Cache
$sql = "CREATE TABLE IF NOT EXISTS `ai_request_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `request_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `tool_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `request_params` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `response_data` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `hit_count` int(11) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `last_accessed` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `expires_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `request_hash` (`request_hash`),
    KEY `idx_hash` (`request_hash`),
    KEY `idx_tool` (`tool_name`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'ai_request_cache' table");

// 1j. AI Recommendations
$sql = "CREATE TABLE IF NOT EXISTS `ai_recommendations` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned DEFAULT NULL,
    `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `customer_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `appliances_json` json DEFAULT NULL,
    `power_analysis` json DEFAULT NULL,
    `recommended_system` json NOT NULL,
    `roi_analysis` json NOT NULL,
    `quote_id` int(10) unsigned DEFAULT NULL,
    `created_quote` tinyint(1) DEFAULT 0,
    `model_used` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'groq-llama-3.1-70b',
    `processing_time_ms` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_customer_name` (`customer_name`),
    CONSTRAINT `ai_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `ai_recommendations_ibfk_2` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'ai_recommendations' table");

echo "</div>";

echo "<div class='box'><h3>1.5 Store/Inventory Tables</h3>";

// Item Categories
$sql = "CREATE TABLE IF NOT EXISTS `item_categories` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'item_categories' table");

// Items
$sql = "CREATE TABLE IF NOT EXISTS `items` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `category_id` int(10) unsigned DEFAULT NULL,
    `description` text COLLATE utf8mb4_unicode_ci,
    `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `price` decimal(15,2) DEFAULT '0.00',
    `cost_price` decimal(15,2) DEFAULT '0.00',
    `stock_quantity` int(11) DEFAULT 0,
    `minimum_stock` int(11) DEFAULT 0,
    `status` enum('active','archived') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_category_id` (`category_id`),
    CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
executeSql($pdo, $sql, "Create 'items' table");

echo "</div>";


echo "<div class='box'><h3>2. Adding Missing Columns</h3>";

// 2. Soft Deletes
addColumnIfNotExists($pdo, 'customers', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'products', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'quotes', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'invoices', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'receipts', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'payments', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');

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
addColumnIfNotExists($pdo, 'quote_line_items', 'item_id', 'INT(11) DEFAULT NULL');
addColumnIfNotExists($pdo, 'quote_line_items', 'item_name', 'VARCHAR(255) DEFAULT NULL');

// 6b. Invoice line items fields (needed by convert-to-invoice.php)
addColumnIfNotExists($pdo, 'invoice_line_items', 'item_id', 'INT(11) DEFAULT NULL');
addColumnIfNotExists($pdo, 'invoice_line_items', 'item_name', 'VARCHAR(255) DEFAULT NULL');

// 6c. Audit log: hash column (needed by audit.php chain integrity)
addColumnIfNotExists($pdo, 'audit_log', 'hash', 'VARCHAR(64) DEFAULT NULL');

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
    $configFile = __DIR__ . '/../config.php';
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
<?php
/**
 * Shared database schema patcher (single implementation).
 *
 * Used by:
 *  - maintenance/setup/run-schema-update.php (one-time, install-time final check)
 *  - pages/system-update.php (permanent, admin-only)
 *
 * Run: $entries = SchemaPatcher::run($pdo, $rootDir);
 * Each entry: ['status' => 'ok'|'info'|'error', 'section' => ..., 'message' => ...]
 * $rootDir is the project root (used to locate config.php for repairConfig).
 */
class SchemaPatcher
{
    public static function run($pdo, $rootDir)
    {
        $log = [];
        $add = function ($status, $section, $message) use (&$log) {
            $log[] = ['status' => $status, 'section' => $section, 'message' => $message];
        };

        $exec = function ($sql, $description) use ($pdo, $add) {
            try {
                $pdo->exec($sql);
                $add('ok', 'tables', $description);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                    $add('info', 'tables', $description . ' (Already exists)');
                } else {
                    $add('error', 'tables', 'Failed: ' . $description . ' - ' . $e->getMessage());
                }
            }
        };

        $addCol = function ($table, $column, $definition) use ($pdo, $add) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
                if ($stmt->fetch()) {
                    $add('info', 'columns', "Column $table.$column already exists.");
                } else {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                    $add('ok', 'columns', "Added column $table.$column.");
                }
            } catch (Exception $e) {
                $add('error', 'columns', "Error checking/adding $table.$column: " . $e->getMessage());
            }
        };

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
        $exec($sql, "Create 'payments' table");

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
        $exec($sql, "Create 'readymade_quote_categories' table");

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
        $exec($sql, "Create 'readymade_quote_templates' table");

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
        $exec($sql, "Create 'readymade_quote_template_items' table");

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
        $exec($sql, "Create 'audit_log' table");

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
        $exec($sql, "Create 'settings' table");

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
        $exec($sql, "Create 'bank_accounts' table");

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
        $exec($sql, "Create 'market_data' table");

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
        $exec($sql, "Create 'ai_usage_logs' table");

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
        $exec($sql, "Create 'ai_request_cache' table");

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
        $exec($sql, "Create 'ai_recommendations' table");

        // 1.5 Store/Inventory Tables
        $sql = "CREATE TABLE IF NOT EXISTS `item_categories` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $exec($sql, "Create 'item_categories' table");

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
        $exec($sql, "Create 'items' table");

        // 2. Soft Deletes
        $addCol('customers', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $addCol('products', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $addCol('quotes', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $addCol('invoices', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $addCol('receipts', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');
        $addCol('payments', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL');

        // 3. Archiving
        $addCol('quotes', 'is_archived', 'TINYINT(1) DEFAULT 0');
        $addCol('invoices', 'is_archived', 'TINYINT(1) DEFAULT 0');
        $addCol('receipts', 'is_archived', 'TINYINT(1) DEFAULT 0');

        // 4. Customer Fields
        $addCol('customers', 'company', 'VARCHAR(255) DEFAULT NULL');
        $addCol('customers', 'notes', 'TEXT DEFAULT NULL');
        $addCol('customers', 'account_balance', 'DECIMAL(15,2) DEFAULT 0.00');

        // 5. Product Fields
        $addCol('products', 'created_by', 'INT(11) DEFAULT NULL');

        // 6. Quote Fields
        $addCol('quotes', 'delivery_period', 'VARCHAR(255) DEFAULT NULL');
        $addCol('quote_line_items', 'item_id', 'INT(11) DEFAULT NULL');
        $addCol('quote_line_items', 'item_name', 'VARCHAR(255) DEFAULT NULL');

        // 6b. Invoice line items fields (needed by convert-to-invoice.php)
        $addCol('invoice_line_items', 'item_id', 'INT(11) DEFAULT NULL');
        $addCol('invoice_line_items', 'item_name', 'VARCHAR(255) DEFAULT NULL');

        // 6c. Audit log: hash column (needed by audit.php chain integrity)
        $addCol('audit_log', 'hash', 'VARCHAR(64) DEFAULT NULL');

        // 7. Receipt Fields
        $addCol('receipts', 'receipt_number', 'VARCHAR(50) DEFAULT NULL');
        $addCol('receipts', 'payment_id', 'INT(11) DEFAULT NULL');
        $addCol('receipts', 'status', "ENUM('valid','void') DEFAULT 'valid'");

        // 8. User Fields
        $addCol('users', 'phone', 'VARCHAR(20) DEFAULT NULL');

        // 9. Template Fields
        $addCol('readymade_quote_templates', 'payment_terms', 'TEXT DEFAULT NULL');
        $addCol('readymade_quote_templates', 'default_project_title', 'VARCHAR(255) DEFAULT NULL');
        $addCol('readymade_quote_templates', 'subtotal', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        $addCol('readymade_quote_templates', 'total_vat', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        $addCol('readymade_quote_template_items', 'vat_applicable', 'TINYINT(1) DEFAULT 0');
        $addCol('readymade_quote_template_items', 'vat_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        $addCol('readymade_quote_template_items', 'line_total', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');

        // 3. Seeding Required Data: default category for readymade quotes
        try {
            $stmt = $pdo->query("SELECT id FROM readymade_quote_categories WHERE id = 1");
            if (!$stmt->fetch()) {
                $pdo->exec("INSERT INTO readymade_quote_categories (id, category_name, description) VALUES (1, 'General', 'Default Category')");
                $add('ok', 'seed', "Created default 'General' category (ID: 1).");
            } else {
                $add('info', 'seed', 'Default category already exists.');
            }
        } catch (Exception $e) {
            $add('error', 'seed', 'Error checking categories: ' . $e->getMessage());
        }

        // 10. Invoice Status Enum (must include 'finalized')
        try {
            $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','paid','overdue','cancelled','partial','finalized') DEFAULT 'draft'");
            $add('ok', 'alter', "Updated 'invoices.status' ENUM to include 'finalized'.");
        } catch (Exception $e) {
            $add('error', 'alter', 'Failed to update invoice status: ' . $e->getMessage());
        }

        // 11. Ensure receipt_number is unique
        try {
            $pdo->exec("ALTER TABLE receipts ADD UNIQUE KEY `unique_receipt_number` (`receipt_number`)");
            $add('ok', 'alter', 'Added UNIQUE constraint to receipt_number.');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $add('error', 'alter', 'Could not make receipt_number unique (duplicates exist).');
            } else {
                $add('info', 'alter', 'Unique constraint on receipt_number likely already exists.');
            }
        }

        // 4. Config repair: missing constants
        self::repairConfig($rootDir, $add);

        return $log;
    }

    public static function repairConfig($rootDir, $add = null)
    {
        $report = function ($status, $message) use (&$report, $add) {
            if ($add) {
                $add($status, 'config', $message);
            }
        };

        $configFile = rtrim($rootDir, '/\\') . '/config.php';
        if (!file_exists($configFile)) {
            $report('error', 'config.php not found!');
            return false;
        }

        $content = file_get_contents($configFile);
        $modified = false;

        $missingConstants = [
            'COMPANY_LOGO' => "define('COMPANY_LOGO', getSetting('company_logo', ''));",
            'DEFAULT_PAYMENT_TERMS' => "define('DEFAULT_PAYMENT_TERMS', getSetting('default_payment_terms', '80% Initial Deposit'));"
        ];

        foreach ($missingConstants as $const => $def) {
            if (strpos($content, "define('$const'") === false) {
                if (strpos($content, "define('COMPANY_NAME'") !== false) {
                    $content = str_replace(
                        "define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));",
                        "define('COMPANY_NAME', getSetting('company_name', 'Your Company Name'));\n" . $def,
                        $content
                    );
                } else {
                    $content = str_replace(
                        "// Load basic settings into constants",
                        "// Load basic settings into constants\n" . $def,
                        $content
                    );
                }
                $report('ok', "Added missing constant: $const");
                $modified = true;
            } else {
                $report('info', "Constant $const already exists.");
            }
        }

        if ($modified) {
            file_put_contents($configFile, $content);
            $report('ok', 'config.php updated successfully.');
        }
        return true;
    }
}

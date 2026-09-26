<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
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

        // 1f. Leads & Follow-up (retired database/run-leads-migration.php).        // INSERT IGNORE (not ON DUPLICATE KEY UPDATE): re-runs must never
        // wipe configured tokens/secrets.
        $exec("CREATE TABLE IF NOT EXISTS `leads` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `phone` varchar(50) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `source` enum('web','whatsapp','phone','referral','walk-in','other') NOT NULL DEFAULT 'web',
    `interest` text DEFAULT NULL,
    `message` text DEFAULT NULL,
    `status` enum('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new',
    `assigned_to` varchar(255) DEFAULT NULL,
    `converted_to` int(10) unsigned DEFAULT NULL,
    `followup_count` int(10) unsigned NOT NULL DEFAULT 0,
    `last_followup_at` datetime DEFAULT NULL,
    `next_followup_at` datetime DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_next_followup` (`next_followup_at`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'leads' table");
        try {
            $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('lead_followup_enabled', '1'),
('lead_followup_interval_days', '3'),
('lead_followup_max_attempts', '3'),
('lead_digest_enabled', '1'),
('telegram_bot_token', ''),
('telegram_chat_id', ''),
('whatsapp_verify_token', ''),
('whatsapp_app_secret', '')");
            $add('ok', 'seed', 'Ensured leads/follow-up settings keys.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding leads settings: ' . $e->getMessage());
        }

        // 1f2. HR permission seeds (WP3; mirrors database/wp3-hr-permissions.sql).
        // Seeded ONLY into groups that hold none of the six HR keys, so a
        // deliberate admin revocation is never re-added by a later patch run.
        try {
            $hrSeeds = [
                'admin' => ['hr_view', 'hr_manage', 'leave_manage', 'payroll_view', 'payroll_run', 'recruitment_manage'],
                'manager' => ['hr_view', 'hr_manage', 'leave_manage', 'payroll_view', 'recruitment_manage'],
                'accountant' => ['payroll_view', 'payroll_run'],
            ];
            $hrKeys = ['hr_view', 'hr_manage', 'leave_manage', 'payroll_view', 'payroll_run', 'recruitment_manage'];
            $inList = "'" . implode("','", $hrKeys) . "'";
            $insPerm = $pdo->prepare("INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, ? FROM `user_groups` WHERE name = ?");
            foreach ($hrSeeds as $gname => $perms) {
                $has = $pdo->query("SELECT COUNT(*) FROM group_permissions gp JOIN user_groups g ON g.id = gp.group_id WHERE g.name = '$gname' AND gp.permission_key IN ($inList)")->fetchColumn();
                if ((int)$has > 0) {
                    $add('info', 'seed', "Group '$gname' already customised; HR permissions untouched.");
                    continue;
                }
                foreach ($perms as $perm) {
                    $insPerm->execute([$perm, $gname]);
                }
            }
            $add('ok', 'seed', 'Ensured HR group permissions.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding HR permissions: ' . $e->getMessage());
        }

        // 1f3. Payment UI permission seeds (WP3-C; mirrors wp3-hr-permissions.sql).
        // Same no-clobber rule: only fill groups holding none of these keys.
        try {
            $paySeeds = [
                'admin' => ['view_payments', 'create_payment'],
                'manager' => ['view_payments', 'create_payment'],
                'accountant' => ['view_payments', 'create_payment'],
                'sales_rep' => ['create_payment'],
            ];
            $payKeys = "'view_payments','create_payment'";
            foreach ($paySeeds as $gname => $perms) {
                $has = $pdo->query("SELECT COUNT(*) FROM group_permissions gp JOIN user_groups g ON g.id = gp.group_id WHERE g.name = '$gname' AND gp.permission_key IN ($payKeys)")->fetchColumn();
                if ((int)$has > 0) {
                    $add('info', 'seed', "Group '$gname' already customised; payment permissions untouched.");
                    continue;
                }
                foreach ($perms as $perm) {
                    $insPerm->execute([$perm, $gname]);
                }
            }
            $add('ok', 'seed', 'Ensured payment group permissions.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding payment permissions: ' . $e->getMessage());
        }

        // 1f4. Resurrected dead permissions (WP3-C): manage_customers,
        // manage_products, manage_leads, view_reports are required across the
        // app but were never defined or seeded (denied everyone but super_admin).
        try {
            $deadSeeds = [
                'admin' => ['manage_customers', 'manage_products', 'manage_leads', 'view_reports'],
                'manager' => ['manage_customers', 'manage_products', 'manage_leads', 'view_reports'],
                'accountant' => ['manage_customers', 'view_reports'],
                'sales_rep' => ['manage_customers'],
            ];
            $deadKeys = "'manage_customers','manage_products','manage_leads','view_reports'";
            foreach ($deadSeeds as $gname => $perms) {
                $has = $pdo->query("SELECT COUNT(*) FROM group_permissions gp JOIN user_groups g ON g.id = gp.group_id WHERE g.name = '$gname' AND gp.permission_key IN ($deadKeys)")->fetchColumn();
                if ((int)$has > 0) {
                    $add('info', 'seed', "Group '$gname' already customised; module permissions untouched.");
                    continue;
                }
                foreach ($perms as $perm) {
                    $insPerm->execute([$perm, $gname]);
                }
            }
            $add('ok', 'seed', 'Ensured module group permissions.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding module permissions: ' . $e->getMessage());
        }

        // 1g. Bank Accounts
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

        // get_market_data() function (retired ai-features-migration.sql / fix_functions.php).
        // api/ai/calculate-roi.php calls it; CREATE IF NOT EXISTS keeps re-runs safe.
        // NOTE: requires the DB user to hold CREATE ROUTINE; failure is logged, not fatal.
        try {
            $pdo->exec("CREATE FUNCTION IF NOT EXISTS get_market_data(
                p_data_type VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci,
                p_data_key VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
            ) RETURNS DECIMAL(15,2)
            DETERMINISTIC
            BEGIN
                DECLARE v_value DECIMAL(15,2);
                SELECT data_value INTO v_value
                FROM market_data
                WHERE data_type = p_data_type COLLATE utf8mb4_unicode_ci
                AND data_key = p_data_key COLLATE utf8mb4_unicode_ci
                AND effective_date <= CURDATE()
                ORDER BY effective_date DESC
                LIMIT 1;
                RETURN COALESCE(v_value, 0);
            END");
            $add('ok', 'alter', "Ensured function 'get_market_data' exists.");
        } catch (Exception $e) {
            $add('error', 'alter', "Failed to ensure function 'get_market_data': " . $e->getMessage());
        }

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

        // 1.6 Internal Accessories Store (tools/consumables owned by the business, not for sale)
        $sql = "CREATE TABLE IF NOT EXISTS `accessories` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(100) DEFAULT NULL,
    `category` varchar(100) DEFAULT 'General',
    `description` text,
    `unit` varchar(50) DEFAULT 'pcs',
    `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` int(11) NOT NULL DEFAULT 0,
    `minimum_stock` int(11) NOT NULL DEFAULT 0,
    `location` varchar(255) DEFAULT NULL,
    `condition_status` enum('good','fair','faulty') NOT NULL DEFAULT 'good',
    `status` enum('active','archived') NOT NULL DEFAULT 'active',
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_acc_name` (`name`),
    KEY `idx_acc_category` (`category`),
    KEY `idx_acc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $exec($sql, "Create 'accessories' table");

        $sql = "CREATE TABLE IF NOT EXISTS `accessory_transactions` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `accessory_id` int(11) unsigned NOT NULL,
    `type` enum('in','out','adjust') NOT NULL,
    `quantity` int(11) NOT NULL,
    `balance_after` int(11) NOT NULL DEFAULT 0,
    `technician` varchar(255) DEFAULT NULL,
    `purpose` varchar(255) DEFAULT NULL,
    `notes` text,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_tr_acc` (`accessory_id`),
    KEY `idx_tr_created` (`created_at`),
    CONSTRAINT `accessory_transactions_ibfk_1` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $exec($sql, "Create 'accessory_transactions' table");

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
        $exec("CREATE TABLE IF NOT EXISTS `proposals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `customer_name` varchar(255) DEFAULT NULL,
    `system_specs` text DEFAULT NULL,
    `content` longtext DEFAULT NULL,
    `status` varchar(50) DEFAULT 'draft',
    `converted_quote_id` int(10) unsigned DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'proposals' table");
        $addCol('proposals', 'created_by', 'INT(11) DEFAULT NULL');

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
        $addCol('users', 'signature_file', 'VARCHAR(255) DEFAULT NULL');

        // 8a. Document ownership columns (retired add_created_by_column.php).
        // install-schema.sql already creates these on fresh installs.
        foreach (['quotes', 'invoices', 'receipts'] as $docTable) {
            $addCol($docTable, 'created_by', 'INT UNSIGNED DEFAULT NULL');
            try {
                $fk = $docTable . '_created_by_fk';
                $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$docTable' AND CONSTRAINT_NAME = '$fk'");
                if (!$stmt->fetch()) {
                    // Only add when no FK already covers created_by -> users(id)
                    $stmt2 = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$docTable' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users'");
                    if (!$stmt2->fetch()) {
                        $pdo->exec("ALTER TABLE `$docTable` ADD CONSTRAINT `$fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL");
                        $add('ok', 'alter', "Added FK $docTable.created_by -> users.");
                    } else {
                        $add('info', 'alter', "FK $docTable.created_by -> users already exists.");
                    }
                } else {
                    $add('info', 'alter', "FK $docTable.created_by -> users already exists.");
                }
            } catch (Exception $e) {
                $add('error', 'alter', "Failed to add $docTable.created_by FK: " . $e->getMessage());
            }
        }

        // 8b. Product catalog columns (retired update_products_table.php).
        $addCol('products', 'product_code', 'VARCHAR(50) DEFAULT NULL');
        $addCol('products', 'category', "VARCHAR(100) DEFAULT 'General'");
        try {
            $stmt = $pdo->query("SHOW INDEX FROM products WHERE Key_name = 'idx_product_code'");
            if (!$stmt->fetch()) {
                // Backfill codes first so a UNIQUE index cannot fail on NULLs/dupes
                $pdo->exec("UPDATE products SET product_code = CONCAT('PRD-', LPAD(id, 4, '0')) WHERE product_code IS NULL OR product_code = ''");
                $pdo->exec("ALTER TABLE products ADD UNIQUE INDEX idx_product_code (product_code)");
                $add('ok', 'alter', 'Added UNIQUE index products.product_code (codes backfilled).');
            } else {
                $add('info', 'alter', 'UNIQUE index products.product_code already exists.');
            }
        } catch (Exception $e) {
            $add('error', 'alter', 'Failed to add products.product_code index: ' . $e->getMessage());
        }

        // 8b. User Groups & Permissions (standard groups + per-user overrides)
        try {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','manager','sales_rep','accountant','viewer') DEFAULT 'sales_rep'");
            $add('ok', 'alter', "Extended 'users.role' ENUM with super_admin/accountant/viewer.");
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'already') !== false) {
                $add('info', 'alter', 'users.role ENUM already extended.');
            } else {
                $add('error', 'alter', 'Failed to extend users.role: ' . $msg);
            }
        }
        $addCol('users', 'group_id', 'INT(11) UNSIGNED DEFAULT NULL');
        $addCol('users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addCol('users', 'mfa_secret', 'TEXT DEFAULT NULL');
        $addCol('users', 'mfa_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addCol('users', 'mfa_enrolled_at', 'DATETIME DEFAULT NULL');
        $exec("CREATE TABLE IF NOT EXISTS `mfa_recovery_codes` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL,
    `code_hash` varchar(255) NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_mfa_rc_user` (`user_id`),
    CONSTRAINT `mfa_recovery_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'mfa_recovery_codes' table");
        $exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
    `session_hash` char(64) NOT NULL,
    `user_id` int(10) unsigned NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `last_seen` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`session_hash`),
    KEY `idx_us_user` (`user_id`),
    CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'user_sessions' table");
        $exec("CREATE TABLE IF NOT EXISTS `user_invites` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL,
    `token_hash` char(64) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token_hash` (`token_hash`),
    KEY `idx_invite_user` (`user_id`),
    KEY `idx_invite_expiry` (`expires_at`),
    CONSTRAINT `user_invites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'user_invites' table");
        $exec("CREATE TABLE IF NOT EXISTS `auth_throttle` (
    `bucket` varchar(128) NOT NULL,
    `window_start` datetime NOT NULL,
    `attempts` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`bucket`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'auth_throttle' table");
        foreach ([
            "ALTER TABLE `hr_onboarding_codes` ADD COLUMN `code_hash` CHAR(64) DEFAULT NULL",
            "ALTER TABLE `hr_onboarding_codes` ADD COLUMN `expires_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `hr_onboarding_codes` ADD COLUMN `failed_attempts` INT(11) NOT NULL DEFAULT 0",
        ] as $ddl) {
            try {
                $pdo->exec($ddl);
                $add('ok', 'columns', 'Applied onboarding hardening column.');
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') !== false) {
                    $add('info', 'columns', 'Onboarding hardening column already exists.');
                } else {
                    $add('error', 'columns', 'Failed onboarding column: ' . $e->getMessage());
                }
            }
        }
        try {
            $pdo->exec("UPDATE `hr_onboarding_codes` SET `code_hash` = SHA2(`code`, 256) WHERE `code_hash` IS NULL");
            $pdo->exec("UPDATE `hr_onboarding_codes` SET `expires_at` = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE `expires_at` IS NULL");
            $add('ok', 'seed', 'Backfilled onboarding code hashes/expiry.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed onboarding backfill: ' . $e->getMessage());
        }
        $exec("CREATE TABLE IF NOT EXISTS `user_groups` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `primary_role` varchar(20) NOT NULL DEFAULT 'viewer',
    `is_system` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_group_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'user_groups' table");
        $exec("CREATE TABLE IF NOT EXISTS `group_permissions` (
    `group_id` int(11) unsigned NOT NULL,
    `permission_key` varchar(60) NOT NULL,
    PRIMARY KEY (`group_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'group_permissions' table");
        // Add FK only if missing (avoids duplicate-constraint errors on re-run)
        try {
            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_permissions' AND CONSTRAINT_NAME = 'group_permissions_ibfk_1'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `group_permissions` ADD CONSTRAINT `group_permissions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE");
                $add('ok', 'alter', 'Added FK group_permissions -> user_groups.');
            } else {
                $add('info', 'alter', 'FK group_permissions -> user_groups already exists.');
            }
        } catch (Exception $e) {
            $add('error', 'alter', 'Failed to add group_permissions FK: ' . $e->getMessage());
        }
        $exec("CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
    `user_id` int(10) unsigned NOT NULL,
    `permission_key` varchar(60) NOT NULL,
    `granted` tinyint(1) NOT NULL DEFAULT 1,
    `created_by` int(10) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", "Create 'user_permission_overrides' table");
        try {
            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_permission_overrides' AND CONSTRAINT_NAME = 'user_permission_overrides_ibfk_1'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `user_permission_overrides` ADD CONSTRAINT `user_permission_overrides_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
                $add('ok', 'alter', 'Added FK user_permission_overrides -> users.');
            } else {
                $add('info', 'alter', 'FK user_permission_overrides -> users already exists.');
            }
        } catch (Exception $e) {
            $add('error', 'alter', 'Failed to add user_permission_overrides FK: ' . $e->getMessage());
        }
        // Seed standard groups + permissions (idempotent)
        try {
            $pdo->exec("INSERT IGNORE INTO `user_groups` (`name`, `description`, `primary_role`, `is_system`) VALUES
('super_admin', 'Full system control including access management', 'super_admin', 1),
('admin', 'Manage users, settings and all documents', 'admin', 1),
('manager', 'View all documents, manage team workflows', 'manager', 1),
('accountant', 'Invoices, payments and exports', 'accountant', 1),
('sales_rep', 'Own quotes and customers only', 'sales_rep', 1),
('viewer', 'Read-only access', 'viewer', 1)");
            $adminPerms = "'manage_users','create_user','edit_user','delete_user','toggle_user_status','manage_access','manage_settings','view_audit_log','view_all_documents','create_quote','create_document','edit_quote','edit_invoice','edit_document','edit_finalized','delete_quote','delete_invoice','delete_receipt','delete_document','archive_document','convert_to_invoice','generate_receipt','send_email','email_document','edit_own_profile','change_own_password','view_system_dashboard','view_team_dashboard','view_personal_dashboard','manage_store','manage_accessories','manage_hr','manage_payments','export_data'";
            $managerPerms = "'view_all_documents','create_quote','create_document','edit_quote','edit_invoice','edit_document','convert_to_invoice','generate_receipt','send_email','email_document','edit_own_profile','change_own_password','view_team_dashboard','view_personal_dashboard','manage_store','manage_accessories','manage_hr','manage_payments','export_data'";
            $accountantPerms = "'view_all_documents','create_quote','create_document','convert_to_invoice','generate_receipt','send_email','email_document','edit_own_profile','change_own_password','view_personal_dashboard','manage_payments','export_data'";
            $salesPerms = "'create_quote','create_document','edit_quote','edit_invoice','edit_document','send_email','email_document','edit_own_profile','change_own_password','view_personal_dashboard'";
            $viewerPerms = "'view_all_documents','edit_own_profile','change_own_password','view_personal_dashboard'";
            $seeds = ['admin' => $adminPerms, 'manager' => $managerPerms, 'accountant' => $accountantPerms, 'sales_rep' => $salesPerms, 'viewer' => $viewerPerms];
            foreach ($seeds as $gname => $plist) {
                // Insert each perm individually (IGNORE keeps idempotent)
                foreach (explode(',', str_replace(chr(39), '', $plist)) as $perm) {
                    $perm = trim($perm);
                    $pdo->exec("INSERT IGNORE INTO `group_permissions` (`group_id`, `permission_key`) SELECT id, '$perm' FROM `user_groups` WHERE name = '$gname'");
                }
            }
            // Link users without group to matching group; ensure at least one super_admin
            $pdo->exec("UPDATE `users` u JOIN `user_groups` g ON g.name = u.role SET u.group_id = g.id WHERE u.group_id IS NULL");
            $count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
            if (!$count) {
                $pdo->exec("UPDATE `users` SET `role` = 'super_admin', `group_id` = (SELECT id FROM `user_groups` WHERE name = 'super_admin') WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1");
            }
            $add('ok', 'seed', 'Seeded standard user groups and permissions.');
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding user groups: ' . $e->getMessage());
        }

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

        // 3b. Seed the readymade solar template (retired populate-solar-template.php).
        // Idempotent: skips when the template name already exists.
        try {
            $stmt = $pdo->prepare("SELECT id FROM readymade_quote_categories WHERE category_name = ?");
            $stmt->execute(['Solar Installation']);
            $solarCat = $stmt->fetch();
            if (!$solarCat) {
                $stmt = $pdo->prepare("INSERT INTO readymade_quote_categories (category_name, description, is_active) VALUES (?, ?, 1)");
                $stmt->execute(['Solar Installation', 'Solar power system installations']);
                $solarCatId = (int)$pdo->lastInsertId();
            } else {
                $solarCatId = (int)$solarCat['id'];
            }
            $stmt = $pdo->prepare("SELECT id FROM readymade_quote_templates WHERE template_name = ?");
            $stmt->execute(['8kVA Hybrid Solar System']);
            if (!$stmt->fetch()) {
                $solarItems = [
                    ['8 kva Hybrid Inverter', 1, 700000.00],
                    ['10KWH Lithium Battery', 1, 2100000.00],
                    ['620W solar panels', 12, 135000.00],
                    ['Instalation acccessories', 1, 420000.00],
                    ['Installation', 1, 350000.00],
                ];
                $subtotal = 0;
                foreach ($solarItems as $si) {
                    $subtotal += $si[1] * $si[2];
                }
                $stmt = $pdo->prepare("INSERT INTO readymade_quote_templates (category_id, template_name, description, subtotal, total_vat, grand_total, is_active) VALUES (?, ?, ?, ?, 0, ?, 1)");
                $stmt->execute([$solarCatId, '8kVA Hybrid Solar System', 'Complete 8kVA Hybrid Solar System installation package', $subtotal, $subtotal]);
                $solarTplId = (int)$pdo->lastInsertId();
                $itemStmt = $pdo->prepare("INSERT INTO readymade_quote_template_items (template_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) VALUES (?, ?, ?, ?, ?, 0, 0, ?)");
                $n = 1;
                foreach ($solarItems as $si) {
                    $itemStmt->execute([$solarTplId, $n++, $si[1], $si[0], $si[2], $si[1] * $si[2]]);
                }
                $add('ok', 'seed', "Seeded readymade template '8kVA Hybrid Solar System' (5 items).");
            } else {
                $add('info', 'seed', "Readymade template '8kVA Hybrid Solar System' already exists.");
            }
        } catch (Exception $e) {
            $add('error', 'seed', 'Failed seeding solar template: ' . $e->getMessage());
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

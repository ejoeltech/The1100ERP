<?php
/**
 * Migration Script for Readymade Quotes (V7.0)
 * Creates tables and migrates data from tmp_migration to live 1100erp database
 */
require_once __DIR__ . '/../config.php';

try {
    $live_pdo = $pdo;

    // 1. Create Tables in Live DB (If missing)
    $tables_sql = [
        "CREATE TABLE IF NOT EXISTS `readymade_quote_categories` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_name` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `readymade_quote_templates` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` int(10) UNSIGNED NOT NULL,
            `template_name` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `payment_terms` text DEFAULT NULL,
            `default_project_title` varchar(255) DEFAULT NULL,
            `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
            `total_vat` decimal(15,2) NOT NULL DEFAULT 0.00,
            `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `category_id` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `readymade_quote_template_items` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `template_id` int(10) UNSIGNED NOT NULL,
            `product_id` int(10) UNSIGNED DEFAULT NULL,
            `item_number` int(11) NOT NULL,
            `quantity` decimal(10,2) NOT NULL,
            `description` text NOT NULL,
            `unit_price` decimal(15,2) NOT NULL,
            `vat_applicable` tinyint(1) DEFAULT 0,
            `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
            `line_total` decimal(15,2) NOT NULL,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `template_id` (`template_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables_sql as $sql) {
        $live_pdo->exec($sql);
    }
    echo "Tables created/verified.\n";

    // 2. Connect to Tmp DB
    $tmp_dsn = "mysql:host=" . DB_HOST . ";dbname=tmp_migration;charset=utf8mb4";
    $tmp_pdo = new PDO($tmp_dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $tables_to_migrate = [
        'readymade_quote_categories',
        'readymade_quote_templates',
        'readymade_quote_template_items'
    ];

    $live_pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables_to_migrate as $table) {
        echo "Migrating $table...\n";
        $stmt = $tmp_pdo->query("SELECT * FROM `$table` ");
        $rows = $stmt->fetchAll();
        
        if (empty($rows)) {
            echo "  No rows found.\n";
            continue;
        }

        $cols = array_keys($rows[0]);
        $col_list = implode('`, `', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $updates = [];
        foreach ($cols as $col) {
            $updates[] = "`$col` = VALUES(`$col`)";
        }
        $update_str = implode(', ', $updates);

        $sql = "INSERT INTO `$table` (`$col_list`) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $update_str";
        $stmt_insert = $live_pdo->prepare($sql);

        $count = 0;
        foreach ($rows as $row) {
            $stmt_insert->execute(array_values($row));
            $count++;
        }
        echo "  Migrated $count rows.\n";
    }

    $live_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

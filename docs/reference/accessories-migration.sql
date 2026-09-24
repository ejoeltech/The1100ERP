-- Internal Accessories Store migration
-- Tools, instruments and consumables owned by the business for servicing/installation.
-- NOT sold to customers (separate from store items / products).

CREATE TABLE IF NOT EXISTS `accessories` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accessory_transactions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

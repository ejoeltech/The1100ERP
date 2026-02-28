<?php
require_once 'config.php';

echo "Applying Schema Update v2...\n";

try {
    $sql = "
    ALTER TABLE `hr_employees`
    ADD COLUMN `passport_path` varchar(255) DEFAULT NULL AFTER `gender`,
    ADD COLUMN `signature_path` varchar(255) DEFAULT NULL AFTER `passport_path`,
    ADD COLUMN `secondary_phone` varchar(50) DEFAULT NULL AFTER `address`,
    ADD COLUMN `nin_number` varchar(50) DEFAULT NULL AFTER `account_name`,
    ADD COLUMN `bvn_number` varchar(50) DEFAULT NULL AFTER `nin_number`,
    ADD COLUMN `next_of_kin_name` varchar(255) DEFAULT NULL AFTER `emergency_contact_phone`,
    ADD COLUMN `next_of_kin_phone` varchar(50) DEFAULT NULL AFTER `next_of_kin_name`,
    ADD COLUMN `next_of_kin_relationship` varchar(100) DEFAULT NULL AFTER `next_of_kin_phone`,
    ADD COLUMN `reference_1_name` varchar(255) DEFAULT NULL AFTER `next_of_kin_relationship`,
    ADD COLUMN `reference_1_phone` varchar(50) DEFAULT NULL AFTER `reference_1_name`,
    ADD COLUMN `reference_1_org` varchar(255) DEFAULT NULL AFTER `reference_1_phone`,
    ADD COLUMN `reference_2_name` varchar(255) DEFAULT NULL AFTER `reference_1_org`,
    ADD COLUMN `reference_2_phone` varchar(50) DEFAULT NULL AFTER `reference_2_name`,
    ADD COLUMN `reference_2_org` varchar(255) DEFAULT NULL AFTER `reference_2_phone`;
    ";

    $pdo->exec($sql);
    echo "Schema Update Applied Successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Check if duplicate column error (means already applied partial?)
    if ($e->getCode() == '42S21') {
        echo "Note: Column already exists.\n";
    }
}

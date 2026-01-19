<?php
require_once 'config.php';
try {
    $c = $pdo->query("SHOW COLUMNS FROM quotes LIKE 'delivery_period'")->fetch();
    if (!$c) {
        $pdo->exec("ALTER TABLE quotes ADD COLUMN delivery_period VARCHAR(100) DEFAULT '10 Days' AFTER quote_date");
        echo "Column Added";
    } else {
        echo "Column Exists";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
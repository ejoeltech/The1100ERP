<?php
require_once 'config.php';
echo "COMPANY_LOGO Setting: " . getSetting('company_logo', 'NOT_SET') . "\n";
if (defined('COMPANY_LOGO')) {
    echo "Constant COMPANY_LOGO: " . COMPANY_LOGO . "\n";
} else {
    echo "Constant COMPANY_LOGO is NOT defined.\n";
}
?>
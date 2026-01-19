<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Clear Company Data</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .success { color: green; }
        .box { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
<h1>Clear Company Information</h1>";

try {
    // 1. Clear text settings
    $settingsToClear = [
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'company_website',
        'company_tax_id',
        'company_logo',
        'quote_terms',
        'quote_warranty',
        'invoice_footer'
    ];

    $placeholders = implode(',', array_fill(0, count($settingsToClear), '?'));
    $sql = "UPDATE settings SET setting_value = '' WHERE setting_key IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($settingsToClear);

    echo "<div class='success'>✓ Company settings cleared (Name, Email, Address, etc.)</div>";

    // 2. Clear Bank Accounts
    $pdo->exec("TRUNCATE TABLE bank_accounts");
    echo "<div class='success'>✓ All bank accounts removed</div>";

    echo "<br><div class='box'>
            <strong>Success!</strong> All company information and bank details have been wiped.<br>
            You can now go to Settings to enter new information.
            <br><br>
            <a href='dashboard.php'>Go to Dashboard</a>
          </div>";

} catch (Exception $e) {
    echo "<div style='color:red;'>Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
<?php
include '../includes/session-check.php';

// Only admins can save settings
if (function_exists('requirePermission')) {
    requirePermission('manage_settings');
} elseif (!function_exists('isAdmin') || !isAdmin()) {
    die('Access Denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

// Handle logo upload separately if file is present
if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    include 'upload-logo.php';
    exit;
}

try {
    // Settings to save
    $settings = [
        // Company Info
        'company_name' => $_POST['company_name'] ?? '',
        'company_address' => $_POST['company_address'] ?? '',
        'company_phone' => $_POST['company_phone'] ?? '',
        'company_email' => $_POST['company_email'] ?? '',
        'company_website' => $_POST['company_website'] ?? '',
        'company_tax_id' => $_POST['company_tax_id'] ?? '',

        // Email Settings
        'email_method' => $_POST['email_method'] ?? 'php_mail',
        'email_from_address' => $_POST['email_from_address'] ?? '',
        'email_from_name' => $_POST['email_from_name'] ?? '',
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',

        // System Settings
        'vat_rate' => $_POST['vat_rate'] ?? '7.5',
        'quote_prefix' => $_POST['quote_prefix'] ?? 'QUOT-',
        'invoice_prefix' => $_POST['invoice_prefix'] ?? 'INV-',
        'receipt_prefix' => $_POST['receipt_prefix'] ?? 'REC-',
        'currency_symbol' => $_POST['currency_symbol'] ?? '₦',
        'date_format' => $_POST['date_format'] ?? 'd/m/Y',
        'auto_archive_days' => isset($_POST['auto_archive_days']) ? '90' : '0',

        // Bank Accounts
        'bank1_name' => $_POST['bank1_name'] ?? '',
        'bank1_account' => $_POST['bank1_account'] ?? '',
        'bank2_name' => $_POST['bank2_name'] ?? '',
        'bank2_account' => $_POST['bank2_account'] ?? '',
        'bank_account_name' => $_POST['bank_account_name'] ?? '',

        // Display Settings
        'items_per_page' => $_POST['items_per_page'] ?? '25',
        'show_dashboard_charts' => isset($_POST['show_dashboard_charts']) ? '1' : '0',
        'show_recent_activity' => isset($_POST['show_recent_activity']) ? '1' : '0',
        'pdf_quality' => $_POST['pdf_quality'] ?? 'high',
        'theme_color' => $_POST['theme_color'] ?? '#0076BE',
        'footer_text' => $_POST['footer_text'] ?? 'We appreciate your business! Thank you',
        'tinymce_api_key' => $_POST['tinymce_api_key'] ?? 'no-api-key',

        // Quote Appendices
        'quote_terms' => $_POST['quote_terms'] ?? '',
        'quote_warranty' => $_POST['quote_warranty'] ?? '',

        // Integrations
        'groq_api_key' => $_POST['groq_api_key'] ?? '',
    ];

    // Create settings table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Save each setting
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    // Log audit if function exists
    if (function_exists('logAudit')) {
        logAudit('update', 'settings', null, ['settings_updated' => count($settings)]);
    }

    header('Location: ../pages/settings.php?success=1');
    exit;

} catch (Exception $e) {
    error_log("Save settings error: " . $e->getMessage());
    header('Location: ../pages/settings.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
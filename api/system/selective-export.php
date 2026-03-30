<?php
include '../../includes/session-check.php';
requirePermission('manage_settings');

/**
 * Selective Data Export API
 * Generates a JSON file containing specific categories of system data.
 */

try {
    $options = $_GET['options'] ?? [];
    if (empty($options)) {
        throw new Exception("No export options selected.");
    }

    $exportData = [
        'metadata' => [
            'version' => '1.0.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_full_name'] ?? 'System Admin',
            'categories' => $options
        ],
        'data' => []
    ];

    // Mapping of categories to tables
    $categoryMap = [
        'settings' => ['settings', 'bank_accounts'],
        'customers' => ['customers'],
        'inventory' => ['products'],
        'financials' => ['quotes', 'quote_line_items', 'invoices', 'invoice_line_items', 'receipts', 'payments'],
        'hr' => ['hr_employees', 'hr_departments', 'hr_designations']
    ];

    foreach ($options as $category) {
        if (!isset($categoryMap[$category])) continue;

        foreach ($categoryMap[$category] as $table) {
            try {
                $stmt = $pdo->query("SELECT * FROM `$table` shadow"); // Use alias shadow to avoid keyword issues if any
                // Re-query without alias if not needed, or just standard
                $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY 1");
                $exportData['data'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table might not exist in this version
                $exportData['data'][$table] = [];
            }
        }
    }

    // Clean data if needed (e.g., remove absolute paths if preferred, but for migration we keep everything)

    $json = json_encode($exportData, JSON_PRETTY_PRINT);
    
    // Set headers for download
    $filename = 'erp_migration_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $json;
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

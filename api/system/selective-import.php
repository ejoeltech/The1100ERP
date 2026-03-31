<?php
include '../../includes/session-check.php';
requirePermission('manage_settings');

/**
 * Selective Data Import API
 * Processes a migration JSON file and merges data into the database.
 */

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No valid file uploaded.");
    }

    $json = file_get_contents($_FILES['file']['tmp_name']);
    $importData = json_decode($json, true);

    if (!$importData || !isset($importData['metadata']) || !isset($importData['data'])) {
        throw new Exception("Invalid migration file format.");
    }

    $pdo->beginTransaction();

    // Disable foreign key checks for the duration of the import
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $importedTables = [];
    $skippedTables = [];
    $totalRecords = 0;

    foreach ($importData['data'] as $table => $rows) {
        if (empty($rows)) continue;

        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $skippedTables[] = $table;
            continue;
        }

        // Get columns from the first row
        $columns = array_keys($rows[0]);
        $colString = implode('`, `', $columns);
        $placeholderString = implode(', ', array_fill(0, count($columns), '?'));
        
        // Build ON DUPLICATE KEY UPDATE part
        $updateParts = [];
        foreach ($columns as $col) {
            $updateParts[] = "`$col` = VALUES(`$col`)";
        }
        $updateString = implode(', ', $updateParts);

        $sql = "INSERT INTO `$table` (`$colString`) VALUES ($placeholderString) 
                ON DUPLICATE KEY UPDATE $updateString";
        
        $stmt = $pdo->prepare($sql);

        foreach ($rows as $row) {
            $stmt->execute(array_values($row));
            $totalRecords++;
        }

        $importedTables[] = $table;
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->commit();

    header('Content-Type: application/json');
    $message = "Import successful! Processed $totalRecords records across " . count($importedTables) . " tables.";
    if (!empty($skippedTables)) {
        $message .= " Skipped " . count($skippedTables) . " missing tables: " . implode(', ', $skippedTables);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => [
            'tables' => $importedTables,
            'skipped' => $skippedTables,
            'records' => $totalRecords
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

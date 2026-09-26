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

        // WP6: identifiers cannot be bound — strict pattern + sensitive-table
        // blocklist (a crafted file must not write users/secrets/sessions).
        $table = (string)$table;
        $sensitive = ['users', 'user_invites', 'mfa_recovery_codes', 'user_sessions', 'auth_throttle', 'settings'];
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $table) || in_array(strtolower($table), $sensitive, true)) {
            $skippedTables[] = $table;
            continue;
        }

        // Check if table exists (WP6: SHOW ... LIKE takes no placeholders
        // on MariaDB — use information_schema with a bound value).
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $skippedTables[] = $table;
            continue;
        }

        // Get columns from the first row
        $columns = array_keys($rows[0]);

        // WP6: columns must be real columns of this table (pattern + existence).
        $realCols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $realCols[] = $c['Field'];
        }
        foreach ($columns as $col) {
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$col) || !in_array($col, $realCols, true)) {
                throw new Exception("Invalid column '$col' for table '$table'.");
            }
        }
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

<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

requirePermission('manage_settings');

header('Content-Type: application/json');

try {
    // 1. Get Schema Information
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $schemaInfo = "";
    $totalRows = 0;

    foreach ($tableNames as $table) {
        // Get Columns
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Indexes
        $stmt = $pdo->query("SHOW INDEX FROM `$table`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Row Count
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $rowCount = $stmt->fetchColumn();
        $totalRows += $rowCount;

        $colList = implode(', ', array_map(function ($c) {
            return $c['Field'] . '(' . $c['Type'] . ')'; }, $cols));
        $idxList = implode(', ', array_unique(array_column($indexes, 'Key_name')));

        $schemaInfo .= "Table: $table ($rowCount rows)\nColumns: $colList\nIndexes: $idxList\n\n";
    }

    // 2. AI Analysis
    $prompt = "Analyze this database schema. Look for:
    1. Missing indexes on foreign keys (ending in _id).
    2. Tables with high row counts but few indexes.
    3. Suggest SPECIFIC 'CREATE INDEX' SQL commands to optimize performance.
    
    SCHEMA:
    $schemaInfo";

    $analysis = callGroqAPI($prompt, "You are a Database Performance Expert. Focus on Indexing strategies. Output suggested SQL commands clearly.");

    echo json_encode([
        'status' => 'analyzed',
        'table_count' => count($tableNames),
        'total_rows' => $totalRows,
        'analysis' => $analysis
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
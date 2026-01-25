<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/db.php';

// Admin only
if ($_SESSION['role'] !== 'Admin') {
    header('Location: ../../dashboard.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="ai_usage_report_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['Date', 'Time', 'User', 'IP Address', 'Tool', 'Tokens', 'Cost (USD)', 'Processing Time (s)', 'Success']);

// Fetch usage data
$stmt = $pdo->query("
    SELECT 
        l.created_at,
        COALESCE(u.username, 'Public') as username,
        l.ip_address,
        l.tool_name,
        l.tokens_used,
        l.cost_usd,
        l.processing_time,
        l.success
    FROM ai_usage_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ORDER BY l.created_at DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        date('Y-m-d', strtotime($row['created_at'])),
        date('H:i:s', strtotime($row['created_at'])),
        $row['username'],
        $row['ip_address'],
        $row['tool_name'],
        $row['tokens_used'],
        number_format($row['cost_usd'], 4),
        number_format($row['processing_time'], 3),
        $row['success'] ? 'Yes' : 'No'
    ]);
}

fclose($output);
exit;
?>
<?php
require_once '../includes/security.php';
configureSessionCookies();
session_start();
require_once '../config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
requireLogin();
requirePermission('manage_settings');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_log_export_' . date('Y-m-d_His') . '.csv"');

try {
    // Fetch all audit logs
    $stmt = $pdo->query("
        SELECT 
            al.*,
            u.full_name as user_name,
            u.username
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
    ");

    $logs = $stmt->fetchAll();

    // Open output stream
    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Timestamp', 'User', 'Username', 'Action', 'Resource Type', 'Resource ID', 'IP Address', 'User Agent', 'Details']);

    // Data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['user_name'] ?? 'System',
            $log['username'] ?? 'N/A',
            $log['action'],
            $log['resource_type'] ?? 'N/A',
            $log['resource_id'] ?? 'N/A',
            $log['ip_address'],
            $log['user_agent'],
            $log['details']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting audit log: ' . $e->getMessage()
    ]);
    exit;
}
?>
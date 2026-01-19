<?php
session_start();
include '../includes/session-check.php';

// Check if user is logged in and is admin
requireLogin();

// Verify permission (admin or manage_settings)
if (function_exists('requirePermission')) {
    requirePermission('manage_settings');
} elseif (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $retention_days = (int) ($_POST['retention_days'] ?? 90);

    if ($retention_days <= 0) {
        throw new Exception('Invalid retention period');
    }

    // Calculate cutoff date
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

    // Delete old logs
    $stmt = $pdo->prepare("DELETE FROM audit_log WHERE created_at < ?");
    $stmt->execute([$cutoff_date]);

    $deleted_count = $stmt->rowCount();

    // Log this action
    // Log this action with user details
    if (function_exists('logAudit')) {
        logAudit('clear_audit_logs', 'system', null, [
            'retention_days' => $retention_days,
            'cutoff_date' => $cutoff_date,
            'deleted_count' => $deleted_count,
            'cleared_by' => $current_user['full_name'] ?? 'Unknown User'
        ]);
    }

    echo json_encode([
        'success' => true,
        'deleted_count' => $deleted_count,
        'message' => "Deleted $deleted_count audit log entries older than $retention_days days"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
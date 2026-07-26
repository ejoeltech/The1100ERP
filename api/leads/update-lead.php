<?php
// Update a lead's status / assignment (staff action from manage-leads.php).
include '../../includes/session-check.php';
requirePermission('manage_leads');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

try {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $assigned = trim($_POST['assigned_to'] ?? '');

    if (!$leadId) {
        throw new Exception('Lead ID required');
    }
    if (!in_array($status, ['new','contacted','qualified','converted','lost'], true)) {
        throw new Exception('Invalid status');
    }

    $pdo->prepare("UPDATE leads SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$status, $assigned, $leadId]);

    if (function_exists('logAudit')) {
        logAudit('update', 'lead', $leadId, ['status' => $status, 'assigned_to' => $assigned]);
    }

    header('Location: ../../pages/leads/manage-leads.php?updated=1');
    exit;

} catch (Exception $e) {
    header('Location: ../../pages/leads/manage-leads.php?error=' . urlencode($e->getMessage()));
    exit;
}

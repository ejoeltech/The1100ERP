<?php
include '../../includes/session-check.php';

// Deprecated: Redirects to the unified payment recorder (guarded endpoint).
$invoice_id = $_GET['invoice_id'] ?? '';
$param = $invoice_id ? '?invoice_id=' . urlencode($invoice_id) : '';
header("Location: payments/record-payment.php" . $param);
exit;
?>
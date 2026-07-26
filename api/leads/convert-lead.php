<?php
// Convert a lead into a customer (reuses the customers insert shape).
include '../../includes/session-check.php';
requirePermission('manage_customers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

try {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    if (!$leadId) {
        throw new Exception('Lead ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if (!$lead) {
        throw new Exception('Lead not found');
    }

    // Build customer fields from the lead.
    $name  = $lead['name'];
    $phone = $lead['phone'];
    $email = $lead['email'] ?: ($leadId . '@lead.local');
    $notes = "Converted from lead #" . $leadId
            . ($lead['interest'] ? "\nInterest: " . $lead['interest'] : '')
            . ($lead['message'] ? "\nMessage: " . $lead['message'] : '');

    // Avoid duplicate email collision.
    $chk = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) {
        throw new Exception('A customer with this email already exists');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO customers (customer_name, company, email, phone, address, city, notes, is_active)
         VALUES (?, '', ?, ?, '', '', ?, 1)"
    );
    $stmt->execute([$name, $email, $phone, $notes]);
    $customerId = $pdo->lastInsertId();

    // Mark lead converted.
    $stmt = $pdo->prepare("UPDATE leads SET status = 'converted', converted_to = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$customerId, $leadId]);

    if (function_exists('logAudit')) {
        logAudit('convert', 'lead', $leadId, ['to_customer' => $customerId]);
    }

    // Optionally auto-draft a quote if requested.
    if (!empty($_POST['auto_quote'])) {
        $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, status, created_at) VALUES (?, 'draft', NOW())");
        $stmt->execute([$customerId]);
        $quoteId = $pdo->lastInsertId();
        if (function_exists('logAudit')) {
            logAudit('create', 'quote', $quoteId, ['from_lead' => $leadId]);
        }
        header('Location: ../../pages/quotes/edit.php?id=' . $quoteId);
        exit;
    }

    header('Location: ../../pages/customers/manage-customers.php?converted=1');
    exit;

} catch (Exception $e) {
    header('Location: ../../pages/leads/manage-leads.php?error=' . urlencode($e->getMessage()));
    exit;
}

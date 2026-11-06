<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-json.php';
require_once '../../includes/ai-chat-query.php';

header('Content-Type: application/json');

// Only logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$context = $input['context'] ?? []; // Current page, etc.

if (empty($message)) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}
if (function_exists('mb_substr')) {
    $message = mb_substr((string)$message, 0, 4000);
} else {
    $message = substr((string)$message, 0, 4000);
}

try {
    // 1. Define System Context & Tools
    $systemPrompt = "You are an intelligent ERP Assistant for a Nigerian Solar Company (1100ERP).
    Your role is to help users navigate the system, query data, and perform actions.

    CURRENT USER: {$_SESSION['full_name']} (Role: {$_SESSION['role']})
    TODAY: " . date('Y-m-d l') . "

    CAPABILITIES:
    1. QUERY DATA: ask for one approved dataset with optional filters.
    2. ACTIONS: suggest navigating to specific pages.
    3. GENERAL: answer questions about solar energy using the Nigerian Solar Context.

    APPROVED DATASETS (never write SQL):
    - quotes: quote_number, quote_title, customer_name, salesperson, quote_date, grand_total, status
    - invoices: invoice_number, invoice_title, customer_name, salesperson, invoice_date, grand_total, amount_paid, balance_due, status
    - receipts: receipt_number, invoice_id, customer_name, amount_paid, payment_method, payment_date, status
    - payments: payment_number, customer_id, amount, payment_date, payment_method, reference
    - customers: customer_name, email, phone, city, state, account_balance
    - products: product_code, product_name, category, unit_price, is_active
    - leads: name, phone, email, source, status, created_at
    - employees: employee_code, full_name, email, phone, department, designation, employment_status, join_date

    RESPONSE FORMAT (JSON ONLY):
    {
        \"type\": \"text\" | \"query\" | \"action\",
        \"content\": \"Natural language response...\",
        \"dataset\": \"quotes\" (if type is query),
        \"filters\": {\"status\": \"...\", \"search\": \"...\", \"customer\": \"...\", \"date_from\": \"YYYY-MM-DD\", \"date_to\": \"YYYY-MM-DD\", \"limit\": 5},
        \"action_url\": \"/pages/...\" (if type is action)
    }

    RULES:
    - FOR DATA QUERIES: output type 'query' and choose exactly one dataset from the list. Never output SQL.
    - FOR NAVIGATION: output type 'action' and a relative URL.
    - Default limit is 5 and maximum is 20.
    - Output ONLY valid JSON.";

    // 2. Call AI
    $aiResponse = callGroqAPI($message, $systemPrompt, ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']]);

    // 3. Parse Response (fences/prose tolerated by the shared extractor)
    $parsed = aiExtractJson($aiResponse);

    if (!is_array($parsed)) {
        // Fallback for non-JSON response
        $responsePayload = [
            'type' => 'text',
            'content' => (string)$aiResponse
        ];
    } else {
        $responsePayload = $parsed;
    }

    // 4. Execute an approved dataset query. Raw SQL from the model is never
    // executed, even if the model falls back to the old format.
    $type = strtolower((string)($responsePayload['type'] ?? 'text'));
    if ($type === 'query') {
        try {
            $result = aiChatRunQuery($pdo, $responsePayload);
            $responsePayload['type'] = 'sql'; // widget renders data rows
            $responsePayload['data'] = $result['data'];
            $responsePayload['data_count'] = $result['count'];
            unset($responsePayload['filters'], $responsePayload['dataset']);
        } catch (Exception $e) {
            $responsePayload = [
                'type' => 'text',
                'content' => $e->getMessage(),
            ];
        }
    } elseif ($type === 'sql') {
        $responsePayload = [
            'type' => 'text',
            'content' => 'I can only run approved data queries. Please rephrase the request.',
        ];
    }

    echo json_encode($responsePayload);

} catch (PDOException $e) {
    error_log('AI chat data query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The data query failed. Please try again.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
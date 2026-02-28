<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

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

try {
    // 1. Define System Context & Tools
    $systemPrompt = "You are an intelligent ERP Assistant for a Nigerian Solar Company (1100ERP).
    Your role is to help users navigate the system, query data, and perform actions.

    CURRENT USER: {$_SESSION['full_name']} (Role: {$_SESSION['role']})
    TODAY: " . date('Y-m-d l') . "

    CAPABILITIES:
    1. QUERY DATA: You can generate SQL SELECT statements to fetch data.
    2. ACTIONS: You can suggest navigating to specific pages.
    3. GENERAL: Answer questions about solar energy using the Nigerian Solar Context.

    DATABASE SCHEMA (Simplified):
    - products (id, name, description, quantity, price_ngn, category)
    - quotes (id, quote_number, client_name, total_amount, status, created_at)
    - invoices (id, invoice_number, client_name, total_amount, status, created_at)
    - users (id, username, full_name, email, phone)
    - customers (id, name, email, phone, address)

    RESPONSE FORMAT (JSON ONLY):
    {
        \"type\": \"text\" | \"sql\" | \"action\",
        \"content\": \"Natural language response...\",
        \"sql\": \"SELECT ...\" (if type is sql),
        \"action_url\": \"/pages/...\" (if type is action)
    }
    
    RULES:
    - FOR DATA QUERIES: output type 'sql' and valid MySQL SELECT statement. LIMIT 5 by default.
    - FOR NAVIGATION: output type 'action' and the relative URL.
    - NEVER Output DELETE/UPDATE/INSERT SQL. Read-only.
    - Output ONLY valid JSON.";

    // 2. Call AI
    $aiResponse = callGroqAPI($message, $systemPrompt, ['temperature' => 0.2, 'response_format' => ['type' => 'json_object']]);

    // Clean up Markdown Code Blocks
    $cleanJson = $aiResponse;
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $aiResponse, $matches)) {
        $cleanJson = $matches[1];
    } elseif (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
        $cleanJson = $matches[0];
    }

    // 3. Parse Response
    $parsed = json_decode($cleanJson, true);

    if (!$parsed) {
        // Fallback for non-JSON response
        $responsePayload = [
            'type' => 'text',
            'content' => $aiResponse
        ];
    } else {
        $responsePayload = $parsed;
    }

    // 4. Execute SQL if present (Safe Mode)
    if ($responsePayload['type'] === 'sql' && !empty($responsePayload['sql'])) {
        // Security Check
        $sql = strtoupper(trim($responsePayload['sql']));
        if (strpos($sql, 'SELECT') !== 0 || strpos($sql, ';') !== false) {
            $responsePayload['type'] = 'text';
            $responsePayload['content'] = "I cannot execute this query for security reasons.";
            unset($responsePayload['sql']);
        } else {
            try {
                $stmt = $pdo->prepare($responsePayload['sql']);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $responsePayload['data'] = $data;
                $responsePayload['data_count'] = count($data);
            } catch (Exception $e) {
                $responsePayload['type'] = 'text';
                $responsePayload['content'] = "Error executing query: " . $e->getMessage();
            }
        }
    }

    echo json_encode($responsePayload);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
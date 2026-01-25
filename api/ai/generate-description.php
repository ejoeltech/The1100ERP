<?php
/**
 * AI Description Generator Endpoint
 * Generates professional descriptions for quotes/products using Groq AI
 */

header('Content-Type: application/json');
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

// Check permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get input
$data = json_decode(file_get_contents('php://input'), true);
$productName = $data['product_name'] ?? '';
$context = $data['context'] ?? ''; // e.g., 'solar installation', 'battery backup'

if (empty($productName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product name is required']);
    exit;
}

try {
    // Construct Prompt
    $systemPrompt = "You are a professional sales engineer for a Nigerian solar energy company. 
    Write a compelling, technical, yet accessible product description (max 2 sentences). 
    Focus on benefits, durability, and suitability for the Nigerian market (unstable power). 
    Use the currency symbol '₦' if mentioning value, but avoid specific prices unless asked.
    Do not use introductory phrases like 'Here is a description'. Just write the description.";

    $userPrompt = "Write a description for: '{$productName}'.";
    if (!empty($context)) {
        $userPrompt .= " Context: {$context}.";
    }

    // Call Groq API
    $description = callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 150]);

    echo json_encode(['success' => true, 'description' => $description]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

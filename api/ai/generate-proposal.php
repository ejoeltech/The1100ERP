<?php
/**
 * AI Proposal Generator Endpoint
 * Generates full project proposals using Groq AI
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

// Validate inputs
if (empty($data['inverter']) || empty($data['batteries'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Inverter and Battery details are required']);
    exit;
}

try {
    // Construct System Prompt
    $systemPrompt = "You are a Senior Solar Engineer at Bluedots Technologies in Nigeria. 
    Your task is to write a professional, persuasive, and technically accurate solar system proposal in HTML format.
    
    Structure the proposal with these sections:
    1. **Executive Summary**: Brief overview of the solution.
    2. **System Configuration**: List the equipment (Inverter, Batteries, Panels) with technical highlights.
    3. **Load Analysis (Estimated)**: Explain what this system can power simultaneously based on typical Nigerian appliances (e.g., 'Can power: 1x Fridge, 5x Fans, 1x TV...'). Be realistic.
    4. **Direct Benefits**: Focus on fuel savings (replacing generators), noise reduction, and 24/7 power.
    5. **Warranty & Support**: Standard 1-year warranty description.

    Formatting Rules:
    - Use <h2> for section headers.
    - Use <ul><li> for lists.
    - Use <strong> for emphasis.
    - Do NOT include specific prices unless provided.
    - Localize for Nigeria (use 'NEPA/PHCN', 'Generator', 'Fuel scarcity' context).
    - Keep it concise but professional.";

    // Construct User Prompt from Form Data
    $userPrompt = "Generate a proposal for a {$data['project_type']} project. 
    Specs:
    - Inverter: {$data['inverter']}
    - Batteries: {$data['batteries']}
    - Solar Panels: " . ($data['panels'] ?: 'None (Backup Only)') . "
    - Context: " . ($data['context'] ?: 'Standard installation') . ".";

    // Call Groq API
    // Using a higher max_tokens for a full document
    $proposalHtml = callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 2000]);

    echo json_encode(['success' => true, 'proposal_html' => $proposalHtml]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
/**
 * AI Proposal Generator Endpoint
 * Generates full project proposals using Groq AI.
 */

header('Content-Type: application/json');
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-history.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Cap the body before decoding.
$rawBody = file_get_contents('php://input', false, null, 0, 65537);
if ($rawBody === false || strlen($rawBody) > 65536) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Request body is too large.']);
    exit;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
    exit;
}

$allowedTypes = ['Residential Home', 'Commercial Office', 'Industrial Facility'];
$projectType = trim((string) ($data['project_type'] ?? ''));
if (!in_array($projectType, $allowedTypes, true)) {
    $projectType = 'Residential Home';
}
$inverter = mb_substr(trim((string) ($data['inverter'] ?? '')), 0, 200);
$batteries = mb_substr(trim((string) ($data['batteries'] ?? '')), 0, 200);
$panels = mb_substr(trim((string) ($data['panels'] ?? '')), 0, 200);
$context = mb_substr(trim((string) ($data['context'] ?? '')), 0, 2000);

if ($inverter === '' || $batteries === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Inverter and battery details are required.']);
    exit;
}

try {
    $systemPrompt = "You are a Senior Solar Engineer at Eleven100 ERP in Nigeria.
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

    $userPrompt = "Generate a proposal for a {$projectType} project.
    Specs:
    - Inverter: {$inverter}
    - Batteries: {$batteries}
    - Solar Panels: " . ($panels !== '' ? $panels : 'None (Backup Only)') . "
    - Context: " . ($context !== '' ? $context : 'Standard installation') . ".";

    // Higher token budget for a full document.
    $history = getAiBusinessContext($pdo);
    if ($history !== '') {
        $userPrompt .= "\nBusiness history: $history";
    }
    $proposalHtml = sanitizeRichTextHtml(callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 2000]));
    if ($proposalHtml === '' || trim(strip_tags($proposalHtml)) === '') {
        throw new RuntimeException('The AI returned an empty proposal.');
    }

    echo json_encode(['success' => true, 'proposal_html' => $proposalHtml]);

} catch (Throwable $e) {
    error_log('Proposal AI generation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not generate the proposal right now. Please try again.']);
}

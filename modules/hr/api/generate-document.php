<?php
// HR Document Generator API
header('Content-Type: application/json');
require_once '../../../config.php';
require_once '../../../includes/groq-config.php';
require_once '../classes/HR_Employee.php';

// Check permissions (Basic session check)
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['employee_id']) || empty($data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID and Document Type are required']);
    exit;
}

try {
    $hr = new HR_Employee($pdo);
    $employee = $hr->getEmployeeById($data['employee_id']);

    if (!$employee) {
        throw new Exception("Employee not found");
    }

    $type = $data['type'];
    $context = $data['context'] ?? '';

    // Construct System Prompt
    $systemPrompt = "You are an expert HR Manager at " . COMPANY_NAME . ". 
    Your task is to write a professional, legally sound (in Nigerian context), and clearer HR document.
    
    Output Format: HTML (use <p>, <ul>, <strong>, <br>). Do not include <html> or <body> tags.
    Tone: Professional, Formal, Empathetic where necessary.
    
    Company Info:
    Name: " . COMPANY_NAME . "
    Address: " . COMPANY_ADDRESS . "
    
    Employee Info:
    Name: {$employee['full_name']}
    Role: {$employee['designation']}
    Department: {$employee['department']}
    Start Date: {$employee['join_date']}
    ";

    // Specific Prompt based on type
    $userPrompt = "";

    switch ($type) {
        case 'employment_letter':
            $userPrompt = "Write an Employment Offer Letter for {$employee['full_name']}.
            Position: {$employee['designation']}
            Salary: " . CURRENCY_SYMBOL . number_format($employee['basic_salary'], 2) . " (Basic)
            Start Date: {$employee['join_date']}
            Additional Info: $context
            Include standard clauses for probation, working hours, and termination notice.";
            break;

        case 'termination_letter':
            $userPrompt = "Write a Termination of Appointment Letter for {$employee['full_name']}.
            Reason/Context: $context
            Effective Date: Today (" . date('d M Y') . ").
            Keep it legally safe, firm but respectful. Mention handover of company property.";
            break;

        case 'query':
            $userPrompt = "Write a Formal Query/Warning Letter to {$employee['full_name']}.
            Misconduct/Issue: $context
            Require a written explanation within 24 hours.
            Cite 'Company Code of Conduct'.";
            break;

        case 'promotion':
            $userPrompt = "Write a Promotion Letter for {$employee['full_name']}.
            New Role/Details: $context
            Congratulate them on their performance.";
            break;

        case 'recommendation':
            $userPrompt = "Write a Letter of Recommendation for {$employee['full_name']}.
            They are a {$employee['designation']}.
            Key Strengths/Context: $context";
            break;

        default:
            $userPrompt = "Write a formal HR document of type '$type' for {$employee['full_name']}.
            Context: $context";
    }

    $generatedContent = callGroqAPI($userPrompt, $systemPrompt);

    echo json_encode(['success' => true, 'content' => $generatedContent]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

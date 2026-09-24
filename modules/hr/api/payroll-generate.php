<?php
/**
 * Generate / recompute monthly payroll for all active employees using the
 * Nigeria-compliant HR_Payroll engine. Admin only. Idempotent (UPSERT).
 */
require_once '../../../config.php';
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Payroll.php';

header('Content-Type: application/json');

if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int) ($input['month'] ?? date('n'));
$year = (int) ($input['year'] ?? date('Y'));

if ($month < 1 || $month > 12 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month/year']);
    exit;
}

try {
    $payroll = new HR_Payroll($pdo);

    // Active employees only.
    $stmt = $pdo->query("SELECT id FROM hr_employees WHERE termination_date IS NULL OR termination_date > CURDATE()");
    $employeeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $generated = 0;
    $updated = 0;

    $upsert = $pdo->prepare("
        INSERT INTO hr_payroll (
            employee_id, month, year, basic_salary, allowances, overtime, bonus, commission,
            gross_salary, taxable_income, paye, nhf, pension_employee, pension_employer,
            loan_deduction, other_deductions, total_deductions, net_salary, employer_cost, status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'generated')
        ON DUPLICATE KEY UPDATE
            basic_salary = VALUES(basic_salary),
            allowances = VALUES(allowances),
            overtime = VALUES(overtime),
            bonus = VALUES(bonus),
            commission = VALUES(commission),
            gross_salary = VALUES(gross_salary),
            taxable_income = VALUES(taxable_income),
            paye = VALUES(paye),
            nhf = VALUES(nhf),
            pension_employee = VALUES(pension_employee),
            pension_employer = VALUES(pension_employer),
            loan_deduction = VALUES(loan_deduction),
            other_deductions = VALUES(other_deductions),
            total_deductions = VALUES(total_deductions),
            net_salary = VALUES(net_salary),
            employer_cost = VALUES(employer_cost)
    ");

    foreach ($employeeIds as $empId) {
        $r = $payroll->computeMonthly($empId, $month, $year);

        // Detect existing to classify generated vs updated.
        $chk = $pdo->prepare("SELECT id FROM hr_payroll WHERE employee_id = ? AND month = ? AND year = ?");
        $chk->execute([$empId, $month, $year]);
        $exists = $chk->fetch();

        $upsert->execute([
            $r['employee_id'], $r['month'], $r['year'],
            $r['basic_salary'], $r['allowances'], $r['overtime'], $r['bonus'], $r['commission'],
            $r['gross_salary'], $r['taxable_income'], $r['paye'], $r['nhf'],
            $r['pension_employee'], $r['pension_employer'], $r['loan_deduction'],
            $r['other_deductions'], $r['total_deductions'], $r['net_salary'], $r['employer_cost']
        ]);

        if ($exists) {
            $updated++;
        } else {
            $generated++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Payroll computed for " . ($generated + $updated) . " employees.",
        'generated' => $generated,
        'updated' => $updated,
        'month' => $month,
        'year' => $year
    ]);
} catch (Exception $e) {
    error_log("Payroll generate error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

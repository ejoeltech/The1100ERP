<?php
// HR Payroll Management
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Payroll | ' . COMPANY_NAME;
$currentPage = 'hr_payroll';

$hr_employee = new HR_Employee($pdo);
$employees = $hr_employee->getAllEmployees(1000); // Get all active employees

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

$message = '';
$error = '';

// Handle Payroll Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    if (!isAdmin()) {
        $error = "Only admins can generate payroll.";
    } else {
        try {
            $pdo->beginTransaction();
            $generated_count = 0;

            foreach ($employees as $emp) {
                // Check if payroll already exists
                $stmt = $pdo->prepare("SELECT id FROM hr_payroll WHERE employee_id = ? AND month = ? AND year = ?");
                $stmt->execute([$emp['id'], $month, $year]);
                if ($stmt->fetch())
                    continue; // Skip if exists

                // Calculations
                $basic = $emp['basic_salary'];
                $housing = $emp['housing_allowance'];
                $transport = $emp['transport_allowance'];
                $allowances = $housing + $transport + ($emp['other_allowances'] ?? 0);

                // Simple assumption: Tax is 0 unless defined (should be robust in real app)
                $tax = $emp['tax_deduction'] ?? 0;
                $pension = $emp['pension_deduction'] ?? 0;
                $deductions = $tax + $pension;

                $net = ($basic + $allowances) - $deductions;

                $stmt = $pdo->prepare("
                    INSERT INTO hr_payroll (
                        employee_id, month, year, basic_salary, allowances, 
                        deductions, tax, net_salary, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'generated')
                ");
                $stmt->execute([
                    $emp['id'],
                    $month,
                    $year,
                    $basic,
                    $allowances,
                    $deductions,
                    $tax,
                    $net
                ]);
                $generated_count++;
            }

            $pdo->commit();
            $message = "Payroll generated for $generated_count employees.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Payroll Records
$payrolls = [];
$stmt = $pdo->prepare("
    SELECT p.*, e.employee_code, u.full_name, d.name as department
    FROM hr_payroll p
    JOIN hr_employees e ON p.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    LEFT JOIN hr_departments d ON e.department_id = d.id
    WHERE p.month = ? AND p.year = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$month, $year]);
$payrolls = $stmt->fetchAll();

include_once '../../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Payroll Management</h1>
        <p class="text-gray-600">Salary processing and payslips</p>
    </div>
    <div class="flex gap-2 items-center">
        <form class="flex gap-2" method="GET">
            <select name="month" class="rounded-lg border-gray-300" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" class="rounded-lg border-gray-300" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <?php if (isAdmin()): ?>
            <form method="POST">
                <input type="hidden" name="generate_payroll" value="1">
                <button type="submit"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors shadow-sm font-medium">
                    ⚡ Generate Payroll for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200"><?php echo $error; ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dept</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basic</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allowances</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Pay</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($payrolls as $pay): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($pay['full_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($pay['employee_code']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($pay['department'] ?? '-'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                            <?php echo number_format($pay['basic_salary'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                            <?php echo number_format($pay['allowances'] + $pay['bonus'] + $pay['commission'] + $pay['overtime'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-500">
                            -<?php echo number_format($pay['deductions'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-green-600">
                            <?php echo CURRENCY_SYMBOL . number_format($pay['net_salary'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo ucfirst($pay['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="#" class="text-primary hover:text-blue-900">Payslip</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payrolls)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">No payroll records generated for this
                            month.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($payrolls)): ?>
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-right">Total Net Payable:</td>
                        <td class="px-6 py-4 text-right text-green-900">
                            <?php echo CURRENCY_SYMBOL . number_format(array_sum(array_column($payrolls, 'net_salary')), 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include_once '../../../includes/footer.php'; ?>
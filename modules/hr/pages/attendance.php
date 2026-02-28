<?php
// HR Attendance
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Attendance | ' . COMPANY_NAME;
$currentPage = 'hr_attendance';

$hr_employee = new HR_Employee($pdo);
// Get current logged in employee record if they are an employee
$current_emp = $hr_employee->getEmployeeByUserId($_SESSION['user_id']);

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$current_emp) {
        $error = "You are not linked to an employee record.";
    } else {
        $action = $_POST['action'];
        $today = date('Y-m-d');
        
        if ($action === 'clock_in') {
            // Check if already clocked in
            $stmt = $pdo->prepare("SELECT id FROM hr_attendance WHERE employee_id = ? AND date = ?");
            $stmt->execute([$current_emp['id'], $today]);
            if ($stmt->fetch()) {
                $error = "You have already clocked in today.";
            } else {
                $time = date('H:i:s');
                $status = ($time > getSetting('hr_work_start_time', '09:00')) ? 'late' : 'present';
                $stmt = $pdo->prepare("INSERT INTO hr_attendance (employee_id, date, clock_in, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$current_emp['id'], $today, $time, $status]);
                $message = "Clocked in successfully at " . date('g:i A');
            }
        } elseif ($action === 'clock_out') {
            $time = date('H:i:s');
            $stmt = $pdo->prepare("UPDATE hr_attendance SET clock_out = ? WHERE employee_id = ? AND date = ?");
            $stmt->execute([$time, $current_emp['id'], $today]);
            $message = "Clocked out successfully at " . date('g:i A');
        }
    }
}

// Fetch Attendance History (Use 'month' parameter or current)
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

$attendance_log = [];
if ($current_emp) {
    $stmt = $pdo->prepare("SELECT * FROM hr_attendance WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date DESC");
    $stmt->execute([$current_emp['id'], $month, $year]);
    $attendance_log = $stmt->fetchAll();
}

// Check today's status
$today_record = null;
if ($current_emp) {
    $stmt = $pdo->prepare("SELECT * FROM hr_attendance WHERE employee_id = ? AND date = CURRENT_DATE()");
    $stmt->execute([$current_emp['id']]);
    $today_record = $stmt->fetch();
}

include_once '../../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Attendance</h1>
        <p class="text-gray-600">Track your work hours</p>
    </div>
    
    <div class="bg-white px-4 py-2 rounded-lg shadow-sm font-mono text-xl font-bold text-primary">
        <?php echo date('D, d M Y'); ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200"><?php echo $error; ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Clock In/Out Station -->
    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col items-center justify-center text-center">
        <?php if (!$current_emp): ?>
            <div class="text-gray-500 mb-4">Account not linked to Employee ID</div>
        <?php else: ?>
            <div class="mb-6">
                <p class="text-gray-500 text-sm mb-1">Current Status</p>
                <?php if ($today_record && $today_record['clock_out']): ?>
                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full font-bold">Shift Ended</span>
                <?php elseif ($today_record): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full font-bold">Working</span>
                <?php else: ?>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full font-bold">Not Clocked In</span>
                <?php endif; ?>
            </div>

            <form method="POST" class="w-full">
                <?php if (!$today_record): ?>
                    <button type="submit" name="action" value="clock_in" class="w-full py-4 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold text-xl shadow-lg transition-transform transform hover:-translate-y-1">
                        ⏱️ Clock In
                    </button>
                    <p class="mt-4 text-sm text-gray-500">Work starts at <?php echo getSetting('hr_work_start_time', '09:00'); ?></p>
                <?php elseif (!$today_record['clock_out']): ?>
                    <div class="text-3xl font-bold text-gray-800 mb-6 font-mono">
                        <?php echo date('h:i A', strtotime($today_record['clock_in'])); ?>
                    </div>
                    <button type="submit" name="action" value="clock_out" class="w-full py-4 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xl shadow-lg transition-transform transform hover:-translate-y-1">
                        🛑 Clock Out
                    </button>
                <?php else: ?>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">In:</span>
                            <span class="font-bold"><?php echo date('h:i A', strtotime($today_record['clock_in'])); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Out:</span>
                            <span class="font-bold"><?php echo date('h:i A', strtotime($today_record['clock_out'])); ?></span>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 text-green-600 font-bold">
                            You are done for today!
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="lg:col-span-2 grid grid-cols-2 gap-4">
        <div class="bg-blue-50 p-6 rounded-xl border border-blue-100">
            <h3 class="text-blue-800 font-semibold mb-2">Days Present</h3>
            <p class="text-3xl font-bold text-blue-900"><?php echo count($attendance_log); ?></p>
            <p class="text-sm text-blue-600 mt-1">This Month</p>
        </div>
        <div class="bg-red-50 p-6 rounded-xl border border-red-100">
            <h3 class="text-red-800 font-semibold mb-2">Late Arrivals</h3>
            <p class="text-3xl font-bold text-red-900">
                <?php 
                echo count(array_filter($attendance_log, function($r) { return $r['status'] === 'late'; })); 
                ?>
            </p>
            <p class="text-sm text-red-600 mt-1">This Month</p>
        </div>
    </div>
</div>

<!-- History -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="font-bold text-gray-900">Attendance Log</h3>
        <select onchange="window.location.search = '?month='+this.value" class="rounded-lg border-gray-300 text-sm">
            <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>>
                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clock In</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clock Out</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach($attendance_log as $log): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo date('M d, Y', strtotime($log['date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('h:i A', strtotime($log['clock_in'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $log['clock_out'] ? date('h:i A', strtotime($log['clock_out'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 text-xs font-semibold rounded-full 
                                <?php echo $log['status']==='late'?'bg-red-100 text-red-800':($log['status']==='present'?'bg-green-100 text-green-800':'bg-gray-100'); ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php 
                            if ($log['clock_out']) {
                                $start = strtotime($log['clock_in']);
                                $end = strtotime($log['clock_out']);
                                $hours = round(($end - $start) / 3600, 1);
                                echo $hours . ' hrs';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance_log)): ?>
                    <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No records found for this month.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../../includes/footer.php'; ?>

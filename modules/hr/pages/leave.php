<?php
// HR Leave Management
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Leave Management | ' . COMPANY_NAME;
$currentPage = 'hr_leave';

$hr_employee = new HR_Employee($pdo);
$current_emp = $hr_employee->getEmployeeByUserId($_SESSION['user_id']);
$is_admin = isAdmin();

$message = '';
$error = '';

// Handle Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {
    if (!$current_emp) {
        $error = "You are not linked to an employee record.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO hr_leave_requests (employee_id, leave_type, start_date, end_date, reason) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_emp['id'],
                $_POST['leave_type'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['reason']
            ]);
            $message = "Leave request submitted successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle Status Updates (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $is_admin) {
    try {
        $stmt = $pdo->prepare("UPDATE hr_leave_requests SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_SESSION['user_id'], $_POST['request_id']]);
        $message = "Leave status updated.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Lists
$my_requests = [];
$pending_approvals = [];

if ($current_emp) {
    $stmt = $pdo->prepare("SELECT * FROM hr_leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_emp['id']]);
    $my_requests = $stmt->fetchAll();
}

if ($is_admin) {
    $stmt = $pdo->query("
        SELECT lr.*, u.full_name, e.employee_code 
        FROM hr_leave_requests lr 
        JOIN hr_employees e ON lr.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        WHERE lr.status = 'pending' 
        ORDER BY lr.created_at ASC
    ");
    $pending_approvals = $stmt->fetchAll();
}

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Leave Management</h1>
    <p class="text-gray-600">Apply for leave and manage requests</p>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200"><?php echo $error; ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Apply for Leave (Left Col) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Apply for Leave</h3>
            <?php if (!$current_emp): ?>
                <div class="text-gray-500 italic">Please contact HR to link your account to an employee record first.</div>
            <?php else: ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="request_leave" value="1">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type" required class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary">
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="casual">Casual Leave</option>
                            <option value="maternity">Maternity/Paternity</option>
                            <option value="unpaid">Unpaid Leave</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" required class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" required class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <textarea name="reason" rows="3" required class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    
                    <button type="submit" class="w-full py-2 bg-primary text-white rounded-lg font-bold hover:bg-blue-700 transition-colors">
                        Submit Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lists (Right Col) -->
    <div class="lg:col-span-2 space-y-6">
        
        <?php if ($is_admin && !empty($pending_approvals)): ?>
            <!-- Admin Approvals -->
            <div class="bg-white rounded-xl shadow-sm border border-yellow-200 overflow-hidden">
                <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-200">
                    <h3 class="font-bold text-yellow-800">⚠️ Pending Approvals (Admin)</h3>
                </div>
                <ul class="divide-y divide-gray-200">
                    <?php foreach($pending_approvals as $req): ?>
                        <li class="p-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($req['full_name']); ?></h4>
                                    <p class="text-sm text-gray-500">
                                        <?php echo ucfirst($req['leave_type']); ?> Leave | 
                                        <?php echo date('M d', strtotime($req['start_date'])) . ' to ' . date('M d', strtotime($req['end_date'])); ?>
                                    </p>
                                    <p class="mt-2 text-gray-700 italic">"<?php echo htmlspecialchars($req['reason']); ?>"</p>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="status" value="approved" class="px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 font-medium text-sm">Approve</button>
                                        <button type="submit" name="status" value="rejected" class="px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium text-sm">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- My History -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-bold text-gray-900">My Leave History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dates</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($my_requests as $req): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 capitalize"><?php echo $req['leave_type']; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d', strtotime($req['start_date'])) . ' - ' . date('M d, Y', strtotime($req['end_date'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php 
                                    $days = (strtotime($req['end_date']) - strtotime($req['start_date'])) / (60 * 60 * 24) + 1;
                                    echo $days . ' days';
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $statuscolors = ['pending'=>'bg-yellow-100 text-yellow-800', 'approved'=>'bg-green-100 text-green-800', 'rejected'=>'bg-red-100 text-red-800'];
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statuscolors[$req['status']]??'bg-gray-100'; ?>">
                                        <?php echo ucfirst($req['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($my_requests)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No leave requests yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once '../../../includes/footer.php'; ?>

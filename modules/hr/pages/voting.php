<?php
// HR Staff Voting
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Staff Voting | ' . COMPANY_NAME;
$currentPage = 'hr_voting';

$hr_employee = new HR_Employee($pdo);
$current_month = date('n');
$current_year = date('Y');

// Get Current Employee ID (Voter)
$current_user_id = $_SESSION['user_id'];
$voter = $hr_employee->getEmployeeByUserId($current_user_id);

if (!$voter && !isAdmin()) {
    die("Access Denied: You must be an employee to vote.");
}

// Handle Vote Submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    if (!$voter) {
        $error = "Only employees can vote.";
    } else {
        $candidate_id = $_POST['candidate_id'];
        $reason = $_POST['reason'] ?? '';

        if ($candidate_id == $voter['id']) {
            $error = "You cannot vote for yourself.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO hr_votes (voter_id, candidate_id, month, year, reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$voter['id'], $candidate_id, $current_month, $current_year, $reason]);
                $message = "Vote cast successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "You have already voted this month.";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get Vote Status
$has_voted = false;
if ($voter) {
    $stmt = $pdo->prepare("SELECT * FROM hr_votes WHERE voter_id = ? AND month = ? AND year = ?");
    $stmt->execute([$voter['id'], $current_month, $current_year]);
    $vote_record = $stmt->fetch();
    if ($vote_record)
        $has_voted = true;
}

// Get All Employees for Voting List
$employees = $hr_employee->getAllEmployees(1000);

// Get Results (for Admin or after voting - maybe transparent results?)
// Let's make results visible to everyone after they vote, or just Admin?
// Requirement: "Admin view to see tally". Let's show top 3 to everyone for fun? Or just Admin.
// Let's stick to Admin can see all, Users can just vote.

$results = [];
if (isAdmin()) {
    $stmt = $pdo->prepare("
        SELECT 
            candidate_id, 
            e.employee_code,
            u.full_name,
            d.name as department,
            COUNT(v.id) as vote_count
        FROM hr_votes v
        JOIN hr_employees e ON v.candidate_id = e.id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN hr_departments d ON e.department_id = d.id
        WHERE v.month = ? AND v.year = ?
        GROUP BY v.candidate_id
        ORDER BY vote_count DESC
    ");
    $stmt->execute([$current_month, $current_year]);
    $results = $stmt->fetchAll();
}

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Staff of the Month Voting</h1>
    <p class="text-gray-600">Vote for your colleague who performed best in
        <?php echo date('F Y'); ?>
    </p>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- Voting Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Cast Your Vote</h2>

        <?php if ($has_voted): ?>
            <div class="text-center py-10">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800">You voted!</h3>
                <p class="text-gray-600 mt-2">Thank you for participating.</p>
            </div>
        <?php elseif ($voter): ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Colleague</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-96 overflow-y-auto">
                        <?php foreach ($employees as $emp): ?>
                            <?php if ($emp['id'] == $voter['id'])
                                continue; // Skip self ?>
                            <label
                                class="relative flex items-center p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="radio" name="candidate_id" value="<?php echo $emp['id']; ?>"
                                    class="h-4 w-4 text-primary focus:ring-primary border-gray-300" required>
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        <?php echo htmlspecialchars($emp['department'] ?? 'No Dept'); ?>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
                    <textarea name="reason" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-primary"
                        placeholder="Why are you nominating them?"></textarea>
                </div>

                <button type="submit"
                    class="w-full py-3 bg-primary text-white rounded-lg font-bold hover:bg-blue-700 transition-colors">
                    Submit Vote
                </button>
            </form>
        <?php else: ?>
            <div class="p-4 bg-yellow-50 text-yellow-700 rounded-lg">
                Only registered employees can vote. You are logged in as an Admin but not linked to an employee record.
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Section (Admin Only) -->
    <?php if (isAdmin()): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-fit">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2 flex justify-between">
                <span>Current Results</span>
                <span class="text-sm font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">
                    <?php echo date('F Y'); ?>
                </span>
            </h2>

            <?php if (empty($results)): ?>
                <p class="text-gray-400 text-center py-8">No votes cast yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($results as $index => $res): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div
                                class="flex-shrink-0 w-8 h-8 flex items-center justify-center font-bold text-white rounded-full 
                            <?php echo $index == 0 ? 'bg-yellow-500' : ($index == 1 ? 'bg-gray-400' : ($index == 2 ? 'bg-orange-400' : 'bg-gray-300')); ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="ml-4 flex-grow">
                                <h3 class="text-sm font-bold text-gray-900">
                                    <?php echo htmlspecialchars($res['full_name']); ?>
                                </h3>
                                <p class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($res['department'] ?? '-'); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="block text-lg font-bold text-primary">
                                    <?php echo $res['vote_count']; ?>
                                </span>
                                <span class="text-xs text-gray-500">votes</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include_once '../../../includes/footer.php'; ?>
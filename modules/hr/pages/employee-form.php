<?php
// HR Employee Form (Add/Edit)
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'Employee Form | ' . COMPANY_NAME;
$currentPage = 'hr_employees';

$hr_employee = new HR_Employee($pdo);
$departments = $hr_employee->getDepartments();
$designations = $hr_employee->getDesignations();

$employee = null;
$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle File Uploads
        $uploadDir = dirname(__DIR__, 3) . '/modules/hr/assets/uploads/employees/';
        $dbUploadDir = '../assets/uploads/employees/'; // Correct relative path from HR pages
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $passport_path = isset($_POST['existing_passport']) ? $_POST['existing_passport'] : null;
        if (isset($_FILES['passport']) && $_FILES['passport']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'passport_' . bin2hex(random_bytes(16)) . '.' . $ext;
                move_uploaded_file($_FILES['passport']['tmp_name'], $uploadDir . $filename);
                $passport_path = $dbUploadDir . $filename;
            }
        }

        $signature_path = isset($_POST['existing_signature']) ? $_POST['existing_signature'] : null;
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'sig_' . bin2hex(random_bytes(16)) . '.' . $ext;
                move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $filename);
                $signature_path = $dbUploadDir . $filename;
            }
        }

        $data = [
            'full_name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'secondary_phone' => $_POST['secondary_phone'],
            'employee_code' => $_POST['employee_code'],
            'department_id' => !empty($_POST['department_id']) ? $_POST['department_id'] : null,
            'designation_id' => !empty($_POST['designation_id']) ? $_POST['designation_id'] : null,
            'join_date' => $_POST['join_date'],
            'employment_status' => $_POST['employment_status'],
            'date_of_birth' => $_POST['dob'], // Map POST 'dob' to DB 'date_of_birth'
            'gender' => $_POST['gender'],
            'address' => $_POST['address'],

            // Financial & Identity
            'bank_name' => $_POST['bank_name'],
            'account_number' => $_POST['account_number'],
            'account_name' => $_POST['account_name'],
            'nin_number' => $_POST['nin_number'],
            'bvn_number' => $_POST['bvn_number'],
            'tin_number' => $_POST['tin_number'],
            'basic_salary' => $_POST['basic_salary'],
            'housing_allowance' => $_POST['housing_allowance'],
            'transport_allowance' => $_POST['transport_allowance'],

            // Next of Kin
            'next_of_kin_name' => $_POST['next_of_kin_name'],
            'next_of_kin_phone' => $_POST['next_of_kin_phone'],
            'next_of_kin_relationship' => $_POST['next_of_kin_relationship'],

            // References
            'reference_1_name' => $_POST['reference_1_name'],
            'reference_1_phone' => $_POST['reference_1_phone'],
            'reference_1_org' => $_POST['reference_1_org'],
            'reference_2_name' => $_POST['reference_2_name'],
            'reference_2_phone' => $_POST['reference_2_phone'],
            'reference_2_org' => $_POST['reference_2_org'],

            // Files
            'passport_path' => $passport_path,
            'signature_path' => $signature_path
        ];

        if (isset($_GET['id'])) {
            $hr_employee->updateEmployee($_GET['id'], $data);
            $success = "Employee updated successfully.";
            $employee = $hr_employee->getEmployeeById($_GET['id']);
        } else {
            $newId = $hr_employee->createEmployee($data);
            header("Location: employees.php?msg=created");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if (isset($_GET['id']) && !$employee) {
    $employee = $hr_employee->getEmployeeById($_GET['id']);
    if (!$employee)
        die("Employee not found.");
}

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <a href="employees.php" class="text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
            </path>
        </svg>
        Back to Employees
    </a>
    <h1 class="text-2xl font-bold text-gray-900">
        <?php echo isset($_GET['id']) ? 'Edit Employee' : 'Add New Employee'; ?>
    </h1>
</div>

<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200"><?php echo $success; ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6" data-is-edit="<?php echo isset($_GET['id']) ? 'true' : 'false'; ?>">
    <?php echo function_exists('csrfField') ? csrfField() : ''; ?>

    <!-- 1. Personal & Identity -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Personal Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Passport Photo Upload -->
            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Passport Photo</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition-colors cursor-pointer relative bg-gray-50"
                    onclick="document.getElementById('passportInput').click()">
                    <?php 
                        $path = $_POST['existing_passport'] ?? $employee['passport_path'] ?? null;
                        $diskPath = dirname(__DIR__, 3) . '/' . str_replace('../', 'modules/hr/', $path);
                        if ($path && file_exists($diskPath)): ?>
                        <img src="<?php echo htmlspecialchars($path); ?>"
                            class="w-32 h-32 object-cover mx-auto rounded-md mb-2">
                        <input type="hidden" name="existing_passport" value="<?php echo htmlspecialchars($path); ?>">
                    <?php else: ?>
                        <div
                            class="w-32 h-32 bg-gray-200 rounded-md mx-auto mb-2 flex items-center justify-center text-gray-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <span class="text-xs text-gray-500">Click to Upload</span>
                    <input type="file" id="passportInput" name="passport" accept="image/*" class="hidden">
                </div>
            </div>

            <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" required
                        value="<?php echo htmlspecialchars($_POST['full_name'] ?? $employee['full_name'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? $employee['email'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                    <input type="tel" name="phone" required
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? $employee['phone'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Phone</label>
                    <input type="tel" name="secondary_phone"
                        value="<?php echo htmlspecialchars($_POST['secondary_phone'] ?? $employee['secondary_phone'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="dob"
                        value="<?php echo htmlspecialchars($_POST['dob'] ?? $employee['date_of_birth'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border-gray-300 focus:ring-primary">
                        <option value="male" <?php echo ($_POST['gender'] ?? $employee['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male
                        </option>
                        <option value="female" <?php echo ($_POST['gender'] ?? $employee['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>
                            Female</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary"><?php echo htmlspecialchars($_POST['address'] ?? $employee['address'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Employment -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Employment Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee Code *</label>
                <input type="text" name="employee_code" required
                    value="<?php echo htmlspecialchars($_POST['employee_code'] ?? $employee['employee_code'] ?? 'EMP-' . date('Y') . '-' . rand(100, 999)); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary font-mono bg-gray-50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Join Date *</label>
                <input type="date" name="join_date" required
                    value="<?php echo htmlspecialchars($_POST['join_date'] ?? $employee['join_date'] ?? date('Y-m-d')); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="employment_status" class="w-full rounded-lg border-gray-300 focus:ring-primary">
                    <option value="full_time" <?php echo ($_POST['employment_status'] ?? $employee['employment_status'] ?? '') == 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                    <option value="part_time" <?php echo ($_POST['employment_status'] ?? $employee['employment_status'] ?? '') == 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                    <option value="contract" <?php echo ($_POST['employment_status'] ?? $employee['employment_status'] ?? '') == 'contract' ? 'selected' : ''; ?>>Contract</option>
                    <option value="intern" <?php echo ($_POST['employment_status'] ?? $employee['employment_status'] ?? '') == 'intern' ? 'selected' : ''; ?>>Intern</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <select name="department_id" class="w-full rounded-lg border-gray-300 focus:ring-primary">
                    <option value="">-- Select --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo ($_POST['department_id'] ?? $employee['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                <select name="designation_id" class="w-full rounded-lg border-gray-300 focus:ring-primary">
                    <option value="">-- Select --</option>
                    <?php foreach ($designations as $des): ?>
                        <option value="<?php echo $des['id']; ?>" <?php echo ($_POST['designation_id'] ?? $employee['designation_id'] ?? '') == $des['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($des['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- 3. Financial & Identity Cards -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Financial & Identity</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Basic Salary
                    (<?php echo CURRENCY_SYMBOL; ?>)</label>
                <input type="number" step="0.01" name="basic_salary"
                    value="<?php echo htmlspecialchars($_POST['basic_salary'] ?? $employee['basic_salary'] ?? '0.00'); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Housing Allowance</label>
                <input type="number" step="0.01" name="housing_allowance"
                    value="<?php echo htmlspecialchars($_POST['housing_allowance'] ?? $employee['housing_allowance'] ?? '0.00'); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transport Allowance</label>
                <input type="number" step="0.01" name="transport_allowance"
                    value="<?php echo htmlspecialchars($_POST['transport_allowance'] ?? $employee['transport_allowance'] ?? '0.00'); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>

            <div class="md:col-span-3 border-t pt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name"
                        value="<?php echo htmlspecialchars($_POST['bank_name'] ?? $employee['bank_name'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                    <input type="text" name="account_number"
                        value="<?php echo htmlspecialchars($_POST['account_number'] ?? $employee['account_number'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                    <input type="text" name="account_name"
                        value="<?php echo htmlspecialchars($_POST['account_name'] ?? $employee['account_name'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
            </div>

            <div class="md:col-span-3 border-t pt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIN Number</label>
                    <input type="text" name="nin_number"
                        value="<?php echo htmlspecialchars($_POST['nin_number'] ?? $employee['nin_number'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">BVN Number</label>
                    <input type="text" name="bvn_number"
                        value="<?php echo htmlspecialchars($_POST['bvn_number'] ?? $employee['bvn_number'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                    <input type="text" name="tin_number"
                        value="<?php echo htmlspecialchars($_POST['tin_number'] ?? $employee['tin_number'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-primary">
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Next of Kin & References -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Next of Kin</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="next_of_kin_name"
                    value="<?php echo htmlspecialchars($_POST['next_of_kin_name'] ?? $employee['next_of_kin_name'] ?? ''); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="text" name="next_of_kin_phone"
                    value="<?php echo htmlspecialchars($_POST['next_of_kin_phone'] ?? $employee['next_of_kin_phone'] ?? ''); ?>"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                <input type="text" name="next_of_kin_relationship"
                    value="<?php echo htmlspecialchars($_POST['next_of_kin_relationship'] ?? $employee['next_of_kin_relationship'] ?? ''); ?>"
                    placeholder="e.g. Spouse, Brother" class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
        </div>

        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2 pt-4">Referees</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Referee 1 -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-bold text-gray-700 mb-2">Reference 1</h3>
                <div class="space-y-3">
                    <input type="text" name="reference_1_name" placeholder="Full Name"
                        value="<?php echo htmlspecialchars($_POST['reference_1_name'] ?? $employee['reference_1_name'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="reference_1_phone" placeholder="Phone Number"
                        value="<?php echo htmlspecialchars($_POST['reference_1_phone'] ?? $employee['reference_1_phone'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="reference_1_org" placeholder="Organization"
                        value="<?php echo htmlspecialchars($_POST['reference_1_org'] ?? $employee['reference_1_org'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>
            <!-- Referee 2 -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-bold text-gray-700 mb-2">Reference 2</h3>
                <div class="space-y-3">
                    <input type="text" name="reference_2_name" placeholder="Full Name"
                        value="<?php echo htmlspecialchars($_POST['reference_2_name'] ?? $employee['reference_2_name'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="reference_2_phone" placeholder="Phone Number"
                        value="<?php echo htmlspecialchars($_POST['reference_2_phone'] ?? $employee['reference_2_phone'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="reference_2_org" placeholder="Organization"
                        value="<?php echo htmlspecialchars($_POST['reference_2_org'] ?? $employee['reference_2_org'] ?? ''); ?>"
                        class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Signature -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Signature</h2>
        <div
            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary transition-colors bg-gray-50">
            <label class="cursor-pointer block">
                <?php 
                    $path = $_POST['existing_signature'] ?? $employee['signature_path'] ?? null;
                    $diskPath = dirname(__DIR__, 3) . '/' . str_replace('../', 'modules/hr/', $path);
                    if ($path && file_exists($diskPath)): ?>
                    <img src="<?php echo htmlspecialchars($path); ?>" class="h-20 object-contain mx-auto mb-2">
                    <input type="hidden" name="existing_signature" value="<?php echo htmlspecialchars($path); ?>">
                    <p class="text-xs text-green-600">Signature Uploaded</p>
                <?php else: ?>
                    <p class="text-gray-500 mb-2">Upload Scanned Signature (PNG/JPG)</p>
                <?php endif; ?>
                <input type="file" name="signature" accept="image/*"
                    class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </label>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-3 pb-8">
        <a href="employees.php"
            class="px-6 py-3 text-gray-700 hover:bg-gray-100 rounded-xl font-bold bg-white border border-gray-200">Cancel</a>
        <button type="submit"
            class="px-8 py-3 bg-primary text-white rounded-xl font-bold hover:bg-blue-700 shadow-lg transition-transform transform hover:-translate-y-1">
            <?php echo isset($_GET['id']) ? 'Save Changes' : 'Create Employee'; ?>
        </button>
    </div>

</form>


<script>
    // Image Preview for Passport
    const passportInput = document.getElementById('passportInput');
    if (passportInput) {
        passportInput.addEventListener('change', function (e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var container = passportInput.parentElement;
                    // Hide placeholder div
                    var placeholder = container.querySelector('div');
                    if (placeholder) placeholder.style.display = 'none';

                    // Update or create Image
                    var img = container.querySelector('img');
                    if (img) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    } else {
                        img = document.createElement('img');
                        img.src = e.target.result;
                        img.classList.add('w-32', 'h-32', 'object-cover', 'mx-auto', 'rounded-md', 'mb-2');
                        container.insertBefore(img, container.firstChild);
                    }
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
</script>

<?php include_once '../../../includes/footer.php'; ?>
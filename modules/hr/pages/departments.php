<?php
// HR Departments & Designations Management
require_once '../../../includes/session-check.php';

requirePermission('hr_manage');

$pageTitle = 'Departments & Designations | ' . COMPANY_NAME;
$currentPage = 'hr_departments';

$message = '';
$error = '';

$messageMap = [
    'dept_created' => 'Department created successfully.',
    'dept_updated' => 'Department updated successfully.',
    'dept_deleted' => 'Department deleted successfully.',
    'desig_created' => 'Designation created successfully.',
    'desig_updated' => 'Designation updated successfully.',
    'desig_deleted' => 'Designation deleted successfully.',
];
if (isset($_GET['msg'], $messageMap[$_GET['msg']])) {
    $message = $messageMap[$_GET['msg']];
}

function hrDepartmentsRedirect($msg)
{
    header('Location: departments.php?msg=' . urlencode($msg));
    exit;
}

function hrTextLength($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$deptForm = ['id' => 0, 'name' => '', 'description' => ''];
$desigForm = ['id' => 0, 'title' => '', 'department_id' => '', 'description' => ''];

// Load edit targets. POST values take precedence when validation fails.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['edit_department'])) {
        $stmt = $pdo->prepare("SELECT id, name, description FROM hr_departments WHERE id = ?");
        $stmt->execute([(int)$_GET['edit_department']]);
        if ($row = $stmt->fetch()) {
            $deptForm = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'description' => (string)($row['description'] ?? ''),
            ];
        }
    }
    if (!empty($_GET['edit_designation'])) {
        $stmt = $pdo->prepare("SELECT id, title, department_id, description FROM hr_designations WHERE id = ?");
        $stmt->execute([(int)$_GET['edit_designation']]);
        if ($row = $stmt->fetch()) {
            $desigForm = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'department_id' => $row['department_id'] !== null ? (string)$row['department_id'] : '',
                'description' => (string)($row['description'] ?? ''),
            ];
        }
    }
}

/* ---------------- Departments ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) {
    $id = (int)($_POST['department_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $deptForm = ['id' => $id, 'name' => $name, 'description' => $description];

    if ($name === '') {
        $error = 'Department name is required.';
    } elseif (hrTextLength($name) > 255) {
        $error = 'Department name must be 255 characters or fewer.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM hr_departments WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                $error = 'A department with that name already exists.';
            } elseif ($id > 0) {
                $stmt = $pdo->prepare("UPDATE hr_departments SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description !== '' ? $description : null, $id]);
                hrDepartmentsRedirect('dept_updated');
            } else {
                $stmt = $pdo->prepare("INSERT INTO hr_departments (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description !== '' ? $description : null]);
                hrDepartmentsRedirect('dept_created');
            }
        } catch (Exception $e) {
            error_log('Department save failed: ' . $e->getMessage());
            $error = 'Could not save the department. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    $id = (int)($_POST['department_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE department_id = ?");
        $stmt->execute([$id]);
        $employeeCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hr_designations WHERE department_id = ?");
        $stmt->execute([$id]);
        $designationCount = (int)$stmt->fetchColumn();

        if ($employeeCount > 0) {
            $error = "Cannot delete this department: $employeeCount employee(s) are still assigned to it. Reassign them first.";
        } elseif ($designationCount > 0) {
            $error = "Cannot delete this department: $designationCount designation(s) still belong to it. Move or delete them first.";
        } else {
            $pdo->prepare("DELETE FROM hr_departments WHERE id = ?")->execute([$id]);
            hrDepartmentsRedirect('dept_deleted');
        }
    } catch (Exception $e) {
        error_log('Department delete failed: ' . $e->getMessage());
        $error = 'Could not delete the department. Please try again.';
    }
}

/* ---------------- Designations ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_designation'])) {
    $id = (int)($_POST['designation_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $departmentId = trim((string)($_POST['department_id'] ?? ''));
    $departmentId = $departmentId === '' ? null : (int)$departmentId;
    $description = trim((string)($_POST['description'] ?? ''));
    $desigForm = [
        'id' => $id,
        'title' => $title,
        'department_id' => $departmentId === null ? '' : (string)$departmentId,
        'description' => $description,
    ];

    if ($title === '') {
        $error = 'Designation title is required.';
    } elseif (hrTextLength($title) > 255) {
        $error = 'Designation title must be 255 characters or fewer.';
    } else {
        try {
            if ($departmentId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM hr_departments WHERE id = ?");
                $stmt->execute([$departmentId]);
                if (!$stmt->fetch()) {
                    $error = 'The selected department no longer exists.';
                }
            }

            if ($error === '') {
                $stmt = $pdo->prepare("SELECT id FROM hr_designations WHERE LOWER(title) = LOWER(?) AND department_id <=> ? AND id <> ? LIMIT 1");
                $stmt->execute([$title, $departmentId, $id]);
                if ($stmt->fetch()) {
                    $error = 'That designation already exists in the selected department.';
                }
            }

            if ($error === '') {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE hr_designations SET title = ?, department_id = ?, description = ? WHERE id = ?");
                    $stmt->execute([$title, $departmentId, $description !== '' ? $description : null, $id]);
                    hrDepartmentsRedirect('desig_updated');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO hr_designations (title, department_id, description) VALUES (?, ?, ?)");
                    $stmt->execute([$title, $departmentId, $description !== '' ? $description : null]);
                    hrDepartmentsRedirect('desig_created');
                }
            }
        } catch (Exception $e) {
            error_log('Designation save failed: ' . $e->getMessage());
            $error = 'Could not save the designation. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_designation'])) {
    $id = (int)($_POST['designation_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE designation_id = ?");
        $stmt->execute([$id]);
        $employeeCount = (int)$stmt->fetchColumn();

        if ($employeeCount > 0) {
            $error = "Cannot delete this designation: $employeeCount employee(s) still hold it. Reassign them first.";
        } else {
            $pdo->prepare("DELETE FROM hr_designations WHERE id = ?")->execute([$id]);
            hrDepartmentsRedirect('desig_deleted');
        }
    } catch (Exception $e) {
        error_log('Designation delete failed: ' . $e->getMessage());
        $error = 'Could not delete the designation. Please try again.';
    }
}

/* ---------------- Lists ---------------- */

$departments = $pdo->query("
    SELECT d.*,
        (SELECT COUNT(*) FROM hr_employees e WHERE e.department_id = d.id) AS employee_count,
        (SELECT COUNT(*) FROM hr_designations ds WHERE ds.department_id = d.id) AS designation_count
    FROM hr_departments d
    ORDER BY d.name ASC
")->fetchAll();

$designations = $pdo->query("
    SELECT ds.*, d.name AS department_name,
        (SELECT COUNT(*) FROM hr_employees e WHERE e.designation_id = ds.id) AS employee_count
    FROM hr_designations ds
    LEFT JOIN hr_departments d ON d.id = ds.department_id
    ORDER BY d.name ASC, ds.title ASC
")->fetchAll();

include_once '../../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Departments & Designations</h1>
        <p class="text-gray-600">Create the teams staff are assigned to and the job titles they hold.</p>
    </div>
    <a href="employees.php" class="text-sm font-semibold text-primary hover:underline">← Back to Employees</a>
</div>

<?php if ($message): ?>
    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

    <!-- Departments -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Departments</h2>
                <p class="text-sm text-gray-500">Teams and units inside the company.</p>
            </div>
            <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full">
                <?php echo count($departments); ?>
            </span>
        </div>

        <form method="POST" class="px-6 py-5 bg-gray-50 border-b border-gray-200 space-y-3">
            <?php echo csrfField(); ?>
            <input type="hidden" name="department_id" value="<?php echo (int)$deptForm['id']; ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" maxlength="255" required
                    value="<?php echo htmlspecialchars($deptForm['name'], ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="e.g. Engineering"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400">(optional)</span></label>
                <textarea name="description" rows="2" placeholder="What this department handles"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary"><?php echo htmlspecialchars($deptForm['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" name="save_department"
                    class="px-4 py-2 bg-blue-700 text-white text-sm font-bold rounded-lg hover:bg-blue-800">
                    <?php echo $deptForm['id'] > 0 ? 'Save Changes' : 'Add Department'; ?>
                </button>
                <?php if ($deptForm['id'] > 0): ?>
                    <a href="departments.php" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="divide-y divide-gray-100">
            <?php foreach ($departments as $dept): ?>
                <div class="px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 break-words"><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($dept['description'])): ?>
                            <p class="text-sm text-gray-500 mt-0.5 break-words"><?php echo htmlspecialchars($dept['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php echo (int)$dept['employee_count']; ?> employee(s) •
                            <?php echo (int)$dept['designation_count']; ?> designation(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="departments.php?edit_department=<?php echo (int)$dept['id']; ?>"
                            class="text-xs font-semibold px-2.5 py-1.5 rounded bg-gray-100 text-gray-700 hover:bg-gray-200">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this department?');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="department_id" value="<?php echo (int)$dept['id']; ?>">
                            <button type="submit" name="delete_department"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($departments)): ?>
                <p class="px-6 py-8 text-sm text-gray-400 text-center">No departments yet. Add the first one above.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Designations -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Designations</h2>
                <p class="text-sm text-gray-500">Job titles staff can hold.</p>
            </div>
            <span class="text-xs font-semibold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full">
                <?php echo count($designations); ?>
            </span>
        </div>

        <form method="POST" class="px-6 py-5 bg-gray-50 border-b border-gray-200 space-y-3">
            <?php echo csrfField(); ?>
            <input type="hidden" name="designation_id" value="<?php echo (int)$desigForm['id']; ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Job Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" maxlength="255" required
                    value="<?php echo htmlspecialchars($desigForm['title'], ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="e.g. Solar Technician"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-gray-400">(optional)</span></label>
                <select name="department_id" class="w-full rounded-lg border-gray-300 focus:ring-primary">
                    <option value="">No department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int)$dept['id']; ?>" <?php echo $desigForm['department_id'] === (string)$dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400">(optional)</span></label>
                <textarea name="description" rows="2" placeholder="Responsibilities or notes"
                    class="w-full rounded-lg border-gray-300 focus:ring-primary"><?php echo htmlspecialchars($desigForm['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" name="save_designation"
                    class="px-4 py-2 bg-blue-700 text-white text-sm font-bold rounded-lg hover:bg-blue-800">
                    <?php echo $desigForm['id'] > 0 ? 'Save Changes' : 'Add Designation'; ?>
                </button>
                <?php if ($desigForm['id'] > 0): ?>
                    <a href="departments.php" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="divide-y divide-gray-100">
            <?php foreach ($designations as $desig): ?>
                <div class="px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 break-words"><?php echo htmlspecialchars($desig['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <?php echo !empty($desig['department_name']) ? htmlspecialchars($desig['department_name'], ENT_QUOTES, 'UTF-8') : 'No department'; ?>
                            • <?php echo (int)$desig['employee_count']; ?> employee(s)
                        </p>
                        <?php if (!empty($desig['description'])): ?>
                            <p class="text-sm text-gray-500 mt-1 break-words"><?php echo htmlspecialchars($desig['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="departments.php?edit_designation=<?php echo (int)$desig['id']; ?>"
                            class="text-xs font-semibold px-2.5 py-1.5 rounded bg-gray-100 text-gray-700 hover:bg-gray-200">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this designation?');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="designation_id" value="<?php echo (int)$desig['id']; ?>">
                            <button type="submit" name="delete_designation"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($designations)): ?>
                <p class="px-6 py-8 text-sm text-gray-400 text-center">No designations yet. Add the first one above.</p>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include_once '../../../includes/footer.php'; ?>

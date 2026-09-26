<?php
// HR Onboarding Admin
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../../../includes/invites.php';
requireLogin();
requirePermission('hr_manage');

// WP4: all state-changing posts on this page carry CSRF (fields added below).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token. Please go back and try again.');
}

$pageTitle = 'Onboarding Management | ' . COMPANY_NAME;
$currentPage = 'hr_onboarding';

// Handle Code Generation (WP2: high-entropy, hashed at rest, expiring)
if (isset($_POST['generate_code'])) {
    require_once '../../../includes/invites.php';
    $role = $_POST['role'] ?? 'viewer';
    if (!in_array($role, ['viewer', 'intern'], true)) {
        $role = 'viewer';
    }

    $code = issueOnboardingCode($role, $_SESSION['user_id']);
    $success = "Generated Code (valid 30 days, single use): <strong>$code</strong>";
}

// Handle Import
if (isset($_POST['import_entry'])) {
    $entry_id = $_POST['entry_id'];

    // Fetch entry details
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_entries WHERE id = ?");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch();

    if ($entry && $entry['status'] == 'submitted') {
        $pdo->beginTransaction();
        try {
            // 1. Create User Account (WP2: unknowable password + invite; never
            // phone numbers or guessable defaults, never displayed).
            $username = strtolower(explode(' ', trim($entry['full_name']))[0]) . rand(100, 999);
            $role = 'viewer'; // Least privilege; code role is never trusted.
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 1)");
            $stmt->execute([$username, hashPassword(bin2hex(random_bytes(32))), $entry['email'], $entry['full_name'], $role]);
            $user_id = $pdo->lastInsertId();
            $invite_token = createUserInvite($user_id, $_SESSION['user_id']);

            // 2. Create HR Employee Record
            // Need to generate employee code
            $emp_code = 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

            $sql_hr = "INSERT INTO hr_employees (
                user_id, employee_code, full_name, email, phone, secondary_phone, 
                address, date_of_birth, gender, 
                passport_path, signature_path, nin_number, bvn_number,
                next_of_kin_name, next_of_kin_phone, next_of_kin_relationship
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql_hr);
            $stmt->execute([
                $user_id,
                $emp_code,
                $entry['full_name'],
                $entry['email'],
                $entry['phone'],
                $entry['phone'],
                $entry['address'],
                $entry['date_of_birth'],
                $entry['gender'],
                $entry['passport_path'],
                $entry['signature_path'],
                $entry['nin_number'],
                $entry['bvn_number'],
                $entry['next_of_kin_name'],
                $entry['next_of_kin_phone'],
                $entry['next_of_kin_relationship']
            ]);

            // 3. Mark Code as Used & Entry Imported
            $pdo->prepare("UPDATE hr_onboarding_codes SET is_used = 1 WHERE id = ?")->execute([$entry['code_id']]);
            $pdo->prepare("UPDATE hr_onboarding_entries SET status = 'imported' WHERE id = ?")->execute([$entry_id]);

            $pdo->commit();
            $inviteLink = '../../pages/users/accept-invite.php?token=' . urlencode($invite_token);
            // WP5: username derives from applicant-supplied full_name — escape at build.
            $success = "Employee Imported Successfully! Username: " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "<br>One-time invite (48h, single use, shown once): <a class='font-mono break-all' href='$inviteLink'>$inviteLink</a>";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Import Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// Fetch active codes
$codes = $pdo->query("SELECT * FROM hr_onboarding_codes ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Fetch pending entries
$entries = $pdo->query("SELECT e.*, c.code FROM hr_onboarding_entries e JOIN hr_onboarding_codes c ON e.code_id = c.id WHERE e.status = 'submitted' ORDER BY e.updated_at DESC")->fetchAll();

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Onboarding Management</h1>
    <p class="text-gray-600">Generate signup codes and review incoming employee submissions.</p>
</div>

<!-- Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    <!-- Code Generator -->
    <div class="md:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold mb-4">Generate Signup Code</h2>
            <?php if (isset($success)): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" class="w-full rounded-lg border-gray-300">
                        <option value="viewer">Staff (Viewer)</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
                <button type="submit" name="generate_code"
                    class="w-full bg-primary text-white py-2 rounded-lg font-bold hover:bg-blue-700">Generate
                    Code</button>
            </form>

            <div class="mt-6">
                <h3 class="font-bold text-gray-700 text-xs uppercase mb-2">Recent Codes</h3>
                <div class="space-y-2">
                    <?php foreach ($codes as $c): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-2 rounded text-sm border">
                            <span class="font-mono font-bold select-all">
                                <?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($c['is_used']): ?>
                                <span class="bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded">Used</span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-600 text-xs px-2 py-0.5 rounded">Active</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Queue -->
    <div class="md:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold mb-4">Pending Reviews</h2>

            <?php if (empty($entries)): ?>
                <div class="text-center py-10 text-gray-400">No pending submissions.</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($entries as $e): ?>
                        <div class="border rounded-lg p-4 hover:border-blue-300 transition bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex gap-4">
                                    <?php if ($e['passport_path']): ?>
                                        <img src="../../../<?php echo htmlspecialchars($e['passport_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="w-12 h-12 rounded-full object-cover bg-gray-200">
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="font-bold text-gray-900">
                                            <?php echo htmlspecialchars($e['full_name']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($e['email']); ?> •
                                            <?php echo htmlspecialchars($e['phone']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Code:
                                            <?php echo htmlspecialchars($e['code'], ENT_QUOTES, 'UTF-8'); ?> • Submitted:
                                            <?php echo date('d M H:i', strtotime($e['updated_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST"
                                        onsubmit="return confirm('Import this employee? This will create a User account.');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="entry_id" value="<?php echo (int)$e['id']; ?>">
                                        <button type="submit" name="import_entry"
                                            class="px-4 py-2 bg-green-600 text-white text-sm rounded font-bold hover:bg-green-700">Import</button>
                                    </form>
                                </div>
                            </div>
                            <!-- Details Toggle (Basic implementation) -->
                            <details class="text-xs text-gray-600 mt-2">
                                <summary class="cursor-pointer text-blue-600">View Details</summary>
                                <div class="grid grid-cols-2 gap-2 mt-2 p-2 bg-white rounded border">
                                    <p><strong>Address:</strong>
                                        <?php echo htmlspecialchars($e['address']); ?>
                                    </p>
                                    <p><strong>DOB:</strong>
                                        <?php echo htmlspecialchars($e['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?> (
                                        <?php echo htmlspecialchars($e['gender'], ENT_QUOTES, 'UTF-8'); ?>)
                                    </p>
                                    <p><strong>NOK:</strong>
                                        <?php echo htmlspecialchars($e['next_of_kin_name']); ?> (
                                        <?php echo htmlspecialchars($e['next_of_kin_relationship']); ?>)
                                    </p>
                                    <p><strong>NOK Phone:</strong>
                                        <?php echo htmlspecialchars($e['next_of_kin_phone']); ?>
                                    </p>
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include_once '../../../includes/footer.php'; ?>
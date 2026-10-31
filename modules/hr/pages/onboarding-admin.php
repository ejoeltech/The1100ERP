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

$onboardingTypes = onboardingTypes();
$success = '';
$error = '';

/**
 * Absolute URL of the public onboarding landing page. Derived from the
 * current request so it works in dev subfolders and on the live domain.
 */
function onboardingSignupUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $root = preg_replace('#/modules/hr/pages$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($root, '/') . '/signup.php';
}

// Handle Code Generation (WP2: high-entropy, hashed at rest, expiring)
if (isset($_POST['generate_code'])) {
    $role = onboardingTypeFromRole($_POST['role'] ?? 'staff');
    $code = issueOnboardingCode($role, $_SESSION['user_id']);
    $success = "Generated " . htmlspecialchars(onboardingTypeLabel($role), ENT_QUOTES, 'UTF-8')
        . " code (valid 30 days, single use): <strong class='font-mono select-all'>"
        . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>';
}

// Handle Delete: only unused codes may be removed. Used codes are audit data.
if (isset($_POST['delete_code'])) {
    $codeId = (int)($_POST['code_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE id = ?");
    $stmt->execute([$codeId]);
    $row = $stmt->fetch();
    if (!$row) {
        $error = 'Signup code not found.';
    } elseif (!empty($row['is_used'])) {
        $error = 'Used codes cannot be deleted. They are kept for the onboarding audit trail.';
    } else {
        $pdo->prepare("DELETE FROM hr_onboarding_codes WHERE id = ?")->execute([$codeId]);
        $success = 'Signup code deleted.';
    }
}

// Handle Renew: invalidate the old code immediately, then issue a fresh one.
if (isset($_POST['renew_code'])) {
    $codeId = (int)($_POST['code_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE id = ?");
    $stmt->execute([$codeId]);
    $row = $stmt->fetch();
    if (!$row) {
        $error = 'Signup code not found.';
    } elseif (!empty($row['is_used'])) {
        $error = 'Used codes cannot be renewed. Generate a new code instead.';
    } else {
        $role = onboardingTypeFromRole($row['role'] ?? 'staff');
        try {
            $newCode = renewOnboardingCode($codeId);
        } catch (Exception $e) {
            $newCode = null;
        }
        if (!$newCode) {
            $error = 'The signup code could not be renewed. Please try again.';
        } else {
            $success = 'Signup code renewed — the previous code no longer works. New '
                . htmlspecialchars(onboardingTypeLabel($role), ENT_QUOTES, 'UTF-8')
                . " code (valid 30 days, single use): <strong class='font-mono select-all'>"
                . htmlspecialchars($newCode, ENT_QUOTES, 'UTF-8') . '</strong>';
        }
    }
}

// Handle Invite by email: creates a unique code and emails it to the applicant.
if (isset($_POST['invite_staff'])) {
    $inviteEmail = trim($_POST['invite_email'] ?? '');
    $inviteName = trim($_POST['invite_name'] ?? '');
    $role = onboardingTypeFromRole($_POST['invite_type'] ?? 'staff');

    if (!filter_var($inviteEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address to send the invite.';
    } elseif (!throttleCheck('onboard_invite:' . strtolower($inviteEmail), 5, 3600)) {
        $error = 'Too many invites sent to this address in the last hour. Try again later.';
    } else {
        $code = issueOnboardingCode($role, $_SESSION['user_id']);
        $mail = sendOnboardingInviteEmail($inviteEmail, $code, $role, onboardingSignupUrl(), $inviteName);
        $typeLabel = htmlspecialchars(onboardingTypeLabel($role), ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        if (!empty($mail['success'])) {
            $success = "Invitation emailed to <strong>" . htmlspecialchars($inviteEmail, ENT_QUOTES, 'UTF-8')
                . "</strong> ($typeLabel). Code (shown once, single use): <strong class='font-mono select-all'>$safeCode</strong>";
        } else {
            $error = "The invitation email could not be sent: "
                . htmlspecialchars($mail['message'] ?? 'unknown error', ENT_QUOTES, 'UTF-8')
                . ". The code <strong class='font-mono select-all'>$safeCode</strong> was created — share it with the staff member manually.";
        }
    }
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
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
            $stmt->execute([$username, hashPassword(bin2hex(random_bytes(32))), $entry['email'], $entry['phone'], $entry['full_name'], $role]);
            $user_id = $pdo->lastInsertId();
            $invite_token = createUserInvite($user_id, $_SESSION['user_id']);

            // 2. Create HR Employee Record
            // Need to generate employee code
            $emp_code = 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            // Signup code type drives the employment status; UI types mirror
            // the hr_employees enum (staff -> full_time, intern, contract).
            $employmentStatus = $entry['employment_type'] ?? 'staff';
            if ($employmentStatus === 'staff') {
                $employmentStatus = 'full_time';
            }
            if (!in_array($employmentStatus, ['full_time', 'part_time', 'contract', 'intern'], true)) {
                $employmentStatus = 'full_time';
            }

            $sql_hr = "INSERT INTO hr_employees (
                user_id, employee_code, full_name, email, phone, secondary_phone, 
                address, date_of_birth, gender, employment_status,
                passport_path, signature_path, nin_number, bvn_number,
                next_of_kin_name, next_of_kin_phone, next_of_kin_relationship
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
                $employmentStatus,
                $entry['passport_path'],
                $entry['signature_path'],
                encryptPII($entry['nin_number'] ?? null),
                encryptPII($entry['bvn_number'] ?? null),
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
            $success = "Employee Imported Successfully as " . htmlspecialchars(onboardingTypeLabel($entry['employment_type'] ?? 'staff'), ENT_QUOTES, 'UTF-8')
                . "! Username: " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
                . "<br>One-time invite (48h, single use, shown once): <a class='font-mono break-all' href='$inviteLink'>$inviteLink</a>";
            if (!invitesGloballyEnabled()) {
                $success .= "<br><span class='text-amber-700'>Invite links are disabled in Security settings — set a temporary password for this user instead.</span>";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Import Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// Fetch active codes
$codes = $pdo->query("SELECT * FROM hr_onboarding_codes ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Fetch pending entries
$entries = $pdo->query("SELECT e.*, c.code, c.role AS code_role FROM hr_onboarding_entries e JOIN hr_onboarding_codes c ON e.code_id = c.id WHERE e.status = 'submitted' ORDER BY e.updated_at DESC")->fetchAll();

include_once '../../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Onboarding Management</h1>
    <p class="text-gray-600">Generate signup codes, invite staff by email, and review incoming employee submissions.</p>
</div>

<?php if ($error): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200 text-sm"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="bg-green-50 text-green-800 p-4 rounded-lg mb-6 border border-green-200 text-sm"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    <!-- Code Generator -->
    <div class="md:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold mb-4">Generate Signup Code</h2>

            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff Type</label>
                    <select name="role" class="w-full rounded-lg border-gray-300">
                        <?php foreach ($onboardingTypes as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_code"
                    class="w-full bg-primary text-white py-2 rounded-lg font-bold hover:bg-blue-700">Generate
                    Code</button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-bold mb-1">Invite by Email</h2>
                <p class="text-xs text-gray-500 mb-2">Creates a unique code and emails the signup link.</p>
                <p class="text-xs mb-4 truncate">
                    <a href="<?php echo htmlspecialchars(onboardingSignupUrl(), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"
                        class="text-blue-700 hover:underline"><?php echo htmlspecialchars(onboardingSignupUrl(), ENT_QUOTES, 'UTF-8'); ?></a>
                </p>

                <form method="POST" class="space-y-3">
                    <?php echo csrfField(); ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="invite_email" required placeholder="name@example.com"
                            value="<?php echo htmlspecialchars($_POST['invite_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (optional)</label>
                        <input type="text" name="invite_name" placeholder="Full name"
                            value="<?php echo htmlspecialchars($_POST['invite_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Staff Type</label>
                        <select name="invite_type" class="w-full rounded-lg border-gray-300">
                            <?php foreach ($onboardingTypes as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo onboardingTypeFromRole($_POST['invite_type'] ?? 'staff') === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="invite_staff"
                        class="w-full bg-blue-700 text-white py-2 rounded-lg font-bold hover:bg-blue-800">
                        Send Invite Email
                    </button>
                </form>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="font-bold text-gray-700 text-xs uppercase mb-2">Recent Codes</h3>
                <div class="space-y-2">
                    <?php foreach ($codes as $c):
                        $codeType = onboardingTypeFromRole($c['role'] ?? 'staff');
                        $expired = !empty($c['expires_at']) && strtotime($c['expires_at']) < time();
                        ?>
                        <div class="bg-gray-50 p-2 rounded text-sm border">
                            <div class="flex justify-between items-center gap-2">
                                <span class="font-mono font-bold select-all">
                                    <?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if ($c['is_used']): ?>
                                    <span class="bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded whitespace-nowrap">Used</span>
                                <?php elseif ($expired): ?>
                                    <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded whitespace-nowrap">Expired</span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-600 text-xs px-2 py-0.5 rounded whitespace-nowrap">Active</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center mt-1 text-xs text-gray-500">
                                <span><?php echo htmlspecialchars(onboardingTypeLabel($codeType), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><?php echo $c['expires_at'] ? 'expires ' . date('d M', strtotime($c['expires_at'])) : 'no expiry'; ?></span>
                            </div>
                            <?php if (empty($c['is_used'])): ?>
                                <div class="flex gap-2 mt-2">
                                    <form method="POST"
                                        onsubmit="return confirm('Revoke this code and issue a fresh one?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="code_id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" name="renew_code"
                                            class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 font-semibold">Renew</button>
                                    </form>
                                    <form method="POST"
                                        onsubmit="return confirm('Delete this signup code? Any draft attached to it will also be removed.');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="code_id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" name="delete_code"
                                            class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 font-semibold">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($codes)): ?>
                        <p class="text-sm text-gray-400">No codes generated yet.</p>
                    <?php endif; ?>
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
                                            <?php echo htmlspecialchars($e['code'], ENT_QUOTES, 'UTF-8'); ?> •
                                            Type:
                                            <?php echo htmlspecialchars(onboardingTypeLabel($e['employment_type'] ?? $e['code_role'] ?? 'staff'), ENT_QUOTES, 'UTF-8'); ?> •
                                            Submitted:
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

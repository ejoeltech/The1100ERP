<?php
// HR Onboarding Form (Self Service)
require_once '../../../config.php';
require_once '../../../includes/security.php';
require_once '../../../includes/invites.php';

// The onboarding landing page starts the session with hardened cookie
// settings. This page must use the exact same save path/config or the
// session handoff is invisible and the applicant is bounced back.
configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['onboarding_code_id'])) {
    header("Location: ../../../signup.php");
    exit;
}

$code_id = $_SESSION['onboarding_code_id'];
$phone = $_SESSION['onboarding_phone'] ?? '';

// Re-resolve the code row server-side (never trust session-held secrets).
$stmt = $pdo->prepare("SELECT * FROM hr_onboarding_codes WHERE id = ?");
$stmt->execute([$code_id]);
$codeRow = $stmt->fetch();
if (!$codeRow || !empty($codeRow['is_used']) || (!empty($codeRow['expires_at']) && strtotime($codeRow['expires_at']) < time())) {
    unset($_SESSION['onboarding_code_id'], $_SESSION['onboarding_phone']);
    header("Location: ../../../signup.php");
    exit;
}
$signup_code = $codeRow['code'];
$employment_type = onboardingTypeFromRole($codeRow['role'] ?? 'staff');

// Check if entry exists or create one
$stmt = $pdo->prepare("SELECT * FROM hr_onboarding_entries WHERE code_id = ?");
$stmt->execute([$code_id]);
$entry = $stmt->fetch();

$success = '';
$error = '';
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // WP4: token check on the self-service post (defense in depth; the
    // onboarding session itself is the primary gate).
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid security token. Please go back and try again.');
    }

    // Required field validation runs before any write so a half-filled form
    // is never saved and the applicant can correct it with their input intact.
    $required = [
        'full_name' => 'Full Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'dob' => 'Date of Birth',
        'address' => 'Residential Address',
        'nok_name' => 'Next of Kin Name',
        'nok_rel' => 'Next of Kin Relationship',
        'nok_phone' => 'Next of Kin Phone',
    ];
    $missing = [];
    foreach ($required as $field => $label) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }
    if (!empty($_POST['email']) && !filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($missing) {
        $error = 'Please complete the following required fields: ' . implode(', ', $missing) . '.';
    }

    if ($error === '') {
        // Handle File Uploads (Passport & Signature)
        // WP7: content-validated images (MIME + getimagesize), random names,
        // guarded dir. Failures leave the previous path untouched.
        $uploadDir = ensureUploadDir(__DIR__ . '/../assets/uploads/onboarding/');

        $passport_path = $entry['passport_path'] ?? null;
        if (isset($_FILES['passport']) && $_FILES['passport']['error'] == 0) {
            try {
                $ext = validateImageUpload($_FILES['passport']);
                $filename = 'pass_' . bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($_FILES['passport']['tmp_name'], $uploadDir . $filename)) {
                    $passport_path = 'modules/hr/assets/uploads/onboarding/' . $filename;
                }
            } catch (Exception $e) {
                $uploadError = 'Passport photo: ' . $e->getMessage();
            }
        }

        $signature_path = $entry['signature_path'] ?? null;
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
            try {
                $ext = validateImageUpload($_FILES['signature']);
                $filename = 'sig_' . bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $filename)) {
                    $signature_path = 'modules/hr/assets/uploads/onboarding/' . $filename;
                }
            } catch (Exception $e) {
                $uploadError = trim($uploadError . ' Signature: ' . $e->getMessage());
            }
        }

        // A bad upload must not silently discard the applicant's typed data.
        if ($uploadError !== '') {
            $error = $uploadError;
        } else {
            // Prepare Data
            $data = [
                'code_id' => $code_id,
                'signup_code' => $signup_code,
                'employment_type' => $employment_type,
                'full_name' => trim($_POST['full_name']),
                'phone' => trim($_POST['phone']), // They can update it here
                'email' => trim($_POST['email']),
                'date_of_birth' => $_POST['dob'],
                'gender' => $_POST['gender'] ?? 'male',
                'address' => trim($_POST['address']),
                'nin_number' => trim($_POST['nin'] ?? ''),
                'bvn_number' => trim($_POST['bvn'] ?? ''),
                'next_of_kin_name' => trim($_POST['nok_name']),
                'next_of_kin_relationship' => trim($_POST['nok_rel']),
                'next_of_kin_phone' => trim($_POST['nok_phone']),
                'passport_path' => $passport_path,
                'signature_path' => $signature_path
            ];

            if ($entry) {
                // Update
                $sql = "UPDATE hr_onboarding_entries SET
                    full_name=?, phone=?, email=?, date_of_birth=?, gender=?, address=?,
                    nin_number=?, bvn_number=?, next_of_kin_name=?, next_of_kin_relationship=?, next_of_kin_phone=?,
                    passport_path=?, signature_path=?, employment_type=?, status='submitted'
                    WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $data['full_name'],
                    $data['phone'],
                    $data['email'],
                    $data['date_of_birth'],
                    $data['gender'],
                    $data['address'],
                    $data['nin_number'],
                    $data['bvn_number'],
                    $data['next_of_kin_name'],
                    $data['next_of_kin_relationship'],
                    $data['next_of_kin_phone'],
                    $data['passport_path'],
                    $data['signature_path'],
                    $data['employment_type'],
                    $entry['id']
                ]);
                $success = "Information saved! The admin will review your submission.";
                // Refresh entry
                $stmt->execute([$code_id]);
                $entry = $stmt->fetch();

            } else {
                // Insert
                $sql = "INSERT INTO hr_onboarding_entries (
                    code_id, signup_code, employment_type, full_name, phone, email, date_of_birth, gender, address,
                    nin_number, bvn_number, next_of_kin_name, next_of_kin_relationship, next_of_kin_phone,
                    passport_path, signature_path, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')";

                $pdo->prepare($sql)->execute([
                    $data['code_id'],
                    $data['signup_code'],
                    $data['employment_type'],
                    $data['full_name'],
                    $data['phone'],
                    $data['email'],
                    $data['date_of_birth'],
                    $data['gender'],
                    $data['address'],
                    $data['nin_number'],
                    $data['bvn_number'],
                    $data['next_of_kin_name'],
                    $data['next_of_kin_relationship'],
                    $data['next_of_kin_phone'],
                    $data['passport_path'],
                    $data['signature_path']
                ]);
                $success = "Registration Submitted! You can come back to edit until approval.";
                // Refresh entry
                $stmt->execute([$code_id]);
                $entry = $stmt->fetch();
            }
        }
    }
}

// Values shown in the form: the applicant's last POST wins (so errors never
// wipe their work), otherwise the saved draft, otherwise the session phone.
function signupValue($field, $entry, $fallback = '', $postKey = null)
{
    $postKey = $postKey ?? $field;
    if (array_key_exists($postKey, $_POST)) {
        return (string)$_POST[$postKey];
    }
    return (string)($entry[$field] ?? $fallback);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form |
        <?php echo COMPANY_NAME; ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-100 min-h-screen py-10">

    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="bg-blue-900 px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold">New Employee Registration</h1>
                <p class="text-blue-200 text-sm">Welcome, please complete your details below.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="../../../login.php" class="text-xs bg-white text-blue-900 hover:bg-blue-50 px-3 py-1 rounded font-bold">Sign in</a>
                <a href="../../../signup.php" class="text-xs bg-blue-800 hover:bg-blue-700 px-3 py-1 rounded">Exit</a>
            </div>
        </div>

        <div class="bg-blue-50 border-b border-blue-100 px-8 py-3 text-sm text-blue-900">
            Registering as <strong><?php echo htmlspecialchars(onboardingTypeLabel($employment_type), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="text-blue-500">• code <?php echo htmlspecialchars($signup_code, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 border-b border-red-200 text-sm font-medium">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 border-b border-green-200 text-center font-bold">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($entry && $entry['status'] === 'imported'): ?>
            <div class="p-10 text-center">
                <div class="text-green-500 text-5xl mb-4"><i class="fa-solid fa-check-circle"></i></div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Registration Approved!</h2>
                <p class="text-gray-600">Your account has been created. Please contact HR for your login credentials.</p>
            </div>
        <?php else: ?>

            <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
                <?php echo csrfField(); ?>
                <!-- Personal Info -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars(signupValue('full_name', $entry), ENT_QUOTES, 'UTF-8'); ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars(signupValue('email', $entry), ENT_QUOTES, 'UTF-8'); ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars(signupValue('phone', $entry, $phone), ENT_QUOTES, 'UTF-8'); ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo htmlspecialchars(signupValue('date_of_birth', $entry, '', 'dob'), ENT_QUOTES, 'UTF-8'); ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gender</label>
                            <select name="gender" class="w-full rounded border-gray-300 p-2 border">
                                <option value="male" <?php echo (signupValue('gender', $entry, 'male') == 'male') ? 'selected' : ''; ?>
                                    >Male</option>
                                <option value="female" <?php echo (signupValue('gender', $entry) == 'female') ? 'selected' : ''; ?>
                                    >Female</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Residential Address</label>
                            <textarea name="address" rows="2"
                                class="w-full rounded border-gray-300 p-2 border"><?php echo htmlspecialchars(signupValue('address', $entry), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Identity Files -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Identity & Files</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Passport Photo</label>
                            <?php if (!empty($entry['passport_path'])): ?>
                                <img src="../../../<?php echo htmlspecialchars($entry['passport_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="h-20 w-20 object-cover rounded mb-2 border">
                            <?php endif; ?>
                            <input type="file" name="passport" accept="image/*" class="w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Signature Scan</label>
                            <?php if (!empty($entry['signature_path'])): ?>
                                <img src="../../../<?php echo htmlspecialchars($entry['signature_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="h-10 object-contain mb-2 border bg-gray-50">
                            <?php endif; ?>
                            <input type="file" name="signature" accept="image/*" class="w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">NIN Number</label>
                            <input type="text" name="nin" value="<?php echo htmlspecialchars(signupValue('nin_number', $entry, '', 'nin'), ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">BVN Number</label>
                            <input type="text" name="bvn" value="<?php echo htmlspecialchars(signupValue('bvn_number', $entry, '', 'bvn'), ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                    </div>
                </div>

                <!-- Next of Kin -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Next of Kin</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="nok_name" value="<?php echo htmlspecialchars(signupValue('next_of_kin_name', $entry), ENT_QUOTES, 'UTF-8'); ?>"
                                required class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Relationship</label>
                            <input type="text" name="nok_rel"
                                value="<?php echo htmlspecialchars(signupValue('next_of_kin_relationship', $entry), ENT_QUOTES, 'UTF-8'); ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="nok_phone" value="<?php echo htmlspecialchars(signupValue('next_of_kin_phone', $entry), ENT_QUOTES, 'UTF-8'); ?>"
                                required class="w-full rounded border-gray-300 p-2 border">
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit"
                        class="w-full bg-blue-900 text-white py-4 rounded-xl font-bold text-lg hover:bg-blue-800 shadow-lg">
                        Submit Registration
                    </button>
                    <p class="text-center text-xs text-gray-500 mt-2">By submitting, you confirm the details are accurate.
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>

</body>

</html>
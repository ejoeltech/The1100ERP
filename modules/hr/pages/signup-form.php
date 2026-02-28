<?php
// HR Onboarding Form (Self Service)
require_once '../../../config.php';
session_start();

if (!isset($_SESSION['onboarding_code_id'])) {
    header("Location: ../../../signup.php");
    exit;
}

$code_id = $_SESSION['onboarding_code_id'];
$signup_code = $_SESSION['onboarding_code'];
$phone = $_SESSION['onboarding_phone'];

// Check if entry exists or create one
$stmt = $pdo->prepare("SELECT * FROM hr_onboarding_entries WHERE code_id = ?");
$stmt->execute([$code_id]);
$entry = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle File Uploads (Passport & Signature)
    $uploadDir = '../assets/uploads/onboarding/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Logic similar to employee-form.php but simplified
    $passport_path = $entry['passport_path'] ?? null;
    if (isset($_FILES['passport']) && $_FILES['passport']['error'] == 0) {
        $ext = pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION);
        $filename = 'pass_' . $signup_code . '.' . $ext;
        move_uploaded_file($_FILES['passport']['tmp_name'], $uploadDir . $filename);
        $passport_path = 'modules/hr/assets/uploads/onboarding/' . $filename; // Store relative to root for easy access later
    }

    $signature_path = $entry['signature_path'] ?? null;
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
        $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $filename = 'sig_' . $signup_code . '.' . $ext;
        move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $filename);
        $signature_path = 'modules/hr/assets/uploads/onboarding/' . $filename;
    }

    // Prepare Data
    $data = [
        'code_id' => $code_id,
        'signup_code' => $signup_code,
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'], // They can update it here
        'email' => $_POST['email'],
        'date_of_birth' => $_POST['dob'],
        'gender' => $_POST['gender'],
        'address' => $_POST['address'],
        'nin_number' => $_POST['nin'],
        'bvn_number' => $_POST['bvn'],
        'next_of_kin_name' => $_POST['nok_name'],
        'next_of_kin_relationship' => $_POST['nok_rel'],
        'next_of_kin_phone' => $_POST['nok_phone'],
        'passport_path' => $passport_path,
        'signature_path' => $signature_path
    ];

    if ($entry) {
        // Update
        $sql = "UPDATE hr_onboarding_entries SET 
            full_name=?, phone=?, email=?, date_of_birth=?, gender=?, address=?, 
            nin_number=?, bvn_number=?, next_of_kin_name=?, next_of_kin_relationship=?, next_of_kin_phone=?, 
            passport_path=?, signature_path=?, status='submitted'
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
            $entry['id']
        ]);
        $success = "Information saved! The admin will review your submission.";
        // Refresh entry
        $stmt->execute([$code_id]);
        $entry = $stmt->fetch();

    } else {
        // Insert
        // Note: We need code_id and signup_code
        $sql = "INSERT INTO hr_onboarding_entries (
            code_id, signup_code, full_name, phone, email, date_of_birth, gender, address, 
            nin_number, bvn_number, next_of_kin_name, next_of_kin_relationship, next_of_kin_phone, 
            passport_path, signature_path, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')";

        $pdo->prepare($sql)->execute(array_values($data));
        $success = "Registration Submitted! You can come back to edit until approval.";
        // Refresh entry
        $stmt->execute([$code_id]);
        $entry = $stmt->fetch();
    }
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
            <a href="../../../signup.php" class="text-xs bg-blue-800 hover:bg-blue-700 px-3 py-1 rounded">Logout</a>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 border-b border-green-200 text-center font-bold">
                <?php echo $success; ?>
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
                <!-- Personal Info -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo $entry['full_name'] ?? ''; ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" value="<?php echo $entry['email'] ?? ''; ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo $entry['phone'] ?? $phone; ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo $entry['date_of_birth'] ?? ''; ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gender</label>
                            <select name="gender" class="w-full rounded border-gray-300 p-2 border">
                                <option value="male" <?php echo (($entry['gender'] ?? '') == 'male') ? 'selected' : ''; ?>
                                    >Male</option>
                                <option value="female" <?php echo (($entry['gender'] ?? '') == 'female') ? 'selected' : ''; ?>
                                    >Female</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Residential Address</label>
                            <textarea name="address" rows="2"
                                class="w-full rounded border-gray-300 p-2 border"><?php echo $entry['address'] ?? ''; ?></textarea>
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
                                <img src="../../../<?php echo $entry['passport_path']; ?>"
                                    class="h-20 w-20 object-cover rounded mb-2 border">
                            <?php endif; ?>
                            <input type="file" name="passport" accept="image/*" class="w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Signature Scan</label>
                            <?php if (!empty($entry['signature_path'])): ?>
                                <img src="../../../<?php echo $entry['signature_path']; ?>"
                                    class="h-10 object-contain mb-2 border bg-gray-50">
                            <?php endif; ?>
                            <input type="file" name="signature" accept="image/*" class="w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">NIN Number</label>
                            <input type="text" name="nin" value="<?php echo $entry['nin_number'] ?? ''; ?>"
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">BVN Number</label>
                            <input type="text" name="bvn" value="<?php echo $entry['bvn_number'] ?? ''; ?>"
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
                            <input type="text" name="nok_name" value="<?php echo $entry['next_of_kin_name'] ?? ''; ?>"
                                required class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Relationship</label>
                            <input type="text" name="nok_rel"
                                value="<?php echo $entry['next_of_kin_relationship'] ?? ''; ?>" required
                                class="w-full rounded border-gray-300 p-2 border">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="nok_phone" value="<?php echo $entry['next_of_kin_phone'] ?? ''; ?>"
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
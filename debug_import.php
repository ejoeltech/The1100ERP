<?php
require_once 'config.php';

echo "Attempting to import submitted entry (ID: 1)...\n";

try {
    $entry_id = 1; // Explicitly targeting the stuck entry
    $stmt = $pdo->prepare("SELECT * FROM hr_onboarding_entries WHERE id = ?");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch();

    if (!$entry) {
        die("Entry not found or already processed.\n");
    }

    echo "Found Entry: " . $entry['full_name'] . "\n";

    $pdo->beginTransaction();

    // 1. Create User
    $username = strtolower(explode(' ', trim($entry['full_name']))[0]) . rand(100, 999);
    $password_hash = password_hash($entry['phone'], PASSWORD_DEFAULT);

    // Default role 'viewer' as 'staff' is not in enum and per HR_Employee logic
    $role = 'viewer';

    // Check if email already exists in users
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $chk->execute([$entry['email']]);
    if ($chk->fetch()) {
        echo "Warning: Email already exists in users table. Skipping User Creation (using existing).\n";
        $user_id = $chk->fetchColumn();
    } else {
        echo "Creating User ($username)...\n";
        // Fixed: using 'is_active' = 1 instead of 'status' = 'active'
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$username, $password_hash, $entry['email'], $entry['full_name'], $role]);
        $user_id = $pdo->lastInsertId();
        echo "User Created: ID $user_id\n";
    }

    // 2. Create HR Employee
    $emp_code = 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    echo "Creating Employee ($emp_code)...\n";

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

    echo "HR Employee Record Created.\n";

    // 3. Mark as Imported
    // $pdo->prepare("UPDATE hr_onboarding_entries SET status = 'imported' WHERE id = ?")->execute([$entry_id]);
    // echo "Status updated to Imported.\n";

    // ROLLBACK FOR TESTING so we don't actually change DB state yet
    $pdo->rollBack();
    echo "Transaction ROLLED BACK (Test Successful). No errors found.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
?>
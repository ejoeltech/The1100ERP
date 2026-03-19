<?php
// HR ID Card Generator - Template Edition
// require_once '../../../config.php'; // Removed to prevent double inclusion
require_once '../../../includes/session-check.php';
require_once '../classes/HR_Employee.php';

requireLogin();

$pageTitle = 'ID Card Generator | ' . COMPANY_NAME;

$hr_employee = new HR_Employee($pdo);
$employees = $hr_employee->getAllEmployees(1000);

$selected_employee = null;
if (isset($_GET['id'])) {
    $emp = $hr_employee->getEmployeeById($_GET['id']);
    if ($emp) {
        $selected_employee = $emp;
    }
}

// Fetch Settings & Templates
$stmt = $pdo->query("SELECT * FROM hr_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Prepare Static Data
$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'COMPANY NAME';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$company_phone = defined('COMPANY_PHONE') ? COMPANY_PHONE : '';
$company_email = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : '';
$logo_html = (defined('COMPANY_LOGO') && COMPANY_LOGO)
    ? '<img src="../../../' . COMPANY_LOGO . '" style="width: 80%; height: auto; max-width: 100%;">'
    : '<div style="width:20px;height:20px;background:#0072bc;border-radius:50%"></div>';

// Text Vars
$subtitle = $settings['id_card_subtitle_text'] ?? 'TECHNOLOGIES';
$emergency_label = $settings['id_card_emergency_label'] ?? 'IN CASE OF EMERGENCY CONTACT:';
$disclaimer = $settings['id_card_disclaimer_text'] ?? '';
$color_primary = $settings['id_card_primary_color'] ?? '#0072bc';
$color_secondary = $settings['id_card_secondary_color'] ?? '#39b54a';
$color_tertiary = $settings['id_card_tertiary_color'] ?? '#005a9c';
$show_name = $settings['id_card_show_name'] ?? '1';

// Additional CSS for toggles
$toggle_css = ($show_name === '0') ? '.brand-name { display: none !important; }' : '';


// Load Templates from DB (Must populate these via Designer first, or fallbacks if empty, but Designer handles defaults)
$front_template = $settings['id_card_front_html'] ?? '';
$back_template = $settings['id_card_back_html'] ?? '';
$custom_css = $settings['id_card_custom_css'] ?? '';

// Fallback if empty (Shouldn't happen if Designer accessed once)
if (empty($front_template) || empty($custom_css)) {
    echo "Please visit the <a href='id-card-designer.php'>ID Card Designer</a> first to initialize the templates.";
    exit;
}

include_once '../../../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/id-card-designer-advanced.css">

<style>
    /* Generator Specific Styles */
    .page-container {
        display: flex;
        flex-wrap: wrap;
        gap: 40px;
        padding: 40px;
        justify-content: center;
        background: #f0f0f0;
    }

    .print-btn {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #6366f1;
        color: white;
        padding: 12px 24px;
        border: none;
        cursor: pointer;
        border-radius: 12px;
        font-weight: 800;
        z-index: 1000;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    @media print {
        header, nav, .navbar, footer, .print-btn, .selector-panel {
            display: none !important;
        }
        body { background: white; margin: 0; padding: 0; }
        .page-container { padding: 0; gap: 0; display: block; margin: 0; background: none; }
        .card-wrapper { break-inside: avoid; margin-bottom: 20px; display: inline-block; margin-right: 20px; }
        main { padding: 0 !important; margin: 0 !important; max-width: none !important; }
    }
</style>

<button onclick="window.print()" class="print-btn"><i class="fa-solid fa-print"></i> Print ID Cards</button>

<?php if (!$selected_employee): ?>
    <div class="selector-panel" style="padding: 40px; background: white; text-align: center;">
        <h3 class="text-2xl font-black text-gray-800 mb-6">Select Employee</h3>
        <form method="GET">
            <select name="id" onchange="this.form.submit()" class="p-4 border-2 border-gray-100 rounded-2xl w-full max-w-md text-lg">
                <option value="">-- Choose Employee --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo $emp['employee_code']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
<?php else: ?>

    <div class="page-container">
        <?php
        $emp = $selected_employee;
        $config = json_decode($settings['id_card_designer_config'] ?? '{}', true);
        
        // Merge with defaults if missing
        $frontConfig = $config['front'] ?? [];
        $backConfig = $config['back'] ?? [];

        $photo_url = !empty($emp['passport_path']) ? $emp['passport_path'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&size=200&background=ccc&color=fff';
        $signature_url = !empty($emp['signature_path']) ? $emp['signature_path'] : '';
        $signature_html = $signature_url ? '<img src="' . $signature_url . '" style="height: 40px; width: auto;">' : '<span style="font-family:cursive; font-size:12px;">Authorized Sig.</span>';

        $qr_data = $emp['employee_code'] . '|' . $emp['full_name'];
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_data);
        $qr_img = '<img src="' . $qr_url . '" class="qr-code" style="width: 100%; height: 100%;">';

        $commonData = [
            '{{company_name}}' => $company_name,
            '{{company_logo}}' => $logo_html,
            '{{photo_url}}' => $photo_url,
            '{{full_name}}' => htmlspecialchars($emp['full_name']),
            '{{designation}}' => htmlspecialchars($emp['designation_name'] ?? $emp['designation'] ?? 'Staff'),
            '{{employee_code}}' => $emp['employee_code'],
            '{{phone}}' => $emp['phone'],
            '{{email}}' => $emp['email'],
            '{{qr_code}}' => $qr_img,
            '{{signature}}' => $signature_html,
            '{{emergency_contact}}' => $emp['next_of_kin_phone'] ?? 'N/A',
            '{{company_address}}' => $company_address,
            '{{company_phone}}' => $company_phone,
            '{{company_email}}' => $company_email,
            '{{company_website}}' => defined('COMPANY_WEBSITE') ? COMPANY_WEBSITE : '',
            '{{emergency_label}}' => htmlspecialchars($settings['id_card_emergency_label'] ?? 'EMERGENCY CONTACT'),
            '{{disclaimer}}' => $settings['id_card_disclaimer_text'] ?? 'Terms and conditions apply.',
            '{{color_primary}}' => $frontConfig['primary_color'] ?? '#0072bc',
            '{{color_secondary}}' => $frontConfig['secondary_color'] ?? '#39b54a'
        ];

        // Map config to placeholders
        $frontMap = [];
        foreach($frontConfig as $k => $v) $frontMap['{{'.$k.'}}'] = $v;
        
        $backMap = [];
        foreach($backConfig as $k => $v) $backMap['{{'.$k.'}}'] = $v;

        // Render Front
        $front_template = file_get_contents('../templates/id-front-advanced.html');
        $final_front = str_replace(array_keys($commonData), array_values($commonData), $front_template);
        $final_front = str_replace(array_keys($frontMap), array_values($frontMap), $final_front);

        // Render Back
        $back_template = file_get_contents('../templates/id-back-advanced.html');
        $final_back = str_replace(array_keys($commonData), array_values($commonData), $back_template);
        $final_back = str_replace(array_keys($backMap), array_values($backMap), $final_back);
        ?>

        <div class="card-wrapper">
            <?php echo $final_front; ?>
        </div>

        <div class="card-wrapper">
            <?php echo $final_back; ?>
        </div>
    </div>
<?php endif; ?>

<?php include_once '../../../includes/footer.php'; ?>
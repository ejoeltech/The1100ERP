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

// Templates are now loaded from files, we only need the custom CSS if it exists
if (empty($custom_css)) {
    // We can still proceed, but the designer is recommended
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

<div class="fixed top-5 right-5 flex gap-3 z-[1000]">
    <button onclick="window.print()" class="print-btn bg-gray-800 hover:bg-black transition-all shadow-lg"><i class="fa-solid fa-print"></i> Print</button>
    <a href="../api/export-id-pdf.php?id=<?php echo $selected_employee['id']; ?>" class="print-btn !static bg-indigo-600 hover:bg-indigo-700 transition-all shadow-lg"><i class="fa-solid fa-file-pdf"></i> Download HD PDF</a>
</div>

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
        $frontConfig = array_merge([
            'primary_color' => '#0072bc',
            'secondary_color' => '#39b54a',
            'logo_x' => 100, 'logo_y' => 20, 'logo_width' => 80, 'logo_height' => 50,
            'brand_x' => 80, 'brand_y' => 80, 'brand_size' => 24, 'brand_color' => '#1a1a1a', 'show_brand' => 'block',
            'photo_x' => 100, 'photo_y' => 130, 'photo_w' => 150, 'photo_h' => 150, 'photo_radius' => 50, 'photo_border_w' => 4, 'photo_border_color' => '#0072bc',
            'name_x' => 75, 'name_y' => 290, 'name_size' => 22, 'name_color' => '#1a1a1a', 'name_w' => 200,
            'role_x' => 110, 'role_y' => 365, 'role_size' => 16, 'role_color' => '#0072bc', 'show_role' => 'block',
            'code_x' => 100, 'code_y' => 400, 'show_code' => 'block',
            'qr_x' => 260, 'qr_y' => 460, 'show_qr' => 'block',
            'qr_size' => 150,
            'qr_template' => '{{employee_code}} | {{full_name}}'
        ], $config['front'] ?? []);
        
        $backConfig = array_merge([
            'bg_color_back' => '#f9f9f9',
            'wave_height_back' => 120,
            'wave_opacity_back' => 0.7,
            'back_title' => 'THIS IS TO CERTIFY THAT',
            'back_content' => "The bearer of this identification card is a duly registered member/staff of {{company_name}}. \n\nThis card is issued for identification purposes only and must be presented upon request. If found, please return to the address below. \n\nUnauthorized use, duplication, or possession of this card is strictly prohibited and may result in disciplinary or legal action.",
            'btitle_x' => 0, 'btitle_y' => 35, 'btitle_w' => 350, 'btitle_size' => 11,
            'btext_x' => 0, 'btext_y' => 70, 'btext_w' => 350,
            'sig_x' => 35, 'sig_y' => 260, 'sig_w' => 280,
            'business_address' => $company_address ?: "123 Business Street, Lagos, Nigeria\n+234 800 000 0000 | info@bluedots.com",
            'addr_x' => 35, 'addr_y' => 430, 'addr_w' => 200,
            'qr_x_back' => 260, 'qr_y_back' => 450, 'qr_size_back' => 65,
            'show_qr_back' => 'block'
        ], $config['back'] ?? []);

        $photo_url = !empty($emp['passport_path']) ? $emp['passport_path'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&size=200&background=ccc&color=fff';
        $signature_url = !empty($emp['signature_path']) ? $emp['signature_path'] : '';
        $signature_html = $signature_url ? '<img src="' . $signature_url . '" style="height: 40px; width: auto;">' : '<span style="font-family:cursive; font-size:12px;">Authorized Sig.</span>';

        // QR Logic (Front)
        $qr_template = $frontConfig['qr_template'] ?? '{{employee_code}} | {{full_name}}';
        $qr_data = str_replace(
            ['{{employee_code}}', '{{full_name}}', '{{phone}}', '{{email}}'],
            [$emp['employee_code'], $emp['full_name'], $emp['phone'], $emp['email']],
            $qr_template
        );
        $qr_size_front = $frontConfig['qr_size'] ?? 150;
        $qr_url_front = 'https://api.qrserver.com/v1/create-qr-code/?size='.$qr_size_front.'x'.$qr_size_front.'&data=' . urlencode($qr_data);
        $qr_img_front = '<img src="' . $qr_url_front . '" class="qr-code" style="width: 100%; height: 100%;">';

        // QR Logic (Back)
        $qr_size_back = $backConfig['qr_size_back'] ?? 65;
        $qr_url_back = 'https://api.qrserver.com/v1/create-qr-code/?size='.$qr_size_back.'x'.$qr_size_back.'&data=' . urlencode($qr_data);
        $qr_img_back = '<img src="' . $qr_url_back . '" class="qr-code" style="width: 100%; height: 100%; border:none;">';

        $commonData = [
            '{{company_name}}' => $company_name,
            '{{company_logo}}' => $logo_html,
            '{{photo_url}}' => $photo_url,
            '{{full_name}}' => htmlspecialchars($emp['full_name']),
            '{{designation}}' => htmlspecialchars($emp['designation_name'] ?? $emp['designation'] ?? 'Staff'),
            '{{employee_code}}' => $emp['employee_code'],
            '{{phone}}' => $emp['phone'],
            '{{email}}' => $emp['email'],
            '{{qr_code}}' => $qr_img_front,
            '{{qr_placeholder_back}}' => $qr_img_back,
            '{{signature}}' => $signature_html,
            '{{emergency_contact}}' => $emp['next_of_kin_phone'] ?? 'N/A',
            '{{company_address}}' => $company_address,
            '{{company_phone}}' => $company_phone,
            '{{company_email}}' => $company_email,
            '{{company_website}}' => defined('COMPANY_WEBSITE') ? COMPANY_WEBSITE : '',
            '{{emergency_label}}' => htmlspecialchars($settings['id_card_emergency_label'] ?? 'EMERGENCY CONTACT'),
            '{{disclaimer}}' => $settings['id_card_disclaimer_text'] ?? 'Terms and conditions apply.',
            '{{business_address}}' => $backConfig['business_address'] ?? $company_address,
            '{{back_title}}' => $backConfig['back_title'] ?? 'TERMS & CONDITIONS',
            '{{back_content}}' => $backConfig['back_content'] ?? 'If found, please return to any of our offices.',
            '{{color_primary}}' => $frontConfig['primary_color'] ?? '#0072bc',
            '{{color_secondary}}' => $frontConfig['secondary_color'] ?? '#39b54a',
            '{{bg_color_back}}' => $backConfig['bg_color_back'] ?? '#f9f9f9',
            '{{wave_height_back}}' => $backConfig['wave_height_back'] ?? 120,
            '{{wave_opacity_back}}' => $backConfig['wave_opacity_back'] ?? 0.7
        ];

        // Map config to placeholders (Front & Back)
        $frontMap = [];
        foreach($frontConfig as $k => $v) $frontMap['{{'.$k.'}}'] = $v;
        
        $backMap = [];
        foreach($backConfig as $k => $v) $backMap['{{'.$k.'}}'] = $v;
        
        // Merge to ensure all coordinates are available to both templates if needed
        $fullConfigMap = array_merge($frontMap, $backMap);

        // Render Front
        $front_template = file_get_contents('../templates/id-front-advanced.html');
        $final_front = str_replace(array_keys($commonData), array_values($commonData), $front_template);
        $final_front = str_replace(array_keys($fullConfigMap), array_values($fullConfigMap), $final_front);
        $final_front = str_replace(array_keys($commonData), array_values($commonData), $final_front); // Second pass for nested placeholders

        // Render Back
        $back_template = file_get_contents('../templates/id-back-advanced.html');
        $final_back = str_replace(array_keys($commonData), array_values($commonData), $back_template);
        $final_back = str_replace(array_keys($fullConfigMap), array_values($fullConfigMap), $final_back);
        $final_back = str_replace(array_keys($commonData), array_values($commonData), $final_back); // Second pass for nested placeholders
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
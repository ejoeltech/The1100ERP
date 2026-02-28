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

<style>
    /* Generator Specific Styles */
    .page-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        padding: 20px;
        justify-content: center;
    }

    .print-btn {
        position: fixed;
        top: 100px;
        right: 20px;
        background: #0072bc;
        color: white;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        border-radius: 5px;
        font-weight: bold;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    @media print {

        header,
        nav,
        .navbar,
        footer,
        .print-btn,
        .selector-panel {
            display: none !important;
        }

        body {
            background: white;
            margin: 0;
            padding: 0;
        }

        .page-container {
            padding: 0;
            gap: 0;
            display: block;
            margin: 0;
        }

        .card-wrapper {
            break-inside: avoid;
            margin-bottom: 20px;
            display: inline-block;
            margin-right: 20px;
        }

        main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
        }

        /* Override main container padding */
    }

    /* INJECT SAVED CSS */
    :root {
        --brand-blue:
            <?php echo $color_primary; ?>
        ;
        --brand-green:
            <?php echo $color_secondary; ?>
        ;
        --dark-blue:
            <?php echo $color_tertiary; ?>
        ;
        --card-width: 350px;
        --card-height: 550px;
        --card-radius: 15px;
    }

    /* Structural Enforcements */
    .qr-section {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .id-section {
        width: 100%;
        text-align: center;
    }

    <?php echo $custom_css; ?>
    <?php echo $toggle_css; ?>
</style>

<button onclick="window.print()" class="print-btn"><i class="fa-solid fa-print"></i> Print Cards</button>


<?php if (!$selected_employee): ?>
    <div class="selector-panel" style="padding: 20px; background: white;">
        <h3>Select Employee to Generate</h3>
        <form method="GET">
            <select name="id" onchange="this.form.submit()" style="padding: 10px; width: 300px;">
                <option value="">-- Choose Employee --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?>
                        (<?php echo $emp['employee_code']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
<?php else: ?>

    <div class="page-container">

        <!-- Generate FRONT and BACK for Selected Employee -->
        <?php
        $emp = $selected_employee;

        // Mappings
        // DB stores path as relative to modules/hr/pages/ (e.g. "../assets/uploads/...")
        // So we can use it directly if we are in that directory.
        $photo_url = !empty($emp['passport_path']) ? $emp['passport_path'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&size=200&background=ccc&color=fff';

        $signature_url = !empty($emp['signature_path']) ? $emp['signature_path'] : '';
        $signature_html = $signature_url ? '<img src="' . $signature_url . '" style="height: 40px; width: auto;">' : '<span style="font-family:cursive; font-size:12px;">Authorized Sig.</span>';

        $qr_data = $emp['employee_code'] . '|' . $emp['full_name'] . '|' . $company_name;
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_data);
        $qr_img = '<img src="' . $qr_url . '" class="qr-code" style="width: 70px; height: 70px;">';

        $placeholders = [
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
            '{{subtitle}}' => htmlspecialchars($subtitle),
            '{{emergency_label}}' => htmlspecialchars($emergency_label),
            '{{disclaimer}}' => $disclaimer, // HTML allowed
            '{{color_primary}}' => $color_primary,
            '{{color_secondary}}' => $color_secondary,
            '{{color_tertiary}}' => $color_tertiary
        ];

        // Replace
        $final_front = str_replace(array_keys($placeholders), array_values($placeholders), $front_template);
        $final_back = str_replace(array_keys($placeholders), array_values($placeholders), $back_template);
        ?>

        <div class="card-wrapper">
            <div class="id-card">
                <?php echo $final_front; ?>
            </div>
        </div>

        <div class="card-wrapper">
            <div class="id-card">
                <?php echo $final_back; ?>
            </div>
        </div>

    </div>

<?php endif; ?>

<?php include_once '../../../includes/footer.php'; ?>
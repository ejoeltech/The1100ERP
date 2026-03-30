<?php
require_once '../../../config.php';
require_once '../../../includes/session-check.php';
requireLogin();

if (!file_exists('../../../vendor/autoload.php')) {
    die('mPDF not found. Please run composer install.');
}
require_once '../../../vendor/autoload.php';

$emp_id = $_GET['id'] ?? null;
if (!$emp_id) die('Missing Employee ID');

// Fetch Employee
$stmt = $pdo->prepare("SELECT e.*, d.name as designation_name FROM hr_employees e LEFT JOIN hr_designations d ON e.designation_id = d.id WHERE e.id = ?");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch();
if (!$emp) die('Employee not found');

// Fetch Settings
$stmt = $pdo->query("SELECT * FROM hr_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
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

// Prepare Data
$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'COMPANY NAME';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$logo_path = (defined('COMPANY_LOGO') && COMPANY_LOGO) ? '../../../' . COMPANY_LOGO : '';
$logo_html = $logo_path ? '<img src="' . $logo_path . '" style="width:100%; height:auto;">' : '';

$photo_url = !empty($emp['passport_path']) ? $emp['passport_path'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&size=200&background=ccc&color=fff';

// QR Logic (Front)
$qr_template = $frontConfig['qr_template'] ?? '{{employee_code}} | {{full_name}}';
$qr_data = str_replace(
    ['{{employee_code}}', '{{full_name}}', '{{phone}}', '{{email}}'],
    [$emp['employee_code'], $emp['full_name'], $emp['phone'], $emp['email']],
    $qr_template
);
$qr_size_front = $frontConfig['qr_size'] ?? 150;
$qr_url_front = 'https://api.qrserver.com/v1/create-qr-code/?size='.$qr_size_front.'x'.$qr_size_front.'&data=' . urlencode($qr_data);

// QR Logic (Back)
$qr_size_back = $backConfig['qr_size_back'] ?? 65;
$qr_url_back = 'https://api.qrserver.com/v1/create-qr-code/?size='.$qr_size_back.'x'.$qr_size_back.'&data=' . urlencode($qr_data);

$commonData = [
    '{{company_name}}' => $company_name,
    '{{company_logo}}' => $logo_html,
    '{{photo_url}}' => $photo_url,
    '{{full_name}}' => htmlspecialchars($emp['full_name']),
    '{{designation}}' => htmlspecialchars($emp['designation_name'] ?? $emp['designation'] ?? 'Staff'),
    '{{employee_code}}' => $emp['employee_code'],
    '{{phone}}' => $emp['phone'],
    '{{email}}' => $emp['email'],
    '{{qr_code}}' => '<img src="' . $qr_url_front . '" style="width:100%; height:100%;">',
    '{{qr_placeholder_back}}' => '<img src="' . $qr_url_back . '" style="width:100%; height:100%; border:none;">',
    '{{signature}}' => 'Authorized Signature',
    '{{emergency_contact}}' => $emp['next_of_kin_phone'] ?? 'N/A',
    '{{company_address}}' => $company_address,
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

// Helper to replace
function replace($tpl, $data, $fullConfigMap) {
    $html = str_replace(array_keys($data), array_values($data), $tpl);
    $html = str_replace(array_keys($fullConfigMap), array_values($fullConfigMap), $html);
    $html = str_replace(array_keys($data), array_values($data), $html); // Second pass for nested placeholders
    return $html;
}

// Map config (Front & Back)
$frontMap = [];
foreach($frontConfig as $k => $v) $frontMap['{{'.$k.'}}'] = $v;
$backMap = [];
foreach($backConfig as $k => $v) $backMap['{{'.$k.'}}'] = $v;

$fullConfigMap = array_merge($frontMap, $backMap);

$front_tpl = file_get_contents('../templates/id-front-advanced.html');
$back_tpl = file_get_contents('../templates/id-back-advanced.html');

$html_front = replace($front_tpl, $commonData, $fullConfigMap);
$html_back = replace($back_tpl, $commonData, $fullConfigMap);

// Base Styles for mPDF (Absolute positioning support)
$css = file_get_contents('../assets/css/id-card-designer-advanced.css');
// Adjust CSS for mPDF if needed (mpdf handles absolute better with some tweaks)
$css .= "
    .id-card-canvas { border: 1px solid #eee; margin: 0 auto; }
    body { font-family: 'Inter', sans-serif; }
";

try {
    // ID Card Size: 85.6mm x 54mm (CR80)
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => [85.6, 54],
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
        'orientation' => 'P'
    ]);

    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    
    // Front Page
    $mpdf->WriteHTML($html_front, \Mpdf\HTMLParserMode::HTML_BODY);
    
    // Back Page
    $mpdf->AddPage();
    $mpdf->WriteHTML($html_back, \Mpdf\HTMLParserMode::HTML_BODY);

    $filename = 'ID_CARD_' . $emp['employee_code'] . '.pdf';
    $mpdf->Output($filename, 'D');

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

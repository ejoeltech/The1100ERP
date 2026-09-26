<?php
define('IS_API', true);
require_once '../../includes/security.php';
configureSessionCookies();
session_start();

require_once '../../config.php';
require_once '../../includes/helpers.php';

// Use mPDF (already installed via composer; Dompdf is not a dependency)
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/pdf');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

try {
    // Get the recommendation data from POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['recommendation']) || !isset($input['form_data'])) {
        throw new Exception('Missing required data');
    }

    $recommendation = $input['recommendation'];
    $formData = $input['form_data'];

    // Prepare data for PDF template
    $data = [];

    // Mode label
    $data['mode_label'] = ($formData['mode'] == 1) ? 'Design Planner' : 'Design Implementation';

    // System type label
    $data['system_type_label'] = ($formData['system_type'] === 'hybrid')
        ? 'Hybrid Inverter'
        : 'Charge Controller';

    // System specifications
    $data['inverter_capacity'] = $formData['inverter_capacity'] ?? 'N/A';
    $data['battery_voltage'] = $formData['battery_voltage'] ?? 'N/A';
    $data['controller_capacity'] = $formData['controller_capacity'] ?? 'N/A';
    $data['max_voltage'] = $formData['max_voltage'] ?? 'N/A';
    $data['max_current'] = $formData['max_current'] ?? 'N/A';

    // Panel info if provided
    if (!empty($formData['panel_power'])) {
        $data['panel_power'] = $formData['panel_power'];
        $data['panel_voc'] = $formData['panel_voc'];
        $data['panel_isc'] = $formData['panel_isc'];
    }

    // Load the PDF template
    ob_start();
    include __DIR__ . '/../../includes/recommendation-pdf-template.php';
    $html = ob_get_clean();

    // Generate filename
    $filename = 'Solar_Recommendation_' . date('Y-m-d_His') . '.pdf';

    // Configure mPDF (mirrors the previous DOMPDF settings)
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font' => 'Inter'
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating PDF. Check server logs.');
}
?>
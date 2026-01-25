<?php
define('IS_API', true);
session_start();

require_once '../../config.php';
require_once '../../includes/helpers.php';

// Include DOMPDF
require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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

    // Configure DOMPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Inter');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generate filename
    $filename = 'Solar_Recommendation_' . date('Y-m-d_His') . '.pdf';

    // Output PDF
    $dompdf->stream($filename, array('Attachment' => 1));

} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating PDF: ' . $e->getMessage());
}
?>
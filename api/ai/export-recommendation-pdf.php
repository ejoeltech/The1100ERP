<?php
define('IS_API', true);
require_once '../../includes/security.php';
configureSessionCookies();
session_start();

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/invites.php';

// This endpoint stays public because pages/system-designer.php is a public
// tool, but it is no longer an anonymous HTML-to-PDF renderer: every request
// needs the session CSRF token and is throttled per IP.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Method not allowed');
}
requireCsrf();

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!throttleCheck('ai_pdf:' . $clientIp, 10, 3600)) {
    http_response_code(429);
    header('Retry-After: 3600');
    die('Too many PDF export requests. Please try again later.');
}

// Cap the request body before decoding it.
$rawBody = file_get_contents('php://input');
if (strlen($rawBody) > 262144) {
    http_response_code(413);
    die('Request too large');
}

// Use mPDF (already installed via composer; Dompdf is not a dependency)
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');

/**
 * Allow-list formatter for the AI-generated summary block. Every other value
 * in the recommendation is rendered through htmlspecialchars() by the
 * template, so this closes the only raw-HTML sink.
 */
function sanitizeRecommendationHtml($html)
{
    if (!is_string($html)) {
        return '';
    }
    $allowed = '<b><strong><i><em><u><br><p><ul><ol><li><h1><h2><h3><h4><span><div><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);
    // Drop every attribute (style, src, href, on*, ...) from the tags that
    // remain so no remote resources or event handlers reach mPDF.
    $html = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $html);
    return $html;
}

try {
    // Get the recommendation data from POST
    $input = json_decode($rawBody, true);

    if (!is_array($input) || !isset($input['recommendation']) || !isset($input['form_data'])) {
        throw new Exception('Missing required data');
    }
    if (!is_array($input['recommendation']) || !is_array($input['form_data'])) {
        throw new Exception('Invalid data');
    }

    $recommendation = $input['recommendation'];
    $formData = $input['form_data'];

    // Never let the caller supply markup outside this allow-list.
    $recommendation['summary'] = sanitizeRecommendationHtml($recommendation['summary'] ?? '');

    // Prepare data for PDF template
    $data = [];

    // Mode label
    $data['mode_label'] = (($formData['mode'] ?? null) == 1) ? 'Design Planner' : 'Design Implementation';

    // System type label
    $data['system_type_label'] = (($formData['system_type'] ?? '') === 'hybrid')
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
        $data['panel_voc'] = $formData['panel_voc'] ?? 'N/A';
        $data['panel_isc'] = $formData['panel_isc'] ?? 'N/A';
    }
    if (!empty($formData['panel_count'])) {
        $data['panel_count'] = $formData['panel_count'];
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
        'default_font' => 'dejavusans'
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating PDF. Check server logs.');
}
?>
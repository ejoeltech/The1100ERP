<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run from the CLI.');
}
// Ensure hostile AI shapes never trigger warnings or fatal errors in the PDF template.
$data = [
    'mode_label' => 'Design Planner',
    'system_type_label' => 'Hybrid Inverter',
    'inverter_capacity' => '5000',
    'battery_voltage' => '48',
];
$recommendation = [
    'arrangement' => 'Two strings of five panels',
    'breaker' => '63A DC breaker',
    'wiring' => 42,
    'panel_recommendations' => 'not-an-array',
    'warnings' => 'Battery voltage is below the inverter minimum.',
    'summary' => '<p>Safe <strong>summary</strong><script>alert(1)</script></p>',
];

ob_start();
include __DIR__ . '/../includes/recommendation-pdf-template.php';
$html = ob_get_clean();

$failures = [];
if (strpos($html, '<script') !== false) {
    $failures[] = 'script tag leaked into PDF HTML';
}
if (strpos($html, 'Two strings of five panels') === false) {
    $failures[] = 'string arrangement was not rendered';
}
if (strpos($html, '63A DC breaker') === false) {
    $failures[] = 'string breaker was not rendered';
}
if (strpos($html, 'Battery voltage is below the inverter minimum.') === false) {
    $failures[] = 'string warning was not rendered';
}

if ($failures) {
    echo 'FAIL: ' . implode('; ', $failures) . PHP_EOL;
    exit(1);
}
echo 'PASS - PDF template tolerates scalar/array shapes and strips scripts' . PHP_EOL;
exit(0);

<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run from the CLI.');
}
require_once __DIR__ . '/../includes/ai-design-request.php';

$cases = [];
$cases['valid hybrid planner'] = [
    'input' => [
        'system_type' => 'hybrid',
        'inverter_capacity' => '5000',
        'controller_capacity' => '4000',
        'nominal_voltage' => '48',
        'max_voltage' => '450',
        'max_current' => '80',
        'battery_voltage' => '48',
        'battery_capacity_kwh' => '5.12',
        'battery_quantity' => '4',
        'battery_chemistry' => 'lithium',
    ],
    'mode' => 1,
    'expect' => 'ok',
];
$cases['partial panel specs'] = [
    'input' => [
        'system_type' => 'hybrid',
        'inverter_capacity' => '5000',
        'controller_capacity' => '4000',
        'nominal_voltage' => '48',
        'max_voltage' => '450',
        'max_current' => '80',
        'battery_voltage' => '48',
        'panel_power' => '550',
    ],
    'mode' => 1,
    'expect' => 'error',
];
$cases['implementation without panels'] = [
    'input' => [
        'system_type' => 'hybrid',
        'inverter_capacity' => '5000',
        'controller_capacity' => '4000',
        'nominal_voltage' => '48',
        'max_voltage' => '450',
        'max_current' => '80',
        'battery_voltage' => '48',
        'panel_count' => '8',
    ],
    'mode' => 2,
    'expect' => 'error',
];
$cases['valid implementation'] = [
    'input' => [
        'system_type' => 'controller',
        'inverter_capacity' => '3000',
        'controller_capacity' => '3000',
        'battery_voltage' => '24',
        'max_voltage' => '150',
        'panel_power' => '550',
        'panel_voc' => '49.5',
        'panel_isc' => '13.9',
        'panel_count' => '8',
    ],
    'mode' => 2,
    'expect' => 'ok',
];
$cases['bad numeric'] = [
    'input' => [
        'system_type' => 'hybrid',
        'inverter_capacity' => 'abc',
        'controller_capacity' => '4000',
        'nominal_voltage' => '48',
        'max_voltage' => '450',
        'max_current' => '80',
        'battery_voltage' => '48',
    ],
    'mode' => 1,
    'expect' => 'error',
];
$cases['not an array'] = ['input' => 'nope', 'mode' => 1, 'expect' => 'error'];

$failed = 0;
foreach ($cases as $name => $case) {
    try {
        $out = normalizeAiDesignRequest($case['input'], $case['mode']);
        $result = 'ok';
        $extra = isset($out['battery_quantity']) ? ' qty=' . $out['battery_quantity'] : '';
    } catch (InvalidArgumentException $e) {
        $result = 'error';
        $extra = ' (' . $e->getMessage() . ')';
    }
    $pass = $result === $case['expect'];
    if (!$pass) {
        $failed++;
    }
    echo ($pass ? 'PASS' : 'FAIL') . ' - ' . $name . ' => ' . $result . $extra . PHP_EOL;
}
exit($failed === 0 ? 0 : 1);

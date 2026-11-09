<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Validate and normalize a System Designer request before it reaches the AI.
 */
function normalizeAiDesignRequest($input, $mode)
{
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid request body.');
    }
    $type = (string)($input['system_type'] ?? '');
    if (!in_array($type, ['hybrid', 'controller'], true)) {
        throw new InvalidArgumentException('Choose a valid system type.');
    }

    $out = ['mode' => (int)$mode, 'system_type' => $type, 'has_panel_specs' => false];
    $numeric = [
        'inverter_capacity', 'controller_capacity', 'nominal_voltage', 'max_voltage',
        'max_current', 'battery_voltage', 'panel_power', 'panel_voc', 'panel_isc',
        'battery_capacity_kwh', 'battery_max_charge_current', 'battery_max_discharge_current',
    ];
    foreach ($numeric as $key) {
        if (!isset($input[$key]) || $input[$key] === '') continue;
        if (!is_scalar($input[$key])) throw new InvalidArgumentException('Invalid numeric field: ' . $key);
        $value = filter_var($input[$key], FILTER_VALIDATE_FLOAT);
        if ($value === false || !is_finite($value) || $value <= 0) {
            throw new InvalidArgumentException('Field ' . $key . ' must be a positive number.');
        }
        $out[$key] = (float)$value;
    }

    $required = $type === 'hybrid'
        ? ['inverter_capacity', 'controller_capacity', 'nominal_voltage', 'max_voltage', 'max_current', 'battery_voltage']
        : ['inverter_capacity', 'controller_capacity', 'battery_voltage', 'max_voltage'];
    foreach ($required as $key) {
        if (empty($out[$key])) throw new InvalidArgumentException('Missing required field: ' . $key);
    }

    $panelKeys = ['panel_power', 'panel_voc', 'panel_isc'];
    $panelCount = 0;
    foreach ($panelKeys as $key) { if (isset($out[$key])) $panelCount++; }
    if ($panelCount > 0 && $panelCount < 3) {
        throw new InvalidArgumentException('Provide all three panel specifications or none.');
    }
    if ($panelCount === 3) $out['has_panel_specs'] = true;
    if ((int)$mode === 2 && $panelCount !== 3) {
        throw new InvalidArgumentException('Implementation mode needs panel power, VOC and ISC.');
    }

    if ((int)$mode === 2) {
        $count = filter_var($input['panel_count'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 1000) {
            throw new InvalidArgumentException('Number of panels must be a whole number between 1 and 1000.');
        }
        $out['panel_count'] = (int)$count;
    }

    if (isset($input['battery_quantity']) && $input['battery_quantity'] !== '') {
        $qty = filter_var($input['battery_quantity'], FILTER_VALIDATE_INT);
        if ($qty === false || $qty < 1 || $qty > 200) {
            throw new InvalidArgumentException('Battery quantity must be between 1 and 200.');
        }
        $out['battery_quantity'] = (int)$qty;
    }
    if (isset($input['battery_chemistry']) && $input['battery_chemistry'] !== '') {
        $out['battery_chemistry'] = mb_substr(trim((string)$input['battery_chemistry']), 0, 40);
    }
    if (isset($input['voltage_range']) && $input['voltage_range'] !== '') {
        $out['voltage_range'] = mb_substr(trim((string)$input['voltage_range']), 0, 60);
    }

    // Sensible electrical bounds to prevent nonsense prompts.
    if (isset($out['inverter_capacity']) && $out['inverter_capacity'] > 100000) throw new InvalidArgumentException('Inverter capacity is too large.');
    if (isset($out['controller_capacity']) && $out['controller_capacity'] > 100000) throw new InvalidArgumentException('Controller capacity is too large.');
    if (isset($out['max_voltage']) && $out['max_voltage'] > 1500) throw new InvalidArgumentException('Maximum voltage is too large.');
    if (isset($out['max_current']) && $out['max_current'] > 1000) throw new InvalidArgumentException('Maximum current is too large.');
    if (isset($out['panel_power']) && $out['panel_power'] > 2000) throw new InvalidArgumentException('Panel power is too large.');

    return $out;
}

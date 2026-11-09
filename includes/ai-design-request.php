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

/**
 * Recompute the safety-critical parts of an AI recommendation. The model is
 * never trusted to do its own voltage/current arithmetic; any violation is
 * appended to the warnings and forces safe=false.
 */
function aiEnforceDesignSafety($recommendation, $input)
{
    if (!is_array($recommendation) || !is_array($input)) {
        return $recommendation;
    }

    $warnings = $recommendation['warnings'] ?? [];
    if (is_string($warnings)) {
        $warnings = trim($warnings) !== '' ? [$warnings] : [];
    }
    if (!is_array($warnings)) {
        $warnings = [];
    }

    $panelVoc = isset($input['panel_voc']) ? (float) $input['panel_voc'] : null;
    $panelIsc = isset($input['panel_isc']) ? (float) $input['panel_isc'] : null;
    $maxVoltage = isset($input['max_voltage']) ? (float) $input['max_voltage'] : null;
    $maxCurrent = isset($input['max_current']) ? (float) $input['max_current'] : null;
    $arrangement = $recommendation['arrangement'] ?? null;

    $unsafe = false;
    $perString = null;
    $strings = null;

    if (is_array($arrangement)) {
        $perString = filter_var($arrangement['panels_per_string'] ?? null, FILTER_VALIDATE_INT);
        $strings = filter_var($arrangement['strings'] ?? null, FILTER_VALIDATE_INT);
        if ($perString === false || $perString < 1) {
            $perString = null;
        }
        if ($strings === false || $strings < 1) {
            $strings = null;
        }
    }

    if ($perString !== null && $panelVoc !== null && $maxVoltage !== null) {
        // 1.15 covers the usual cold-temperature VOC rise above STC.
        $vocDesign = $perString * $panelVoc * 1.15;
        if ($vocDesign > $maxVoltage) {
            $unsafe = true;
            $warnings[] = sprintf(
                'Calculated string VOC is %.1f V (%d panels × %.1f V, including a 15%% cold-temperature margin), which exceeds the %.1f V controller limit. Reduce panels per string.',
                $vocDesign,
                $perString,
                $panelVoc,
                $maxVoltage
            );
        }
    }

    if ($strings !== null && $panelIsc !== null && $maxCurrent !== null) {
        $currentTotal = $strings * $panelIsc;
        if ($currentTotal > $maxCurrent) {
            $unsafe = true;
            $warnings[] = sprintf(
                'Calculated array current is %.1f A (%d strings × %.1f A), which exceeds the %.1f A controller limit. Reduce parallel strings.',
                $currentTotal,
                $strings,
                $panelIsc,
                $maxCurrent
            );
        }
    }

    if (isset($input['panel_count'], $perString, $strings)
        && $perString !== null && $strings !== null) {
        $expected = (int) $input['panel_count'];
        $calculated = $perString * $strings;
        if ($calculated !== $expected) {
            $warnings[] = sprintf(
                'The arrangement uses %d panels but %d were entered. Confirm the string count before installing.',
                $calculated,
                $expected
            );
        }
    }

    if ($unsafe) {
        $recommendation['safe'] = false;
    }

    $clean = [];
    foreach ($warnings as $warning) {
        if (is_scalar($warning)) {
            $value = trim((string) $warning);
            if ($value !== '') {
                $clean[$value] = true;
            }
        }
    }
    $recommendation['warnings'] = array_keys($clean);

    return $recommendation;
}

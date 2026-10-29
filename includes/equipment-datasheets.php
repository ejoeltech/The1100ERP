<?php
// WP3: refuse direct web execution; this file only works when included.
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Equipment datasheet catalog shared definitions.
 *
 * One place for the category list, the per-category spec columns and the
 * normalisation used by the admin CRUD, CSV import, AI lookup and the public
 * System Designer search endpoint.
 */

if (!function_exists('equipmentDatasheetCategories')) {
    /** category key => [label, icon, spec fields] */
    function equipmentDatasheetCategories()
    {
        return [
            'inverter' => [
                'label' => 'Hybrid inverter',
                'icon' => '&#128267;',
                'fields' => [
                    'capacity_w' => ['Inverter capacity', 'W', 'number'],
                    'controller_capacity_w' => ['Solar input / controller capacity', 'W', 'number'],
                    'nominal_voltage' => ['Nominal solar voltage', 'V', 'number'],
                    'max_voltage' => ['Maximum solar voltage', 'V', 'number'],
                    'voltage_range' => ['MPPT voltage range', '', 'text'],
                    'max_current' => ['Maximum solar current', 'A', 'number'],
                    'battery_voltage' => ['Battery terminal voltage', 'V', 'number'],
                ],
            ],
            'controller' => [
                'label' => 'Charge controller',
                'icon' => '&#9728;',
                'fields' => [
                    'capacity_w' => ['Inverter capacity', 'W', 'number'],
                    'controller_capacity_w' => ['Controller capacity', 'W', 'number'],
                    'battery_voltage' => ['Battery nominal voltage', 'V', 'number'],
                    'max_voltage' => ['Maximum solar voltage', 'V', 'number'],
                ],
            ],
            'battery' => [
                'label' => 'Battery',
                'icon' => '&#128267;',
                'fields' => [
                    'battery_voltage' => ['Nominal voltage', 'V', 'number'],
                    'capacity_ah' => ['Capacity', 'Ah', 'number'],
                    'capacity_kwh' => ['Usable energy', 'kWh', 'number'],
                    'chemistry' => ['Chemistry', '', 'text'],
                    'max_charge_current' => ['Max charge current', 'A', 'number'],
                    'max_discharge_current' => ['Max discharge current', 'A', 'number'],
                    'cycles' => ['Cycle life', 'cycles', 'int'],
                ],
            ],
            'panel' => [
                'label' => 'Solar panel',
                'icon' => '&#127774;',
                'fields' => [
                    'power_w' => ['Rated power', 'W', 'number'],
                    'voc' => ['Open-circuit voltage (VOC)', 'V', 'number'],
                    'isc' => ['Short-circuit current (ISC)', 'A', 'number'],
                    'vmp' => ['Voltage at max power (VMP)', 'V', 'number'],
                    'imp' => ['Current at max power (IMP)', 'A', 'number'],
                    'efficiency' => ['Module efficiency', '%', 'number'],
                ],
            ],
        ];
    }
}

if (!function_exists('equipmentDatasheetCategory')) {
    function equipmentDatasheetCategory($key)
    {
        $categories = equipmentDatasheetCategories();
        return $categories[$key] ?? null;
    }
}

if (!function_exists('equipmentDatasheetLabel')) {
    function equipmentDatasheetLabel($key)
    {
        $category = equipmentDatasheetCategory($key);
        return $category ? $category['label'] : 'Equipment';
    }
}

if (!function_exists('equipmentDatasheetStatuses')) {
    function equipmentDatasheetStatuses()
    {
        return [
            'published' => 'Published (visible in System Designer)',
            'draft' => 'Draft (hidden from System Designer)',
            'archived' => 'Archived (hidden)',
        ];
    }
}

if (!function_exists('equipmentDatasheetColumns')) {
    /** Every spec column stored in equipment_datasheets. */
    function equipmentDatasheetColumns()
    {
        return [
            'capacity_w', 'controller_capacity_w', 'nominal_voltage', 'max_voltage',
            'voltage_range', 'max_current', 'battery_voltage', 'power_w', 'voc', 'isc',
            'vmp', 'imp', 'efficiency', 'capacity_ah', 'capacity_kwh', 'chemistry',
            'max_charge_current', 'max_discharge_current', 'cycles',
        ];
    }
}

if (!function_exists('equipmentDatasheetCleanText')) {
    function equipmentDatasheetCleanText($value, $maxLength)
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/', ' ', $value);
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $maxLength);
        } else {
            $value = substr($value, 0, $maxLength);
        }
        return $value;
    }
}

if (!function_exists('equipmentDatasheetNumber')) {
    /** Accept "5,000", "5 000" or "5000.5"; return float|null. */
    function equipmentDatasheetNumber($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $value = str_replace([',', ' '], '', $value);
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;
        return $number >= 0 ? $number : null;
    }
}

if (!function_exists('equipmentDatasheetNormalize')) {
    /**
     * Normalise arbitrary input (form, CSV row, AI draft) into the DB shape.
     * Values outside the selected category are dropped so a panel record can
     * never carry battery columns by accident.
     *
     * @return array{category:string, brand:string, model:string, title:?string,
     *               status:string, notes:?string, source_url:?string, specs:array}
     */
    function equipmentDatasheetNormalize($input, $defaultStatus = 'published')
    {
        $input = is_array($input) ? $input : [];
        $category = strtolower(trim((string)($input['category'] ?? '')));
        if (!equipmentDatasheetCategory($category)) {
            $category = '';
        }

        $brand = equipmentDatasheetCleanText($input['brand'] ?? '', 120);
        $model = equipmentDatasheetCleanText($input['model'] ?? '', 160);
        $title = equipmentDatasheetCleanText($input['title'] ?? '', 255);

        $status = strtolower(trim((string)($input['status'] ?? $defaultStatus)));
        if (!isset(equipmentDatasheetStatuses()[$status])) {
            $status = $defaultStatus;
        }

        $notes = trim((string)($input['notes'] ?? ''));
        if (function_exists('mb_substr')) {
            $notes = mb_substr($notes, 0, 4000);
        } else {
            $notes = substr($notes, 0, 4000);
        }

        $sourceUrl = trim((string)($input['source_url'] ?? ''));
        if ($sourceUrl !== '' && !preg_match('#^https?://#i', $sourceUrl)) {
            $sourceUrl = '';
        }
        if (function_exists('mb_substr')) {
            $sourceUrl = mb_substr($sourceUrl, 0, 500);
        } else {
            $sourceUrl = substr($sourceUrl, 0, 500);
        }

        $specs = [];
        if ($category !== '') {
            foreach (equipmentDatasheetCategories()[$category]['fields'] as $field => $meta) {
                $type = $meta[2];
                if ($type === 'text') {
                    $value = equipmentDatasheetCleanText($input[$field] ?? '', 60);
                    $specs[$field] = $value === '' ? null : $value;
                } elseif ($type === 'int') {
                    $number = equipmentDatasheetNumber($input[$field] ?? null);
                    $specs[$field] = $number === null ? null : (int)round($number);
                } else {
                    $specs[$field] = equipmentDatasheetNumber($input[$field] ?? null);
                }
            }
        }

        return [
            'category' => $category,
            'brand' => $brand,
            'model' => $model,
            'title' => $title === '' ? null : $title,
            'status' => $status,
            'notes' => $notes === '' ? null : $notes,
            'source_url' => $sourceUrl === '' ? null : $sourceUrl,
            'specs' => $specs,
        ];
    }
}

if (!function_exists('equipmentDatasheetRequiredError')) {
    /**
     * Minimum specs needed for the System Designer to auto-fill useful values.
     *
     * @return array{message:string, field:string}|null
     */
    function equipmentDatasheetRequiredError($data)
    {
        $required = [
            'inverter' => ['capacity_w' => 'inverter capacity'],
            'controller' => ['controller_capacity_w' => 'controller capacity', 'max_voltage' => 'maximum solar voltage'],
            'battery' => ['battery_voltage' => 'nominal battery voltage'],
            'panel' => ['power_w' => 'rated power', 'voc' => 'open-circuit voltage', 'isc' => 'short-circuit current'],
        ];
        $category = $data['category'] ?? '';
        if (!isset($required[$category])) {
            return ['message' => 'Choose a valid equipment category.', 'field' => 'category'];
        }

        foreach ($required[$category] as $field => $label) {
            if (($data['specs'][$field] ?? null) === null) {
                return [
                    'message' => 'The ' . $label . ' is required for a ' . strtolower(equipmentDatasheetLabel($category)) . '.',
                    'field' => $field,
                ];
            }
        }

        if ($category === 'battery'
            && ($data['specs']['capacity_ah'] ?? null) === null
            && ($data['specs']['capacity_kwh'] ?? null) === null) {
            return ['message' => 'Enter the battery capacity in Ah or kWh.', 'field' => 'capacity_ah'];
        }

        return null;
    }
}

if (!function_exists('equipmentDatasheetUpsert')) {
    /**
     * Insert or update a normalised datasheet.
     *
     * @return array{id:int, created:bool}
     */
    function equipmentDatasheetUpsert($pdo, $data, $id = 0, $userId = null)
    {
        $columns = array_merge(
            ['category', 'brand', 'model', 'title'],
            equipmentDatasheetColumns(),
            ['notes', 'source_url', 'status']
        );

        $specValues = [];
        foreach (equipmentDatasheetColumns() as $column) {
            $specValues[] = $data['specs'][$column] ?? null;
        }
        $values = array_merge(
            [$data['category'], $data['brand'], $data['model'], $data['title']],
            $specValues,
            [$data['notes'], $data['source_url'], $data['status']]
        );

        if ((int)$id > 0) {
            $assignments = implode(', ', array_map(function ($column) {
                return "`$column` = ?";
            }, $columns));
            $stmt = $pdo->prepare(
                "UPDATE equipment_datasheets SET $assignments, updated_by = ? WHERE id = ?"
            );
            $stmt->execute(array_merge($values, [$userId, (int)$id]));
            return ['id' => (int)$id, 'created' => false];
        }

        $placeholders = implode(', ', array_fill(0, count($columns) + 2, '?'));
        $stmt = $pdo->prepare(
            'INSERT INTO equipment_datasheets (' . implode(', ', array_map(function ($column) {
                return "`$column`";
            }, $columns)) . ", created_by, updated_by) VALUES ($placeholders)"
        );
        $stmt->execute(array_merge($values, [$userId, $userId]));
        return ['id' => (int)$pdo->lastInsertId(), 'created' => true];
    }
}

if (!function_exists('equipmentDatasheetFieldGuidance')) {
    /**
     * Plain-language definitions used in the AI lookup prompt so fields are
     * not confused with one another (PV current vs battery current, etc.).
     */
    function equipmentDatasheetFieldGuidance($category)
    {
        $guidance = [
            'inverter' => [
                'capacity_w' => 'AC output rating in W',
                'controller_capacity_w' => 'rated PV input power of the built-in solar controller (not the inverter output)',
                'nominal_voltage' => 'nominal PV/MPPT operating voltage (never the battery voltage)',
                'max_voltage' => 'maximum PV open-circuit voltage accepted by the MPPT',
                'voltage_range' => 'MPPT operating voltage window, e.g. 120-450',
                'max_current' => 'maximum PV input current (not the battery charge current)',
                'battery_voltage' => 'DC battery terminal voltage',
            ],
            'controller' => [
                'capacity_w' => 'AC output rating of the paired inverter in W, if published',
                'controller_capacity_w' => 'rated PV input power of the controller',
                'battery_voltage' => 'nominal DC battery voltage',
                'max_voltage' => 'maximum PV open-circuit voltage accepted by the controller',
            ],
            'battery' => [
                'battery_voltage' => 'nominal DC voltage',
                'capacity_ah' => 'nominal capacity in amp-hours',
                'capacity_kwh' => 'nominal or usable energy in kWh',
                'chemistry' => 'e.g. LiFePO4, NMC, AGM, GEL, flooded lead-acid',
                'max_charge_current' => 'maximum continuous charge current in A',
                'max_discharge_current' => 'maximum continuous discharge current in A',
                'cycles' => 'rated cycle life at the stated depth of discharge',
            ],
            'panel' => [
                'power_w' => 'nameplate maximum power (Pmax) in W',
                'voc' => 'open-circuit voltage (VOC) in V',
                'isc' => 'short-circuit current (ISC) in A',
                'vmp' => 'voltage at maximum power (VMP) in V',
                'imp' => 'current at maximum power (IMP) in A',
                'efficiency' => 'module efficiency in percent',
            ],
        ];
        return $guidance[$category] ?? [];
    }
}

if (!function_exists('equipmentDatasheetDisplayName')) {
    function equipmentDatasheetDisplayName($row)
    {
        $brand = trim((string)($row['brand'] ?? ''));
        $model = trim((string)($row['model'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $name = trim($brand . ' ' . $model);
        if ($name === '') {
            $name = $title;
        } elseif ($title !== '') {
            $name .= ' - ' . $title;
        }
        return $name === '' ? 'Unnamed equipment' : $name;
    }
}

if (!function_exists('equipmentDatasheetPublicPayload')) {
    /** Shape returned to the System Designer. Omits admin-only columns. */
    function equipmentDatasheetPublicPayload($row)
    {
        $category = (string)($row['category'] ?? '');
        $specs = [];
        $fields = equipmentDatasheetCategory($category);
        if ($fields) {
            foreach ($fields['fields'] as $field => $meta) {
                if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                    continue;
                }
                $value = $row[$field];
                $specs[$field] = ($meta[2] === 'text') ? (string)$value : (float)$value;
            }
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'category' => $category,
            'brand' => (string)($row['brand'] ?? ''),
            'model' => (string)($row['model'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'name' => equipmentDatasheetDisplayName($row),
            'specs' => $specs,
            'source_url' => (string)($row['source_url'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('equipmentDatasheetSearch')) {
    /**
     * Bounded catalogue search used by the public endpoint.
     *
     * @return array<int,array> Raw rows.
     */
    function equipmentDatasheetSearch($pdo, $query, $category = '', $limit = 10)
    {
        $limit = max(1, min(25, (int)$limit));
        $where = ["status = 'published'"];
        $params = [];

        $category = strtolower(trim((string)$category));
        if ($category !== '' && equipmentDatasheetCategory($category)) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $query = trim((string)$query);
        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $where[] = "(brand LIKE ? OR model LIKE ? OR title LIKE ? OR CONCAT_WS(' ', brand, model, title) LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT id, category, brand, model, title, ' . implode(', ', equipmentDatasheetColumns())
            . ', source_url, updated_at
            FROM equipment_datasheets
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY brand ASC, model ASC
            LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

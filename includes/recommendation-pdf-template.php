<?php
// Solar System Recommendation PDF Template
// For System Designer AI tool

$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#2563eb';

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Inter", sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid ' . $theme_color . ';
            padding-bottom: 15px;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: ' . $theme_color . ';
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }
        
        .meta-info {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .label {
            font-weight: bold;
            color: #374151;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background: ' . $theme_color . ';
            color: white;
            padding: 8px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        th {
            background: #e5e7eb;
            padding: 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #d1d5db;
        }
        
        td {
            padding: 6px;
            border: 1px solid #d1d5db;
        }
        
        .highlight {
            background: #fef3c7;
            font-weight: bold;
        }
        
        .warning-box {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 10px;
            margin: 10px 0;
        }
        
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            padding: 10px;
            margin: 10px 0;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }
        
        .panel-option {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .panel-option-title {
            font-weight: bold;
            color: ' . $theme_color . ';
            font-size: 13px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        ' . (defined('COMPANY_LOGO') && COMPANY_LOGO && file_exists(__DIR__ . '/../' . COMPANY_LOGO) ?
    '<img src="' . __DIR__ . '/../' . COMPANY_LOGO . '" style="height: 50px; max-width: 180px; display: block; margin: 0 auto 10px;">' : ''
) . '
        <div class="header-title">Solar System Design Recommendation</div>
        <div class="header-subtitle">' . (defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots Technologies') . '</div>
        <div style="font-size: 10px; margin-top: 5px;">' . date('F j, Y g:i A') . '</div>
    </div>

    <!-- System Information -->
    <div class="meta-info">
        <table style="border: none;">
            <tr>
                <td style="border: none; width: 50%;"><span class="label">Design Mode:</span> ' . htmlspecialchars($data['mode_label']) . '</td>
                <td style="border: none; width: 50%;"><span class="label">System Type:</span> ' . htmlspecialchars($data['system_type_label']) . '</td>
            </tr>
            <tr>
                <td style="border: none;"><span class="label">Inverter Capacity:</span> ' . htmlspecialchars($data['inverter_capacity']) . ' W</td>
                <td style="border: none;"><span class="label">Battery Voltage:</span> ' . htmlspecialchars($data['battery_voltage']) . ' V</td>
            </tr>
        </table>
    </div>

    ';

// Panel Recommendations Section (Mode 1 WITHOUT specific panels)
if (!empty($recommendation['panel_recommendations'])) {
    $html .= '
    <div class="section">
        <div class="section-title">📋 Recommended Panel Options</div>
        <p style="margin-bottom: 10px; font-style: italic;">Based on your system specifications, here are compatible panel configurations:</p>';

    foreach ($recommendation['panel_recommendations'] as $idx => $panel_rec) {
        $html .= '
        <div class="panel-option">
            <div class="panel-option-title">Option ' . ($idx + 1) . ': ' . htmlspecialchars($panel_rec['panel_size']) . '</div>
            <table>
                <tr>
                    <td width="25%"><strong>Max Panels:</strong></td>
                    <td class="highlight">' . htmlspecialchars($panel_rec['max_panels_display']) . '</td>
                    <td width="25%"><strong>Total Capacity:</strong></td>
                    <td class="highlight">' . htmlspecialchars($panel_rec['total_capacity']) . '</td>
                </tr>
                <tr>
                    <td><strong>Configuration:</strong></td>
                    <td colspan="3">' . htmlspecialchars($panel_rec['arrangement']) . '</td>
                </tr>
            </table>
        </div>';
    }
    $html .= '</div>';
}

// Single Panel Configuration (Mode 1 WITH specific panels OR Mode 2)
if (!empty($recommendation['max_panels']) && empty($recommendation['panel_recommendations'])) {
    $html .= '
    <div class="section">
        <div class="section-title">☀️ Solar Panel Configuration</div>
        <table>
            <tr>
                <th width="30%">Parameter</th>
                <th width="70%">Value</th>
            </tr>
            <tr>
                <td>Maximum Panels</td>
                <td class="highlight">' . htmlspecialchars($recommendation['max_panels']) . '</td>
            </tr>
            <tr>
                <td>Total System Capacity</td>
                <td class="highlight">' . htmlspecialchars($recommendation['total_capacity']) . '</td>
            </tr>
        </table>
    </div>';
}

// Arrangement Section
if (!empty($recommendation['arrangement'])) {
    $arr = $recommendation['arrangement'];
    $html .= '
    <div class="section">
        <div class="section-title">🔌 Panel Arrangement & Wiring</div>
        <table>
            <tr>
                <th>Configuration</th>
                <th>Strings</th>
                <th>Panels per String</th>
                <th>Total VOC</th>
                <th>Total Current</th>
            </tr>
            <tr>
                <td>' . htmlspecialchars($arr['configuration']) . '</td>
                <td>' . htmlspecialchars($arr['strings']) . '</td>
                <td>' . htmlspecialchars($arr['panels_per_string']) . '</td>
                <td class="highlight">' . htmlspecialchars($arr['total_voc']) . '</td>
                <td class="highlight">' . htmlspecialchars($arr['total_current']) . '</td>
            </tr>
        </table>
        
        <div class="info-box">
            <strong>Explanation:</strong><br>
            ' . nl2br(htmlspecialchars($arr['explanation'])) . '
        </div>
    </div>';
}

// Breaker & Wiring Section
$html .= '
<div class="section">
    <div class="section-title">🔧 Component Specifications</div>
    
    <table>
        <tr>
            <th width="40%">Component</th>
            <th width="60%">Specification</th>
        </tr>';

if (!empty($recommendation['breaker'])) {
    $html .= '
        <tr>
            <td><strong>DC Circuit Breaker</strong></td>
            <td class="highlight">' . htmlspecialchars($recommendation['breaker']['rating']) . '</td>
        </tr>
        <tr>
            <td colspan="2" style="background: #f9fafb; font-size: 10px;">
                <em>' . htmlspecialchars($recommendation['breaker']['explanation']) . '</em>
            </td>
        </tr>';
}

if (!empty($recommendation['wiring'])) {
    $html .= '
        <tr>
            <td><strong>PV to Controller Wire</strong></td>
            <td class="highlight">' . htmlspecialchars($recommendation['wiring']['pv_wire']) . '</td>
        </tr>
        <tr>
            <td><strong>Battery to Inverter Wire</strong></td>
            <td class="highlight">' . htmlspecialchars($recommendation['wiring']['battery_wire']) . '</td>
        </tr>
        <tr>
            <td colspan="2" style="background: #f9fafb; font-size: 10px;">
                <em>' . htmlspecialchars($recommendation['wiring']['explanation']) . '</em>
            </td>
        </tr>';
}

$html .= '
    </table>
</div>';

// Summary Section
if (!empty($recommendation['summary'])) {
    $html .= '
    <div class="section">
        <div class="section-title">📝 Summary & Installation Notes</div>
        <div style="padding: 10px;">
            ' . $recommendation['summary'] . '
        </div>
    </div>';
}

// Warnings
if (!empty($recommendation['warnings'])) {
    $html .= '
    <div class="section">
        <div class="section-title">⚠️ Safety Warnings & Considerations</div>';

    foreach ($recommendation['warnings'] as $warning) {
        $html .= '
        <div class="warning-box">
            ⚠️ ' . htmlspecialchars($warning) . '
        </div>';
    }
    $html .= '</div>';
}

// Footer
$html .= '
    <div class="footer">
        Generated by ' . (defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots Technologies') . ' System Designer Tool<br>
        This recommendation is based on the specifications provided and assumes standard installation conditions.<br>
        Always consult with a certified solar installer before implementation.
    </div>

</body>
</html>
';

echo $html;
?>
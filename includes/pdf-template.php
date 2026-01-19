<?php
// Quote PDF Template
// Matches user's "Aligned Editable Quote Template"
// Translated to DOMPDF-compatible Table Layouts

$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#0076BE'; // Primary Blue
$light_bg = '#C1D8F0'; // Light Blue from user HTML

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Inter", sans-serif;
            font-size: 13px;
            color: #000;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            font-size: 12px;
        }

        /* Helper Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .italic { font-style: italic; }
        .no-border { border: none !important; }

        /* Colors */
        .bg-primary { background-color: ' . $theme_color . '; color: white; }
        .text-primary { color: ' . $theme_color . '; }
        .bg-light { background-color: ' . $light_bg . '; }

        /* Layout Specifics */
        .header-title-large {
            font-size: 36px;
            font-weight: bold;
            color: #1A1A1A;
            margin: 0;
        }
        .header-tech {
            font-size: 11px;
            letter-spacing: 3px;
            font-weight: bold;
            margin-top: -5px;
            text-transform: uppercase;
        }

        .quote-label {
            font-family: serif;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 1px;
            border: none;
        }

        /* Info Boxes */
        .blue-label {
            background-color: ' . $theme_color . ';
            color: white;
            font-weight: bold;
            font-size: 12px;
            padding: 3px 8px;
            display: block;
            width: 80px;
            text-align: center;
        }
        
        .border-bottom {
            border-bottom: 1px solid #000;
        }

        /* Tables */
        .main-table th {
            background-color: ' . $theme_color . ';
            color: white;
            border: 1px solid #000;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            padding: 5px;
        }
        .main-table td {
            padding: 5px;
        }
        .compact-row td {
            padding: 4px;
        }
        
        /* Footer Bank */
        .payment-header {
            background-color: ' . $theme_color . ';
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .prepared-by {
            background-color: ' . $theme_color . ';
            color: white;
            text-align: right;
            font-size: 11px;
            font-style: italic;
            padding: 3px 10px;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="no-border" style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td align="center" class="no-border">
                ' . (defined('COMPANY_LOGO') && COMPANY_LOGO ?
    '<img src="' . __DIR__ . '/../' . COMPANY_LOGO . '" style="height: 80px; margin-bottom: 10px;">' :
    '<div class="header-title-large">' . (defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots') . '</div>
                     <div class="header-tech">TECHNOLOGIES</div>'
) . '
                
                <div style="margin-top: 5px; font-size: 11px;">
                    <strong>Contact Address:</strong> ' . COMPANY_ADDRESS . '<br>
                    <strong>Phone:</strong> ' . COMPANY_PHONE . ' | <strong>Email:</strong> ' . COMPANY_EMAIL . '
                </div>
            </td>
        </tr>
    </table>

    <!-- Quote Title Section -->
    <table class="no-border" style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td align="center" class="no-border">
                <div class="quote-label">QUOTE</div>
                <table style="width: auto; margin: 0 auto;">
                    <tr>
                        <td class="no-border" style="font-weight: bold; font-size: 11px; text-transform: uppercase; color: #555; padding-right: 10px;">Quote Title:</td>
                        <td class="no-border" style="border-bottom: 1px solid #000 !important; font-style: italic; min-width: 300px; text-align: center;">
                            ' . htmlspecialchars($quote['quote_title']) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Info Grid -->
    <table class="no-border" style="width: 100%; margin-bottom: 20px;">
        <tr>
            <!-- Left: Quote For -->
            <td class="no-border" width="50%" style="vertical-align: top; padding-right: 20px;">
                <table width="100%">
                    <tr>
                        <td width="90" style="padding:0; border:none;"><div class="blue-label">Quote For:</div></td>
                    </tr>
                    <tr>
                        <td style="padding:8px; border: 1px solid #000; height: 40px; font-style: italic; vertical-align: top;">
                            ' . htmlspecialchars($quote['customer_name']) . '
                        </td>
                    </tr>
                </table>
            </td>
            
            <!-- Right: Date & No -->
            <td class="no-border" width="50%" style="vertical-align: bottom;">
                <table width="100%" style="border-spacing: 0 5px;">
                    <tr>
                        <td width="90" style="padding:0; border:none;"><div class="blue-label">Date:</div></td>
                        <td style="border:none; border-bottom: 1px solid #000; padding-left: 10px;">
                            ' . date('dS F Y', strtotime($quote['quote_date'])) . '
                        </td>
                    </tr>
                    <tr><td colspan="2" height="5" style="border:none;"></td></tr>
                    <tr>
                        <td width="90" style="padding:0; border:none;"><div class="blue-label">Quote No.:</div></td>
                        <td style="border:none; border-bottom: 1px solid #000; padding-left: 10px;">
                            ' . htmlspecialchars($quote['quote_number'] ?? $quote['document_number']) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Meta Details Table -->
    <table class="main-table" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th width="18%">Salesperson</th>
                <th width="15%">Delivery</th>
                <th width="15%">Validity</th>
                <th width="17%">Ship Date</th>
                <th width="35%">Payment Terms</th>
            </tr>
        </thead>
        <tbody>
            <tr class="compact-row">
                <td class="text-center">' . htmlspecialchars($quote['salesperson']) . '</td>
                <td class="text-center">' . htmlspecialchars($quote['delivery_period'] ?? '') . '</td>
                <td class="text-center">30 Days</td>
                <td class="text-center"> - </td>
                <td class="text-center">' . htmlspecialchars($quote['payment_terms'] ?? DEFAULT_PAYMENT_TERMS) . '</td>
            </tr>
        </tbody>
    </table>

    <!-- Items Table -->
    <table class="main-table" style="margin-bottom: 0;">
        <thead>
            <tr>
                <th width="8%">Item #</th>
                <th width="8%">Qty</th>
                <th width="44%">Product Description</th>
                <th width="20%">Unit Price</th>
                <th width="20%">Line Total</th>
            </tr>
        </thead>
        <tbody>';

$i = 1;
foreach ($line_items as $item) {
    $html .= '
            <tr class="compact-row">
                <td class="text-center">' . $i++ . '</td>
                <td class="text-center">' . number_format($item['quantity'], 0) . '</td>
                <td class="text-left">' . htmlspecialchars($item['description']) . '</td>
                <td class="text-right">' . formatNaira($item['unit_price']) . '</td>
                <td class="text-right">' . formatNaira($item['line_total']) . '</td>
            </tr>';
}

// Fill empty rows to make it look full (8 rows total)
$rows_to_fill = max(0, 8 - count($line_items));
for ($k = 0; $k < $rows_to_fill; $k++) {
    $html .= '
            <tr class="compact-row">
                <td class="text-center">' . $i++ . '</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- Right Aligned Totals -->
    <table class="no-border" width="100%" style="margin-top: 0;">
        <tr>
            <td width="60%" class="no-border"></td>
            <td width="40%" class="no-border" style="padding: 0;">
                <table class="main-table" style="border-top: none;">
                    <tr class="compact-row">
                        <td width="50%" class="text-center font-bold" style="border-top: none;">Subtotal</td>
                        <td width="50%" class="text-right bg-primary font-bold" style="border-top: none;">' . formatNaira($quote['subtotal']) . '</td>
                    </tr>
                    <tr class="compact-row">
                        <td class="text-center font-bold">Discount</td>
                        <td class="text-right"> - </td>
                    </tr>
                    <tr class="compact-row">
                        <td class="text-center font-bold">Tax ' . (isset($quote['vat_rate']) ? '(' . $quote['vat_rate'] . '%)' : '') . '</td>
                        <td class="text-right">' . formatNaira($quote['total_vat']) . '</td>
                    </tr>
                    <tr class="compact-row">
                        <td class="text-center font-bold bg-primary">Deposit Required</td>
                        <td class="text-right bg-primary"> - </td>
                    </tr>
                    <tr><td colspan="2" height="5" class="no-border"></td></tr>
                    <tr class="compact-row">
                        <td class="text-center font-bold bg-primary">Total Quote</td>
                        <td class="text-right bg-primary font-bold">' . formatNaira($quote['grand_total']) . '</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Signature Block -->
    <div style="position: absolute; bottom: 150px; right: 40px; width: 200px; text-align: center;">
        <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 5px;"></div>
        <div style="font-size: 10px; font-weight: bold; text-transform: uppercase;">Authorized Signature</div>
    </div>

    <!-- Footer Section -->
    <div style="position: absolute; bottom: 30px; left: 30px; right: 30px;">
        
        <div class="text-center italic font-bold" style="font-family: serif; font-size: 13px; margin-bottom: 10px;">
            ' . nl2br(htmlspecialchars(getSetting('footer_text', 'We look forward to working with you! Thank you'))) . '
        </div>

        <div style="border: 1px solid #000; width: 100%;">
            <div class="payment-header">
                MAKE ALL PAYMENTS IN FAVOUR OF: ' . strtoupper(COMPANY_NAME) . '
            </div>
            
            <div class="bg-light" style="padding: 10px 0; border-bottom: 1px solid #000;">
                <table class="no-border">
                    <tr>';

$bank_accounts = getBankAccountsForDisplay();
if (empty($bank_accounts)) {
    $html .= '<td align="center" class="no-border">No Bank Details Configured</td>';
} else {
    $width = floor(100 / count($bank_accounts));
    foreach ($bank_accounts as $idx => $acc) {
        // Add vertical divider if not first item
        $border_style = ($idx > 0) ? 'border-left: 1px solid #000;' : '';

        $html .= '<td width="' . $width . '%" align="center" style="vertical-align: top; border: none; ' . $border_style . '">
                        <div class="font-bold text-center" style="font-size: 12px;">' . htmlspecialchars($acc['bank_name']) . '</div>
                        <div class="text-center" style="font-size: 12px;">' . htmlspecialchars($acc['account_number']) . '</div>
                     </td>';
    }
}

$html .= '
                    </tr>
                </table>
            </div>
            
            <div class="prepared-by">
                Quote prepared by: ' . htmlspecialchars($quote['salesperson']) . '
            </div>
        </div>
    </div>

</body>
</html>
';

echo $html;
?>
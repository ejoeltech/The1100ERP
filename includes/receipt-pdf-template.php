<?php
// Receipt PDF Template
$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#2563eb';
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid ' . $theme_color . '; padding-bottom: 15px; }
        .company-name { font-size: 24pt; font-weight: bold; color: ' . $theme_color . '; }
        .company-tagline { font-size: 8pt; letter-spacing: 3px; color: #666; }
        .company-details { font-size: 9pt; margin-top: 10px; color: #666; }
        .document-title { font-size: 32pt; font-weight: bold; text-align: center; margin: 20px 0; color: ' . $theme_color . '; }
        .paid-stamp { text-align: center; background-color: #D1FAE5; color: #065F46; padding: 10px; border-radius: 10px; margin: 10px auto; width: 60%; font-weight: bold; font-size: 12pt; }
        .info-grid { width: 100%; }
        .info-grid td { padding: 5px; font-size: 10pt; }
        .label { font-weight: bold; color: #333; }
        .payment-details { background-color: #F3E8FF; border: 2px solid ' . $theme_color . '; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .payment-details h3 { margin: 0 0 10px 0; font-size: 12pt; }
        .payment-grid { width: 100%; }
        .payment-grid td { padding: 5px; font-size: 10pt; }
        .amount-paid { font-size: 18pt; color: #059669; font-weight: bold; }
        .invoice-summary { border: 1px solid #ddd; padding: 15px; margin: 20px 0; }
        .invoice-summary table { width: 100%; border-collapse: collapse; }
        .invoice-summary td { padding: 8px; border-bottom: 1px solid #ddd; }
        .balance { background-color: #F3F4F6; padding: 10px; font-weight: bold; font-size: 11pt; }
        .footer { margin-top: 40px; border-top: 2px solid ' . $theme_color . '; padding-top: 15px; text-align: center; font-size: 9pt; }
        .confirmed-stamp { background-color: ' . $theme_color . '; color: white; padding: 12px; text-align: center; border-radius: 5px; font-weight: bold; font-size: 12pt; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        ' . (defined('COMPANY_LOGO') && COMPANY_LOGO && file_exists(__DIR__ . '/../' . COMPANY_LOGO) ?
    '<img src="' . __DIR__ . '/../' . COMPANY_LOGO . '" style="height: 60px; max-width: 200px; display: block; margin: 0 auto 10px;">' :
    '<div class="company-name">' . (defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots') . '</div>
             <div class="company-tagline">TECHNOLOGIES</div>'
) . '
        <div class="company-details">
            <strong>Contact Address:</strong> ' . COMPANY_ADDRESS . '<br>
            <strong>Phone:</strong> ' . COMPANY_PHONE . ' | <strong>Email:</strong> ' . COMPANY_EMAIL . ' | ' . COMPANY_WEBSITE . '
        </div>
    </div>
    
    <div class="document-title">RECEIPT</div>
    <div style="text-align: center; font-style: italic; color: #666; margin-bottom: 20px;">
        ' . htmlspecialchars($receipt['quote_title']) . '
    </div>
    
    <div class="paid-stamp">✓ PAYMENT RECEIVED</div>
    
    <div class="receipt-info">
        <table class="info-grid">
            <tr>
                <td class="label" width="30%">Received From:</td>
                <td>' . htmlspecialchars($receipt['customer_name']) . '</td>
                <td class="label" width="25%">Receipt Number:</td>
                <td>' . htmlspecialchars($receipt['document_number']) . '</td>
            </tr>
            <tr>
                <td class="label">Date:</td>
                <td>' . date('d/m/Y', strtotime($receipt['quote_date'])) . '</td>';

if ($parent_invoice) {
    $html .= '
                <td class="label">Invoice:</td>
                <td>' . htmlspecialchars($parent_invoice['document_number']) . '</td>';
}

$html .= '
            </tr>
        </table>
    </div>
    
    <div class="payment-details">
        <h3>Payment Details</h3>
        <table class="payment-grid">
            <tr>
                <td class="label" width="30%">Payment Method:</td>
                <td width="40%">' . htmlspecialchars($receipt['payment_method']) . '</td>
                <td class="label" width="30%">Amount Paid:</td>
            </tr>
            <tr>';

if ($receipt['payment_reference']) {
    $html .= '
                <td class="label">Payment Reference:</td>
                <td>' . htmlspecialchars($receipt['payment_reference']) . '</td>';
} else {
    $html .= '<td></td><td></td>';
}

$html .= '
                <td class="amount-paid">' . formatNaira($receipt['amount_paid']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="invoice-summary">
        <h3 style="margin: 0 0 10px 0;">Invoice Summary</h3>
        <table>
            <tr>
                <td>Original Invoice Amount:</td>
                <td style="text-align: right;"><strong>' . formatNaira($receipt['grand_total']) . '</strong></td>
            </tr>
            <tr>
                <td>Amount Paid (This Receipt):</td>
                <td style="text-align: right; color: #059669;"><strong>' . formatNaira($receipt['amount_paid']) . '</strong></td>
            </tr>';

if ($parent_invoice) {
    $html .= '
            <tr class="balance">
                <td><strong>Remaining Balance:</strong></td>
                <td style="text-align: right;">
                    <strong style="' . ($parent_invoice['balance_due'] > 0 ? 'color: #DC2626;' : 'color: #059669;') . '">
                        ' . formatNaira($parent_invoice['balance_due']) . '
                    </strong>
                </td>
            </tr>';
}

$html .= '
        </table>
    </div>';

if ($receipt['notes']) {
    $html .= '
    <div style="background-color: #F3F4F6; padding: 10px; margin: 20px 0; border-radius: 5px;">
        <p style="margin: 0; font-weight: bold; font-size: 10pt;">Notes:</p>
        <p style="margin: 5px 0 0 0; font-size: 10pt;">' . nl2br(htmlspecialchars($receipt['notes'])) . '</p>
    </div>';
}

$html .= '
    <div class="confirmed-stamp">✓ PAYMENT CONFIRMED</div>
    
    <div class="footer">
        <p style="font-style: italic; font-weight: bold;">Thank you for your payment!</p>
        <p style="margin-top: 10px; font-size: 8pt; color: #666;">
            This is a computer-generated receipt<br>
            Receipt prepared by: ' . htmlspecialchars($receipt['salesperson']) . '
        </p>
    </div>
</body>
</html>';

return $html;
?>
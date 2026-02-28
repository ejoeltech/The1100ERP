<?php
// Invoice PDF Template
// This file is included by export-invoice-pdf.php
// Variables available: $invoice, $line_items

$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#2563eb';
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid ' . THEME_COLOR . ';
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: ' . THEME_COLOR . ';
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 9px;
            letter-spacing: 3px;
            color: #666;
            font-weight: bold;
        }
        .company-info {
            font-size: 9px;
            margin-top: 10px;
            color: #555;
        }
        .document-title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin: 15px 0;
            color: ' . THEME_COLOR . ';
        }
        .document-subtitle {
            text-align: center;
            font-style: italic;
            color: #666;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .info-section {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-left {
            width: 55%;
            float: left;
        }
        .info-right {
            width: 40%;
            float: right;
        }
        .info-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            font-size: 10px;
            color: #333;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 11px;
            color: #000;
        }
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            clear: both;
        }
        .line-items-table th {
            background: ' . THEME_COLOR . ';
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }
        .line-items-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        .line-items-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-section {
            width: 50%;
            float: right;
            margin-top: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px;
            vertical-align: middle;
        }
        .totals-label {
            font-weight: bold;
            color: #555;
            font-size: 11px;
            white-space: nowrap;
            text-align: left;
            width: 50%;
        }
        .totals-value {
            text-align: right;
            font-weight: bold;
            color: #000;
            font-size: 12px;
            white-space: nowrap;
            width: 50%;
        }
        .grand-total-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 8px;
            background: ' . THEME_COLOR . ';
            border-radius: 5px;
        }
        .grand-total-table td {
            color: white;
            padding: 8px;
        }
        .balance-due-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 8px;
            background: #dc2626;
            border-radius: 5px;
        }
        .balance-due-table td {
            color: white;
            padding: 8px;
        }
        .footer {
            clear: both;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 3px solid ' . THEME_COLOR . ';
        }
        .thank-you {
            text-align: center;
            font-style: italic;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 15px;
            color: #555;
        }
        .payment-header {
            background: ' . THEME_COLOR . ';
            color: white;
            text-align: center;
            padding: 6px;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 1px;
        }
        .bank-details {
            background: #e3f2fd;
            padding: 10px;
            display: table;
            width: 100%;
        }
        .bank-item {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 6px;
        }
        .bank-name {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 3px;
        }
        .bank-account {
            font-size: 10px;
            color: #555;
        }
        .prepared-by {
            background: ' . THEME_COLOR . ';
            color: white;
            text-align: right;
            padding: 6px 12px;
            font-size: 9px;
            font-style: italic;
        }
        .clearfix {
            clear: both;
        }
        .amount-paid {
            color: #16a34a;
        }
        .balance-due {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">';

// Logo Logic
$logo_files = glob(__DIR__ . '/../uploads/logo/company_logo_*');
if (!empty($logo_files)) {
    $html .= '<img src="' . __DIR__ . '/../uploads/logo/' . basename(end($logo_files)) . '" style="height: 50px; max-width: 180px;">';
} else {
    $html .= '<div class="logo">' . (defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots') . '</div>
              <div class="subtitle">TECHNOLOGIES</div>';
}

$html .= '
        <div class="company-info">
            <strong>Contact Address:</strong> ' . COMPANY_ADDRESS . '<br>
            <strong>Phone:</strong> ' . COMPANY_PHONE . ' | 
            <strong>Email:</strong> ' . COMPANY_EMAIL . ' | 
            <strong>Website:</strong> ' . COMPANY_WEBSITE . '
        </div>
    </div>

    <!-- Document Title -->
    <div class="document-title">INVOICE</div>
    <div class="document-subtitle">' . htmlspecialchars($invoice['quote_title']) . '</div>

    <!-- Invoice Info -->
    <div class="info-section">
        <div class="info-left">
            <div class="info-label">Bill To:</div>
            <div class="info-box">
                <strong>' . htmlspecialchars($invoice['customer_name']) . '</strong>
            </div>
        </div>
        <div class="info-right">
            <table style="width: 100%; font-size: 10px;">
                <tr>
                    <td class="info-label" style="width: 40%;">Invoice Number:</td>
                    <td class="info-value" style="text-align: right; font-weight: bold; color: #16a34a;">
                        ' . htmlspecialchars($invoice['document_number']) . '
                    </td>
                </tr>
                <tr>
                    <td class="info-label">Date:</td>
                    <td class="info-value" style="text-align: right;">
                        ' . date('d/m/Y', strtotime($invoice['quote_date'])) . '
                    </td>
                </tr>
                <tr>
                    <td class="info-label">Salesperson:</td>
                    <td class="info-value" style="text-align: right;">
                        ' . htmlspecialchars($invoice['salesperson']) . '
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Line Items -->
    <table class="line-items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 8%;" class="text-center">Qty</th>
                <th style="width: 45%;">Description</th>
                <th style="width: 15%;" class="text-right">Unit Price</th>
                <th style="width: 7%;" class="text-center">VAT</th>
                <th style="width: 20%;" class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>';

foreach ($line_items as $item) {
    $html .= '
            <tr>
                <td>' . $item['item_number'] . '</td>
                <td class="text-center">' . number_format($item['quantity'], 2) . '</td>
                <td>' . htmlspecialchars($item['description']) . '</td>
                <td class="text-right">' . formatNaira($item['unit_price']) . '</td>
                <td class="text-center">' . ($item['vat_applicable'] ? '✓' : '—') . '</td>
                <td class="text-right"><strong>' . formatNaira($item['line_total']) . '</strong></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="totals-label">Subtotal:</td>
                <td class="totals-value">' . formatNaira($invoice['subtotal']) . '</td>
            </tr>
            <tr>
                <td class="totals-label">VAT (7.5%):</td>
                <td class="totals-value">' . formatNaira($invoice['total_vat']) . '</td>
            </tr>
        </table>
        
        <table class="grand-total-table">
            <tr>
                <td class="totals-label" style="color: white; font-size: 11px;">Grand Total:</td>
                <td class="totals-value" style="color: white; font-size: 12px;">' . formatNaira($invoice['grand_total']) . '</td>
            </tr>
        </table>

        <table class="totals-table">
             <tr>
                <td class="totals-label amount-paid-label">Amount Paid:</td>
                <td class="totals-value amount-paid-value">' . formatNaira($invoice['amount_paid']) . '</td>
            </tr>
        </table>

        <table class="balance-due-table">
            <tr>
                <td class="totals-label" style="color: white; font-size: 11px;">Balance Due:</td>
                <td class="totals-value" style="color: white; font-size: 12px;">' . formatNaira($invoice['balance_due']) . '</td>
            </tr>
        </table>
    </div>

    <div class="clearfix"></div>

  <!-- Footer -->
    <div class="footer">
        <div class="thank-you">' . nl2br(htmlspecialchars(getSetting('footer_text', 'We appreciate your business! Thank you'))) . '</div>';

$bank_accounts = getBankAccountsForDisplay();
if (!empty($bank_accounts)) {
    $account_name = htmlspecialchars($bank_accounts[0]['account_name'] ?? COMPANY_NAME);
    $html .= '
        <div class="payment-header">MAKE ALL PAYMENTS IN FAVOUR OF: ' . $account_name . '</div>
        <div class="bank-details">';

    $column_width = floor(100 / count($bank_accounts));
    foreach ($bank_accounts as $index => $account) {
        $border_style = ($index < count($bank_accounts) - 1) ? 'border-right: 1px solid ' . THEME_COLOR . ';' : '';
        $html .= '
            <div class="bank-item" style="width: ' . $column_width . '%; ' . $border_style . ' vertical-align: middle;">
                <span class="bank-name" style="font-size: 10px;">' . htmlspecialchars($account['bank_name']) . ':</span>
                <span class="bank-account" style="font-size: 10px; font-weight: bold;">' . htmlspecialchars($account['account_number']) . '</span>
            </div>';
    }

    $html .= '
        </div>';
} else {
    $html .= '
        <div class="payment-header">PAYMENT DETAILS</div>
        <div class="bank-details" style="text-align: center;">
            <div class="bank-item" style="width: 100%;">
                Please contact us for payment details.
            </div>
        </div>';
}

$html .= '
        <div class="prepared-by">
            Invoice prepared by: ' . htmlspecialchars($invoice['salesperson']) . '
        </div>
    </div>
</body>
</html>
';

return $html;

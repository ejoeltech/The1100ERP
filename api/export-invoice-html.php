<?php
include '../includes/session-check.php';

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    die('Invoice ID required');
}

// Fetch invoice
$stmt = $pdo->prepare("
    SELECT *, invoice_number as document_number, invoice_title as quote_title, invoice_date as quote_date 
    FROM invoices 
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

// Fetch line items
$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$invoice_id]);
$line_items = $stmt->fetchAll();

// Generate standalone HTML
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice
        <?php echo htmlspecialchars($invoice['document_number']); ?> -
        <?php echo defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots Technologies'; ?>
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 60px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 3px solid #2563eb;
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .logo-dot {
            border-radius: 50%;
        }

        .logo-dot:nth-child(1) {
            width: 12px;
            height: 12px;
            background: #0ea5e9;
        }

        .logo-dot:nth-child(2) {
            width: 20px;
            height: 20px;
            background: #0284c7;
            border: 2px solid #10b981;
        }

        .logo-dot:nth-child(3) {
            width: 32px;
            height: 32px;
            background: #0369a1;
        }

        .logo-dot:nth-child(4) {
            width: 40px;
            height: 40px;
            border: 4px solid #10b981;
            background: transparent;
        }

        .company-name {
            font-size: 36px;
            font-weight: bold;
            color: #1e40af;
        }

        .company-tagline {
            font-size: 12px;
            letter-spacing: 3px;
            color: #6b7280;
        }

        .company-info {
            font-size: 11px;
            color: #6b7280;
            margin-top: 10px;
        }

        .doc-title {
            font-size: 48px;
            font-weight: bold;
            color: #2563eb;
            text-align: center;
            margin: 30px 0;
        }

        .doc-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
            background: #f9fafb;
            padding: 25px;
            border-radius: 8px;
        }

        .doc-info-item {
            display: flex;
            gap: 10px;
        }

        .doc-info-label {
            font-weight: bold;
            color: #4b5563;
            min-width: 120px;
        }

        .doc-info-value {
            color: #1f2937;
        }

        .project-title {
            font-size: 20px;
            font-weight: bold;
            margin: 30px 0 20px;
            padding: 15px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }

        thead {
            background: #1e40af;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        th:last-child {
            text-align: right;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        tr:nth-child(even) {
            background: #f9fafb;
        }

        .item-qty {
            color: #6b7280;
            font-weight: bold;
        }

        .item-amount {
            text-align: right;
            font-weight: bold;
            color: #1f2937;
        }

        .totals {
            margin-top: 40px;
            float: right;
            min-width: 400px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            background: #f9fafb;
            margin-bottom: 5px;
        }

        .totals-row.grand {
            background: #dbeafe;
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            margin-top: 10px;
            padding: 20px;
        }

        .totals-label {
            color: #4b5563;
        }

        .totals-value {
            font-weight: bold;
            color: #1f2937;
        }

        .totals-row.grand .totals-value {
            color: #2563eb;
        }

        .footer {
            clear: both;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 30px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .print-btn:hover {
            background: #1d4ed8;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

    <div class="container">
        <div class="header">
            <?php if (defined('COMPANY_LOGO') && COMPANY_LOGO && file_exists(__DIR__ . '/../' . COMPANY_LOGO)): ?>
                <img src="../<?php echo htmlspecialchars(COMPANY_LOGO); ?>" alt="Company Logo"
                    style="height: 80px; max-width: 300px; margin-bottom: 15px;">
            <?php else: ?>
                <div class="logo">
                    <div class="logo-dot"></div>
                    <div class="logo-dot"></div>
                    <div class="logo-dot"></div>
                    <div class="logo-dot"></div>
                </div>
                <div class="company-name"><?php echo defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots'; ?></div>
                <div class="company-tagline">TECHNOLOGIES</div>
            <?php endif; ?>
            <div class="company-info">
                <?php echo COMPANY_ADDRESS; ?><br>
                Phone:
                <?php echo COMPANY_PHONE; ?> | Email:
                <?php echo COMPANY_EMAIL; ?> |
                <?php echo COMPANY_WEBSITE; ?>
            </div>
        </div>

        <div class="doc-title">INVOICE</div>

        <div class="doc-info">
            <div class="doc-info-item">
                <span class="doc-info-label">Invoice Number:</span>
                <span class="doc-info-value">
                    <?php echo htmlspecialchars($invoice['document_number']); ?>
                </span>
            </div>
            <div class="doc-info-item">
                <span class="doc-info-label">Date:</span>
                <span class="doc-info-value">
                    <?php echo date('d/m/Y', strtotime($invoice['quote_date'])); ?>
                </span>
            </div>
            <div class="doc-info-item">
                <span class="doc-info-label">Customer:</span>
                <span class="doc-info-value">
                    <?php echo htmlspecialchars($invoice['customer_name']); ?>
                </span>
            </div>
            <div class="doc-info-item">
                <span class="doc-info-label">Salesperson:</span>
                <span class="doc-info-value">
                    <?php echo htmlspecialchars($invoice['salesperson']); ?>
                </span>
            </div>
            <div class="doc-info-item" style="grid-column: 1 / -1;">
                <span class="doc-info-label">Status:</span>
                <span class="doc-info-value">
                    <?php echo ucfirst($invoice['status']); ?>
                </span>
            </div>
        </div>

        <div class="project-title">
            <?php echo htmlspecialchars($invoice['quote_title']); ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th style="width: 100px;">Quantity</th>
                    <th>Description</th>
                    <th style="width: 120px;">Unit Price</th>
                    <th style="width: 80px; text-align: center;">VAT</th>
                    <th style="width: 150px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($line_items as $item): ?>
                    <tr>
                        <td>
                            <?php echo $item['item_number']; ?>
                        </td>
                        <td class="item-qty">
                            <?php echo number_format($item['quantity'], 2); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['description']); ?>
                        </td>
                        <td>
                            <?php echo formatNaira($item['unit_price']); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo $item['vat_applicable'] ? '✓' : '—'; ?>
                        </td>
                        <td class="item-amount">
                            <?php echo formatNaira($item['line_total']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span class="totals-label">Subtotal:</span>
                <span class="totals-value">
                    <?php echo formatNaira($invoice['subtotal']); ?>
                </span>
            </div>
            <div class="totals-row">
                <span class="totals-label">VAT (7.5%):</span>
                <span class="totals-value">
                    <?php echo formatNaira($invoice['total_vat']); ?>
                </span>
            </div>
            <div class="totals-row grand">
                <span class="totals-label">Grand Total:</span>
                <span class="totals-value">
                    <?php echo formatNaira($invoice['grand_total']); ?>
                </span>
            </div>
        </div>

        <div class="footer">
            <p><strong>Payment Terms:</strong>
                <?php echo htmlspecialchars($invoice['payment_terms']); ?>
            </p>
            <p style="margin-top: 15px;">This is a computer-generated invoice from
                <?php echo defined('COMPANY_NAME') ? COMPANY_NAME : 'Bluedots Technologies'; ?>
            </p>
            <p>For any questions, please contact us at
                <?php echo COMPANY_EMAIL; ?>
            </p>
        </div>
    </div>
</body>

</html>
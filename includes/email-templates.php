<?php
// Email HTML templates for documents

function getEmailTemplate($type, $data)
{
    $templates = [
        'quote' => 'getQuoteEmailTemplate',
        'invoice' => 'getInvoiceEmailTemplate',
        'receipt' => 'getReceiptEmailTemplate'
    ];

    if (isset($templates[$type])) {
        return call_user_func($templates[$type], $data);
    }

    return '';
}

function getQuoteEmailTemplate($data)
{
    $document = $data['document'];
    $customer_name = htmlspecialchars($document['customer_name']);
    $document_number = htmlspecialchars($document['document_number']);
    $quote_title = htmlspecialchars($document['quote_title']);
    $grand_total = function_exists('formatNaira') ? formatNaira($document['grand_total']) : '₦' . number_format($document['grand_total'], 2);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0076BE; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0076BE; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Company Name</h1>
            <p>Quote for Your Review</p>
        </div>
        
        <div class="content">
            <p>Dear <strong>$customer_name</strong>,</p>
            
            <p>Thank you for your interest in our services. Please find attached your quote for:</p>
            
            <div class="info-box">
                <h3 style="margin-top: 0;">$quote_title</h3>
                <p><strong>Quote Number:</strong> $document_number</p>
                <p><strong>Total Amount:</strong> $grand_total</p>
            </div>
            
            <p>The attached PDF contains the complete quote with itemized details, pricing, and payment terms.</p>
            
            <p>If you have any questions or would like to proceed, please don't hesitate to contact us.</p>
            
            <p>We look forward to working with you!</p>
        </div>
        
        <div class="footer">
            <p><strong>1100-ERP</strong></p>
            <p>Your Company Address, City, State/Province, Country</p>
            <p>Phone: +1234567890 | Email: contact@yourcompany.com</p>
            <p>www.yourcompany.com</p>
            <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
            <p style="font-size: 11px;">This is an automated email from 1100-ERP System</p>
        </div>
    </div>
</body>
</html>
HTML;
}

function getInvoiceEmailTemplate($data)
{
    $document = $data['document'];
    $customer_name = htmlspecialchars($document['customer_name']);
    $document_number = htmlspecialchars($document['document_number']);
    $quote_title = htmlspecialchars($document['quote_title']);
    $grand_total = function_exists('formatNaira') ? formatNaira($document['grand_total']) : '₦' . number_format($document['grand_total'], 2);
    $balance_due = function_exists('formatNaira') ? formatNaira($document['balance_due']) : '₦' . number_format($document['balance_due'], 2);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #34A853; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #34A853; }
        .payment-box { background: #FFF3CD; padding: 15px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Company Name</h1>
            <p>Invoice for Payment</p>
        </div>
        
        <div class="content">
            <p>Dear <strong>$customer_name</strong>,</p>
            
            <p>Please find attached your invoice for:</p>
            
            <div class="info-box">
                <h3 style="margin-top: 0;">$quote_title</h3>
                <p><strong>Invoice Number:</strong> $document_number</p>
                <p><strong>Total Amount:</strong> $grand_total</p>
                <p><strong>Amount Due:</strong> <span style="color: #DC2626; font-weight: bold;">$balance_due</span></p>
            </div>
            
            <div class="payment-box">
                <h4 style="margin-top: 0;">Payment Information:</h4>
                <p><strong>Bank 1:</strong> [Account Number]</p>
                <p><strong>Bank 2:</strong> [Account Number]</p>
                <p><strong>Account Name:</strong> [Your Company Name]</p>
            </div>
            
            <p>Please process payment at your earliest convenience. Once payment is received, we will send you an official receipt.</p>
            
            <p>Thank you for your business!</p>
        </div>
        
        <div class="footer">
            <p><strong>Your Company Name</strong></p>
            <p>Your Company Address, City, State/Province, Country</p>
            <p>Phone: +1234567890 | Email: contact@yourcompany.com</p>
            <p>www.yourcompany.com</p>
            <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
            <p style="font-size: 11px;">This is an automated email from 1100-ERP System</p>
        </div>
    </div>
</body>
</html>
HTML;
}

function getReceiptEmailTemplate($data)
{
    $document = $data['document'];
    $customer_name = htmlspecialchars($document['customer_name']);
    $document_number = htmlspecialchars($document['document_number']);
    $quote_title = htmlspecialchars($document['quote_title']);
    $amount_paid = function_exists('formatNaira') ? formatNaira($document['amount_paid']) : '₦' . number_format($document['amount_paid'], 2);
    $payment_method = htmlspecialchars($document['payment_method'] ?? 'Cash');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #9333EA; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #9333EA; }
        .success-box { background: #D1FAE5; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Company Name</h1>
            <p>Payment Receipt</p>
        </div>
        
        <div class="content">
            <p>Dear <strong>$customer_name</strong>,</p>
            
            <div class="success-box">
                <h3 style="color: #059669; margin-top: 0;">✓ Payment Received</h3>
                <p style="font-size: 18px; margin: 0;">Thank you for your payment!</p>
            </div>
            
            <p>This email confirms that we have received your payment:</p>
            
            <div class="info-box">
                <h3 style="margin-top: 0;">$quote_title</h3>
                <p><strong>Receipt Number:</strong> $document_number</p>
                <p><strong>Amount Paid:</strong> <span style="color: #059669; font-weight: bold;">$amount_paid</span></p>
                <p><strong>Payment Method:</strong> $payment_method</p>
           </div>
            
            <p>Your official receipt is attached to this email for your records.</p>
            
            <p>We appreciate your prompt payment and look forward to serving you again!</p>
        </div>
        
        <div class="footer">
            <p><strong>Your Company Name</strong></p>
            <p>Your Company Address, City, State/Province, Country</p>
            <p>Phone: +1234567890 | Email: contact@yourcompany.com</p>
            <p>www.yourcompany.com</p>
            <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
            <p style="font-size: 11px;">This is an automated email from 1100-ERP System</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>
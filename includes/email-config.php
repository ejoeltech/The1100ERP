<?php
// Email configuration for PHPMailer or native PHP mail()

// Email settings
define('EMAIL_FROM_ADDRESS', 'noreply@yourcompany.com');
define('EMAIL_FROM_NAME', 'Your Company Name');
define('EMAIL_BCC_SALESPERSON', true); // BCC salesperson on emails

// SMTP Configuration (Optional - for production use)
// If using Gmail or other SMTP, configure these:
define('USE_SMTP', false); // Set to true to use SMTP instead of mail()
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

/**
 * Send email using PHP mail() function
 * For production, consider using PHPMailer with SMTP
 */
function sendEmail($to, $subject, $html_body, $attachments = [], $bcc = [])
{
    // For now, using basic PHP mail()
    // In production, use PHPMailer or similar library

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $headers[] = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>';

    if (!empty($bcc)) {
        $headers[] = 'Bcc: ' . implode(',', $bcc);
    }

    // Add attachments if any (basic implementation)
    // For production, use PHPMailer for proper attachment handling

    $result = mail($to, $subject, $html_body, implode("\r\n", $headers));

    return $result;
}

/**
 * Advanced email sending with PHPMailer (for production)
 * Uncomment and use this when deploying to production
 */
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailAdvanced($to, $subject, $html_body, $pdf_path = null, $bcc = []) {
    require 'vendor/autoload.php';

    $mail = new PHPMailer(true);

    try {
        if (USE_SMTP) {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
        }

        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($to);

        foreach ($bcc as $bcc_email) {
            $mail->addBCC($bcc_email);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;

        if ($pdf_path && file_exists($pdf_path)) {
            $mail->addAttachment($pdf_path);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}
*/
?>
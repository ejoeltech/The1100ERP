<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Minimal SMTP mailer (no dependencies).
 * Supports plain, STARTTLS (tls) and implicit SSL (ssl) with AUTH LOGIN.
 */

/**
 * Send an email via SMTP.
 *
 * @param string|array $to      Recipient email(s)
 * @param string       $subject
 * @param string       $textBody Plain-text body
 * @param array        $attachments Each: ['path' => ..., 'name' => ...] or ['content' => ..., 'name' => ..., 'type' => ...]
 * @param array        $options from, fromName, replyTo, cc, bcc, host, port, encryption, username, password, timeout
 * @return array ['success' => bool, 'message' => string]
 */
function smtpSendEmail($to, $subject, $textBody, $attachments = [], $options = [])
{
    $to = (array)$to;
    $cc = isset($options['cc']) ? (array)$options['cc'] : [];
    $bcc = isset($options['bcc']) ? (array)$options['bcc'] : [];
    $from = $options['from'] ?? '';
    $fromName = $options['fromName'] ?? '';
    $replyTo = $options['replyTo'] ?? $from;
    $host = $options['host'] ?? '';
    $port = (int)($options['port'] ?? 587);
    $encryption = strtolower($options['encryption'] ?? 'tls');
    $username = $options['username'] ?? '';
    $password = $options['password'] ?? '';
    $timeout = (int)($options['timeout'] ?? 20);

    foreach (array_merge($to, $cc, $bcc) as $addr) {
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address: ' . $addr];
        }
    }
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid sender address'];
    }
    if ($host === '') {
        return ['success' => false, 'message' => 'SMTP host is not configured'];
    }

    // Build MIME message
    $boundary = 'erp_' . md5(uniqid((string)mt_rand(), true));
    $headers = [];
    $headers[] = 'From: ' . smtpFormatAddress($from, $fromName);
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'To: ' . implode(', ', $to);
    if ($cc) {
        $headers[] = 'Cc: ' . implode(', ', $cc);
    }
    $headers[] = 'Subject: ' . smtpEncodeHeader($subject);
    $headers[] = 'MIME-Version: 1.0';

    if ($attachments) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n";
        foreach ($attachments as $att) {
            if (isset($att['path'])) {
                if (!is_readable($att['path'])) {
                    return ['success' => false, 'message' => 'Attachment not readable: ' . ($att['name'] ?? $att['path'])];
                }
                $content = file_get_contents($att['path']);
                $name = $att['name'] ?? basename($att['path']);
                $type = $att['type'] ?? 'application/octet-stream';
            } else {
                $content = $att['content'] ?? '';
                $name = $att['name'] ?? 'attachment.bin';
                $type = $att['type'] ?? 'application/octet-stream';
            }
            $body .= "--$boundary\r\n";
            $body .= 'Content-Type: ' . $type . '; name="' . addcslashes($name, '"\\') . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . addcslashes($name, '"\\') . '"' . "\r\n\r\n";
            $body .= chunk_split(base64_encode($content));
        }
        $body .= "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $textBody . "\r\n";
    }

    // Connect
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['success' => false, 'message' => 'SMTP connect failed: ' . $errstr . ' (' . $errno . ')'];
    }
    stream_set_timeout($fp, $timeout);

    $read = function ($expect) use ($fp) {
        $resp = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $resp .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        $code = (int)substr($resp, 0, 3);
        $ok = in_array($code, (array)$expect, true);
        return [$ok, trim($resp)];
    };
    $cmd = function ($command, $expect) use ($fp, $read) {
        fwrite($fp, $command . "\r\n");
        return $read($expect);
    };

    [$ok, $greet] = $read([220]);
    if (!$ok) {
        fclose($fp);
        return ['success' => false, 'message' => 'SMTP greeting failed: ' . $greet];
    }
    $hostname = gethostname() ?: 'localhost';
    [$ok, $resp] = $cmd('EHLO ' . $hostname, [250]);
    if (!$ok) {
        fclose($fp);
        return ['success' => false, 'message' => 'SMTP EHLO failed: ' . $resp];
    }

    if ($encryption === 'tls') {
        [$ok, $resp] = $cmd('STARTTLS', [220]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP STARTTLS failed: ' . $resp];
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP TLS negotiation failed'];
        }
        [$ok, $resp] = $cmd('EHLO ' . $hostname, [250]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP EHLO after TLS failed: ' . $resp];
        }
    }

    if ($username !== '') {
        [$ok, $resp] = $cmd('AUTH LOGIN', [334]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP AUTH not accepted: ' . $resp];
        }
        [$ok, $resp] = $cmd(base64_encode($username), [334]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP username rejected: ' . $resp];
        }
        [$ok, $resp] = $cmd(base64_encode($password), [235]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP authentication failed (check username/app password): ' . $resp];
        }
    }

    [$ok, $resp] = $cmd('MAIL FROM:<' . $from . '>', [250]);
    if (!$ok) {
        fclose($fp);
        return ['success' => false, 'message' => 'SMTP MAIL FROM rejected: ' . $resp];
    }
    foreach (array_merge($to, $cc, $bcc) as $rcpt) {
        [$ok, $resp] = $cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
        if (!$ok) {
            fclose($fp);
            return ['success' => false, 'message' => 'SMTP RCPT rejected (' . $rcpt . '): ' . $resp];
        }
    }
    [$ok, $resp] = $cmd('DATA', [354]);
    if (!$ok) {
        fclose($fp);
        return ['success' => false, 'message' => 'SMTP DATA rejected: ' . $resp];
    }
    // Dot-stuffing per RFC 5321
    $data = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', $body) . "\r\n.";
    fwrite($fp, $data . "\r\n");
    [$ok, $resp] = $read([250]);
    $cmd('QUIT', [221]);
    fclose($fp);

    if (!$ok) {
        return ['success' => false, 'message' => 'SMTP send failed: ' . $resp];
    }
    return ['success' => true, 'message' => 'Email sent via SMTP'];
}

function smtpFormatAddress($email, $name = '')
{
    $name = trim($name ?? '');
    if ($name === '') {
        return $email;
    }
    return smtpEncodeHeader($name) . ' <' . $email . '>';
}

function smtpEncodeHeader($text)
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

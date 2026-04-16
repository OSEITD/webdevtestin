<?php

class EmailHelper {
    private $fromAddress;
    private $fromName;

    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;

    public function __construct() {
        if (!class_exists('EnvLoader')) {
            require_once __DIR__ . '/env.php';
        }
        EnvLoader::load();

        // Load URL helper for customer routing links
        if (!function_exists('getCustomerUrl')) {
            require_once __DIR__ . '/url_helper.php';
        }

        // If Composer autoloader is available, load it to use PHPMailer (recommended)
        $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        }

        // Company email for sending notifications
        $this->fromAddress = getenv('EMAIL_FROM_ADDRESS') ?: 'Info@webdevzm.tech';
        $this->fromName = getenv('EMAIL_FROM_NAME') ?: 'WDParcel';

        // SMTP configuration (optional; when set this is used instead of PHP mail())
        $this->smtpHost = getenv('EMAIL_SMTP_HOST') ?: null;
        $this->smtpPort = getenv('EMAIL_SMTP_PORT') ?: 587;
        $this->smtpUser = getenv('EMAIL_SMTP_USER') ?: null;
        $this->smtpPass = getenv('EMAIL_SMTP_PASS') ?: null;
        $this->smtpSecure = strtolower(trim(getenv('EMAIL_SMTP_SECURE') ?: '')) ?: null; // tls, ssl, or blank

        error_log(sprintf(
            '[EmailHelper] config: smtp_host=%s smtp_port=%s smtp_user=%s smtp_pass_set=%s smtp_secure=%s',
            $this->smtpHost ?: '<none>',
            $this->smtpPort,
            $this->smtpUser ?: '<none>',
            (!empty($this->smtpPass) ? 'yes' : 'no'),
            $this->smtpSecure ?: '<none>'
        ));
    }

   
    public function sendEmail(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null): array {
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendEmailWithPHPMailer($toEmail, $toName, $subject, $body, $replyTo);
        }

        if (!empty($this->smtpHost)) {
            return $this->sendEmailSmtp($toEmail, $toName, $subject, $body, $replyTo);
        }

        return $this->sendEmailMail($toEmail, $toName, $subject, $body, $replyTo);
    }

    private function sendEmailWithPHPMailer(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null): array {
        $result = [
            'method' => 'phpmailer',
            'success' => false,
            'to' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'error' => null,
        ];

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toEmail, $toName ?: $toEmail);
            if (!empty($replyTo)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'quoted-printable';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            if (!empty($this->smtpHost)) {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->Port = (int)$this->smtpPort;
                if (in_array($this->smtpSecure, ['ssl', 'tls'], true)) {
                    $mail->SMTPSecure = $this->smtpSecure;
                }
                if (!empty($this->smtpUser) && !empty($this->smtpPass)) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $this->smtpUser;
                    $mail->Password = $this->smtpPass;
                }
               
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            } else {
                
                $mail->isMail();
            }

            $mail->send();
            $result['success'] = true;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            error_log('[EmailHelper] PHPMailer failed: ' . $e->getMessage());
        }

        return $result;
    }

    private function sendEmailMail(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null): array {
        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromAddress . '>';
        if (!empty($replyTo)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $additionalParams = "-f" . escapeshellarg($this->fromAddress);
        $success = false;
        try {
            $success = @mail($toEmail, $subject, $body, implode("\r\n", $headers), $additionalParams);
        } catch (\Throwable $e) {
            error_log('[EmailHelper] mail() exception: ' . $e->getMessage());
        }

        $result = [
            'method' => 'mail',
            'success' => (bool)$success,
            'to' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'headers' => implode("\r\n", $headers),
            'error' => $success ? null : 'mail() returned false'
        ];

        if (!$success) {
            error_log('[EmailHelper] mail() failed. To: ' . $toEmail . ' Subject: ' . $subject);
        }

        return $result;
    }

    private function sendEmailSmtp(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null): array {
        $result = [
            'method' => 'smtp',
            'success' => false,
            'to' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'error' => null,
            'smtp_log' => []
        ];

        $host = $this->smtpHost;
        $port = (int)$this->smtpPort;
        $secure = $this->smtpSecure;

        $transport = ($secure === 'ssl') ? 'ssl://' : '';
        $fp = @stream_socket_client(
            "$transport{$host}:{$port}",
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );

        if (!$fp) {
            $result['error'] = "SMTP connection failed: $errno $errstr";
            error_log('[EmailHelper] ' . $result['error']);
            return $result;
        }

        stream_set_timeout($fp, 20);

        $log = [];
        $resp = $this->smtpCommand($fp, null, ['220']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'SMTP banner error: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        $hostName = gethostname() ?: 'localhost';
        $resp = $this->smtpCommand($fp, "EHLO {$hostName}\r\n", ['250']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'EHLO failed: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        if ($secure === 'tls') {
            $resp = $this->smtpCommand($fp, "STARTTLS\r\n", ['220']);
            $log[] = $resp;
            if (!$resp['ok']) {
                $result['error'] = 'STARTTLS failed: ' . $resp['response'];
                $result['smtp_log'] = $log;
                fclose($fp);
                return $result;
            }

            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $resp = $this->smtpCommand($fp, "EHLO {$hostName}\r\n", ['250']);
            $log[] = $resp;
            if (!$resp['ok']) {
                $result['error'] = 'EHLO after STARTTLS failed: ' . $resp['response'];
                $result['smtp_log'] = $log;
                fclose($fp);
                return $result;
            }
        }

        if (!empty($this->smtpUser) && !empty($this->smtpPass)) {
            $resp = $this->smtpCommand($fp, "AUTH LOGIN\r\n", ['334']);
            $log[] = $resp;
            if (!$resp['ok']) {
                $result['error'] = 'AUTH LOGIN failed: ' . $resp['response'];
                $result['smtp_log'] = $log;
                fclose($fp);
                return $result;
            }

            $resp = $this->smtpCommand($fp, base64_encode($this->smtpUser) . "\r\n", ['334']);
            $log[] = $resp;
            if (!$resp['ok']) {
                $result['error'] = 'SMTP username rejected: ' . $resp['response'];
                $result['smtp_log'] = $log;
                fclose($fp);
                return $result;
            }

            $resp = $this->smtpCommand($fp, base64_encode($this->smtpPass) . "\r\n", ['235']);
            $log[] = $resp;
            if (!$resp['ok']) {
                $result['error'] = 'SMTP password rejected: ' . $resp['response'];
                $result['smtp_log'] = $log;
                fclose($fp);
                return $result;
            }
        }

        $resp = $this->smtpCommand($fp, "MAIL FROM:<{$this->fromAddress}>\r\n", ['250']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'MAIL FROM failed: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        $resp = $this->smtpCommand($fp, "RCPT TO:<{$toEmail}>\r\n", ['250', '251']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'RCPT TO failed: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        $resp = $this->smtpCommand($fp, "DATA\r\n", ['354']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'DATA command failed: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromAddress . '>';
        $headers[] = 'To: ' . $toName . ' <' . $toEmail . '>';
        if (!empty($replyTo)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'Date: ' . date('r');

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        $resp = $this->smtpCommand($fp, $data, ['250']);
        $log[] = $resp;
        if (!$resp['ok']) {
            $result['error'] = 'Message body rejected: ' . $resp['response'];
            $result['smtp_log'] = $log;
            fclose($fp);
            return $result;
        }

        $resp = $this->smtpCommand($fp, "QUIT\r\n", ['221']);
        $log[] = $resp;

        fclose($fp);
        $result['success'] = true;
        $result['smtp_log'] = $log;
        return $result;
    }

    private function smtpCommand($fp, $command, array $acceptedPrefixes = ['250']): array {
        if ($command !== null) {
            fwrite($fp, $command);
        }

        $response = $this->smtpRead($fp);
        $code = substr($response, 0, 3);
        $ok = in_array($code, $acceptedPrefixes, true);

        return [
            'command' => $command,
            'response' => trim($response),
            'code' => $code,
            'ok' => $ok
        ];
    }

    private function smtpRead($fp) {
        $response = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // SMTP responses end with a line where the 4th char is a space
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        error_log('[EmailHelper] SMTP response: ' . trim($response));
        return $response;
    }

    /**
     * Send parcel creation emails to sender and receiver (if emails provided).
     */
    public function notifyParcelCreated(array $parcelData): array {
        $results = [];

        $trackingNumber = $parcelData['track_number'] ?? '';
        $senderName = $parcelData['sender_name'] ?? '';
        $receiverName = $parcelData['receiver_name'] ?? '';

        // Track/Support link (best effort based on current host or configured base URL)
        if (function_exists('getCustomerUrl')) {
            $trackUrl = getCustomerUrl('track_parcel.php?track=' . urlencode($trackingNumber));
        } else {
            $trackUrl = '';
            if (class_exists('EnvLoader')) {
                $baseUrl = EnvLoader::get('BASE_URL');
                if ($baseUrl) {
                    $trackUrl = rtrim($baseUrl, '/') . '/customer-app/track_parcel.php?track=' . urlencode($trackingNumber);
                }
            }

            if (empty($trackUrl)) {
                $host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
                $trackUrl = "{$protocol}://{$host}/customer-app/track_parcel.php?track=" . urlencode($trackingNumber);
            }
        }

        $senderEmail = $parcelData['sender_email'] ?? '';
        $receiverEmail = $parcelData['receiver_email'] ?? '';

        $subject = "Parcel Registered — Tracking #{$trackingNumber}";

        if (!empty($senderEmail) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $body = "Hello {$senderName},\n\n" .
                "Your parcel has been registered successfully.\n" .
                "Tracking Number: {$trackingNumber}\n" .
                "Recipient: {$receiverName}\n\n" .
                "You can track the parcel at: {$trackUrl}\n\n" .
                "If you need help, contact us at {$this->fromAddress}.\n\n" .
                "Thank you for choosing our service.\n";

            $results['sender'] = $this->sendEmail($senderEmail, $senderName, $subject, $body, $this->fromAddress);
        }

        if (!empty($receiverEmail) && filter_var($receiverEmail, FILTER_VALIDATE_EMAIL)) {
            $body = "Hello {$receiverName},\n\n" .
                "A parcel has been registered for you.\n" .
                "Tracking Number: {$trackingNumber}\n" .
                "Sender: {$senderName}\n\n" .
                "You can track the parcel at: {$trackUrl}\n\n" .
                "If you have any questions, contact us at {$this->fromAddress}.\n\n" .
                "Thank you.\n";

            $results['receiver'] = $this->sendEmail($receiverEmail, $receiverName, $subject, $body, $this->fromAddress);
        }

        return $results;
    }
}

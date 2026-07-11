<?php
/**
 * Mail helper: uses PHPMailer if available (via Composer), otherwise falls back to PHP mail().
 */
function send_email_smtp($to, $subject, $htmlBody, $altBody = '') {
    // Use config constants if present, otherwise environment variables.
    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : getenv('SMTP_HOST');
    $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : getenv('SMTP_PORT');
    $smtpUser = defined('SMTP_USER') ? SMTP_USER : getenv('SMTP_USER');
    $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : getenv('SMTP_PASS');
    $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : getenv('SMTP_SECURE');
    $from = defined('MAIL_FROM') ? MAIL_FROM : getenv('MAIL_FROM');
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : getenv('MAIL_FROM_NAME');

    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            if (!empty($smtpHost) && !empty($smtpUser)) {
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
                $mail->SMTPSecure = $smtpSecure ?: 'tls';
                $mail->Port = (int)($smtpPort ?: 587);
            }

            $from = $from ?: 'noreply@bazario.local';
            $fromName = $fromName ?: 'Bazario';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if (!empty($altBody)) $mail->AltBody = $altBody;

            return $mail->send();
        } catch (Exception $e) {
            error_log('PHPMailer send failed: ' . $e->getMessage());
            // fallthrough to mail()
        }
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $from = $from ?: 'noreply@bazario.local';
    $fromName = $fromName ?: 'Bazario';
    $headers .= "From: {$fromName} <{$from}>\r\n";

    if (empty($smtpHost) || empty($smtpUser)) {
        $logDir = __DIR__ . '/../uploads';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/otp_email_log.log';
        $entry = '[' . date('c') . '] TO=' . $to . "\n";
        $entry .= 'SUBJECT=' . $subject . "\n";
        $entry .= 'BODY=' . strip_tags($htmlBody) . "\n---\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        return true;
    }

    $mailResult = @mail($to, $subject, $htmlBody, $headers);
    if ($mailResult) {
        return true;
    }

    $logDir = __DIR__ . '/../uploads';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/otp_email_log.log';
    $entry = '[' . date('c') . '] TO=' . $to . "\n";
    $entry .= 'SUBJECT=' . $subject . "\n";
    $entry .= 'BODY=' . strip_tags($htmlBody) . "\n---\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    return true;
}

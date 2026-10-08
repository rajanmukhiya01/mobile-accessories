<?php
/**
 * Send application email through authenticated SMTP using Composer PHPMailer.
 */
function send_email_smtp($to, $subject, $htmlBody, $altBody = ''): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Email delivery failed: Composer autoloader is missing.');
        return false;
    }

    require_once $autoload;

    $smtpHost = SMTP_HOST;
    $smtpPort = (int) SMTP_PORT;
    $smtpUser = SMTP_USERNAME;
    $smtpPass = SMTP_PASSWORD;
    $smtpSecure = SMTP_SECURE;
    $from = MAIL_FROM ?: $smtpUser;
    $fromName = MAIL_FROM_NAME ?: 'Bazario';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Email delivery failed: invalid recipient address.');
        return false;
    }

    if ($smtpHost === '' || $smtpPort <= 0 || $smtpUser === '' || $smtpPass === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('Email delivery failed: SMTP settings are incomplete.');
        return false;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('Email delivery failed: PHPMailer class was not autoloaded.');
        return false;
    }

    $mail = null;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;
        $mail->Timeout = 20;
        $mail->SMTPDebug = 0;
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

        return $mail->send() === true;
    } catch (\Throwable $exception) {
        $errorInfo = $mail instanceof \PHPMailer\PHPMailer\PHPMailer ? $mail->ErrorInfo : '';
        $diagnostic = 'ErrorInfo=' . $errorInfo . '; SMTP response=' . $exception->getMessage();
        foreach ([$smtpPass, $smtpUser] as $secret) {
            if ($secret !== '') {
                $diagnostic = str_replace($secret, '[redacted]', $diagnostic);
            }
        }
        error_log('PHPMailer SMTP delivery failed: ' . $diagnostic);
        return false;
    }
}

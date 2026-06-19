<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

function send_mail(string $to, string $subject, string $body): void
{
    $mail = new PHPMailer(true);

    if (!empty($_ENV['SMTP_HOST'])) {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->isMail();
    }

    $mail->setFrom(MAIL_FROM, APP_NAME);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->send();
}

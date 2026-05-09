<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendEmail($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {

        // ================= SMTP SETTINGS =================

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        // Your Gmail
        $mail->Username = 'your_mail_d';

        // Your Gmail App Password
        $mail->Password = 'PASTE_NEW_APP_PASSWORD_HERE';

        // Encryption
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Port
        $mail->Port = 587;

        // Debugging
        $mail->SMTPDebug = 2;

        $mail->Debugoutput = 'html';

        // ================= EMAIL SETTINGS =================

        // Sender
        $mail->setFrom('you_mail_id', 'Rotary Club');

        // Receiver
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = $body;

        // Send email
        $mail->send();

        return true;

    } catch (Exception $e) {

        echo "Mailer Error: " . $mail->ErrorInfo;

        return false;
    }
}
?>
<?php

namespace App\Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class CorreoController extends BaseController
{
    public static function sendEmail($to, $subject, $body, $adjunto = null)
    {
        $mail = new PHPMailer(true);
        
        try {
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host       = env('email.SMTPHost');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('email.SMTPUser');
            $mail->Password   = env('email.SMTPPass');
            $mail->SMTPSecure = env('email.smtpSecure') ?: 'tls';
            $mail->Port       = (int) env('email.SMTPPort', 587);

            // Configuración de remitente y destinatario
            $mail->setFrom(env('email.fromEmail') ?: env('email.SMTPUser'), 'Soporte');
            $mail->addAddress($to);

            $mail->CharSet = 'UTF-8';

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            if ($adjunto && file_exists($adjunto)) {
                $mail->addAttachment($adjunto);
            }

            // Enviar correo
            return $mail->send();

        } catch (Exception $e) {
            return "Error al enviar el correo: {$mail->ErrorInfo}";
        }
    }
}

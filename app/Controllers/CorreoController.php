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
            $mail->Host       = 'smtp.office365.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sistemas@distrirex.com';
            $mail->Password   = '@DistriREX*25+@';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Configuración de remitente y destinatario
            $mail->setFrom('sistemas@distrirex.com', 'Soporte');
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

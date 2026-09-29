<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class IndexController extends BaseController
{
    public function enviar()
    {
        $nombre = $this->request->getPost('name');
        $email = $this->request->getPost('email');
        $mensaje = $this->request->getPost('message');

        $to = 'desarrollo03@inversionesarar.com';
        $subject = 'Nuevo mensaje desde la web Hecarse';

        $body = "
            <strong>Nombre:</strong> {$nombre}<br>
            <strong>Email:</strong> {$email}<br><br>
            <strong>Mensaje:</strong><br>
            {$mensaje}
        ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: Web Hecarse <no-reply@hecarse.com>\r\n";
        $headers .= "Reply-To: {$email}\r\n";

        if (mail($to, $subject, $body, $headers)) {
            return $this->response->setJSON(['status' => 'ok']);
        } else {
            return $this->response->setJSON(['status' => 'error']);
        }
    }
}

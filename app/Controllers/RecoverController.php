<?php

namespace App\Controllers;

use App\Models\UsuariosModel;
use App\Controllers\BaseController;
use App\Controllers\CorreoController;
use CodeIgniter\HTTP\RedirectResponse;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class RecoverController extends BaseController
{
    /**
    //  * @var UserModel $model Modelo de usuarios.
     */
    private $usuarios_model;

    function __construct()
    {
        $this->usuarios_model = new UsuariosModel();
    }

    public function index(): string|RedirectResponse
    {
        // Definición de las variables
        $data = ['title' => 'Hecarse'];
        $data['welcome_message'] = lang('Recover.welcome_recover');
        $data['subtitle_recover'] = lang('Recover.subtitle_recover');
        $data['email_label'] = lang('Recover.email_label');
        $data['btn_recover'] = lang('Recover.btn_recover');
        $data['validation_required'] = lang('Recover.validation_required');
        $data['back_to_login'] = lang('Recover.back_to_login');

        // Retornar la vista con sus variables
        return view('recoverPassword', $data);
    }

    public function envioEnlace()
    {
        $usuariosModel = new UsuariosModel();

        if ($this->request->getMethod() == 'POST') {
            $rules = ['Email' => 'required|valid_email'];

            if ($this->validate($rules)) {
                $email = $this->request->getVar('Email');
                $usuario = $usuariosModel->verifyEmail($email);

                if ($usuario) {
                    // Generar token de recuperación
                    $resetToken = bin2hex(random_bytes(25));
                    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $fechaToken = explode(' ', $tokenExpiry)[0] . 'T' . explode(' ', $tokenExpiry)[1];
                    $usuariosModel->insertResetPassword($usuario['Id'], $resetToken, $fechaToken);

                    // Enlace de recuperación
                    $enlace = base_url('resetPass/' . $resetToken);

                    // Mensaje del correo
                    $body = '<!DOCTYPE html>
                                <html lang="es">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Recuperación de contraseña Portal Hecarse</title>
                                    <style>
                                        body {
                                            font-family: Arial, sans-serif;
                                            background-color: #f4f4f4;
                                            color: #333;
                                            padding: 20px;
                                        }
                                        .container {
                                            background-color: #fff;
                                            border-radius: 8px;
                                            padding: 20px;
                                            max-width: 600px;
                                            margin: 0 auto;
                                            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                                        }
                                        h2 {
                                            color: #41a867;
                                            text-align: center;
                                        }
                                        .btn {
                                            display: inline-block;
                                            width: fit-content; 
                                            margin-left: auto; 
                                            margin-right: auto;
                                            background-color: #41a867;
                                            color: #fff;
                                            padding: 10px 20px;
                                            text-decoration: none;
                                            border-radius: 5px;
                                            font-size: 16px;
                                            margin-top: 20px;
                                            transition: background-color 0.3s ease;
                                        }
                                        .btn:hover {
                                            background-color: #41a867;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <h2>Enlace de Recuperación</h2>
                                        <p>Para restablecer tu contraseña y acceder al portal de Hecarse, haz clic en el siguiente botón. Este enlace te llevará al formulario para actualizar tu contraseña.</p>
                                        <p>Ten en cuenta que el enlace será válido solo para un único cambio de contraseña y tendrá una duración de 1 hora a partir de su generación.</p>
                                        <div style="text-align: center;">
                                            <a href="' . $enlace . '" class="btn">Recupera tu Contraseña Aquí</a><br><br><br>
                                        </div>
                                        <tr>
                                            <td style="color:black; background-color:white; width: 662px; height: 131px; 
                                                    border:1px solid #ABABAB; box-sizing:border-box; word-break:break-word;">
                                                <div style="display: flex; align-items: center;">
                                                    <!-- Logo -->
                                                    <img src="https://distrirex.com/app/public/assets/img/logo_hecarse.png" 
                                                        style="width: 150px; height: 80px; margin-right: 30px;">
                                                    
                                                    <!-- Texto -->
                                                    <div style="color:#002451;">
                                                        <span style="font-size:14pt;font-family:Verdana,sans-serif;">Soporte Web</span><br>
                                                        <span style="font-size:9pt;font-family:Verdana,sans-serif;">Calle 29 # 25 - 72 Torre de Oficina 5 piso<br>
                                                        Floridablanca, Santander, Colombia</span><br>
                                                        <span style="font-size:9pt;font-family:Verdana,sans-serif;">Teléfono: (+57) (7) 639 6969 Ext. 1099634</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </div>
                                </body>
                            </html>';

                    // Usamos la librería CorreoHelper
                    $correoEnviado = CorreoController::sendEmail($email, 'Recuperación de contraseña', $body);

                    if ($correoEnviado === true) {
                        return $this->response->setJSON(['success' => true, 'message' => 'Correo enviado correctamente.']);
                    } else {
                        return $this->response->setJSON(['success' => false, 'message' => 'Error al enviar el correo.', 'debug' => $correoEnviado]);
                    }
                } else {
                    return $this->response->setJSON(['success' => false, 'message' => 'El correo no está registrado.']);
                }
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Por favor, ingrese un correo válido.']);
            }
        }
    }

}

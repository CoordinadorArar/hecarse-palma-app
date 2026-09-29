<?php

namespace App\Controllers;

use App\Models\UsuariosModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class ResetPassController extends BaseController
{
    /**
     * @var UserModel $model Modelo de usuarios.
     */
    private $usuarios_model;

    function __construct()
    {
        $this->usuarios_model = new UsuariosModel();
    }

    /**
     * Metodo para ir a la vista.
     */
    public function index($token = null): string|RedirectResponse
    {
        // Definición de las variables
        $data = ['title' => 'Hecarse'];
        $data['welcome_message'] = lang('ResetPass.welcome_login');
        $data['subtitle_login'] = lang('ResetPass.subtitle_login');
        $data['password'] = lang('ResetPass.password');
        $data['password_confirm'] = lang('ResetPass.password_confirm');
        $data['validation_required'] = lang('ResetPass.validation_required');
        $data['btn_update_pass'] = lang('ResetPass.btn_update_pass');
        $data['data_protection'] = lang('ResetPass.data_protection');
        $data['token'] = $token;
        $data['msg_caducado'] = '';
        $data['userId'] = '';

        $usuariosModel = new UsuariosModel();
        $resetData = $usuariosModel->verifyResetToken($token);

        if ($resetData) {
            // Si el token es válido, obtenemos el ID del usuario
            $userId = $resetData['IdUsuario'];

            $data['userId'] = $userId;

            // Retornar la vista con sus variables
            return view('updatePassword', $data);

        } else {
            // Si el token no es válido o ha caducado
            $data['msg_caducado'] = 'El enlace de recuperación ha caducado o no es válido.';
            return view('updatePassword', $data);

        }
    }


    /**
     * Metodo para actualizar la contraseña.
     */
    public function verifyPasswords()
    {
        if ($this->request->getMethod() == 'POST') {

            $resetToken = $this->request->getPost('token_generado');
            $userId = $this->request->getPost('id_usuario');
            $password = $this->request->getPost('password');
            $password_confirm = $this->request->getPost('password_confirm');

            // Verificar si las contraseñas coinciden
            if ($password !== $password_confirm) {
                return $this->response->setJSON(['success' => false, 'message' => 'Las contraseñas ingresadas no coinciden.']);
            }

            $expresion = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';

            if (!preg_match($expresion, $password)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'La contraseña debe tener mínimo 8 caracteres, incluir al menos una mayúscula, una minúscula y un número.'
                ]);
            }

            // Ahora instanciamos el modelo UsuariosModel
            $usuariosModel = new UsuariosModel();


            //Actualizar contraseña en la base
            if ($usuariosModel->updatePassword($userId, $password)) {
                $usuariosModel->deleteToken($resetToken);
                return $this->response->setJSON(['success' => true, 'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión']);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'No se pudo actualizar la contraseña, por favor inténtelo de nuevo.']);
            }

        }
        // Si no se recibió una solicitud POST
        return $this->response->setJSON(['success' => false, 'message' => 'Error en la solicitud']);
    }


}

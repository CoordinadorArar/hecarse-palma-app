<?php

namespace App\Controllers;

use App\Models\UsuariosModel;
use CodeIgniter\HTTP\RedirectResponse;
use DateTime;

class LoginController extends BaseController
{
    /**
    //  * @var UserModel $model Modelo de usuarios.
     */
    private $usuarios_model;

    function __construct()
    {
        $this->usuarios_model = new UsuariosModel();
    }

    public function index()
    {
        return view('index');
    }

    /**
     * Metodo para renderizar la vista de login.
     * 
     * @return string|RedirectResponse Vista de login.
     */
    public function login(): string|RedirectResponse
    {
        $session = session();

        if ($session->has('usu_id')) {
            return redirect()->to(base_url('losetas'));
        }

        $data = ['title' => 'Hecarse'];
        $data['welcome_message'] = lang('Login.welcome_login');
        $data['subtitle_login'] = lang('Login.subtitle_login');
        $data['username'] = lang('Login.username');
        $data['password'] = lang('Login.password');
        $data['validation_required'] = lang('Login.validation_required');
        $data['btn_login'] = lang('Login.btn_login');
        $data['data_recover'] = lang('Login.data_recover');
        $data['data_protection'] = lang('Login.data_protection');

        return view('login', $data);
    }

    /**
     * Metodo para validar el login del usuario.
     */
    public function verifyUser()
    {
        if ($this->request->getMethod() == 'POST') {
            $rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            if ($this->validate($rules)) {
                $username = $this->request->getVar('username');
                $password = $this->request->getVar('password');

                $user = $this->usuarios_model->getUsuarioPorUsername($username);

                if (!$user) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Usuario no encontrado en nuestra base de datos. Favor verifique de nuevo']);
                }

                // Validar si el usuario está bloqueado
                if (!empty($user['FechaFinalizacion'])) {
                    return $this->response->setJSON(['success' => false, 'message' => 'La cuenta está bloqueada. Por favor contacte a soporte para activar su cuenta.']);
                }

                // Validar contraseña
                if (password_verify($password, $user['Contrasena'])) {

                    $expresion = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
                    $validaSeguridad = preg_match($expresion, $password);
                    $campoSegura = $user['ContrasenaSegura'];

                    if (!$validaSeguridad || $campoSegura != 1) {
                        return $this->response->setJSON([
                            'success' => false,
                            'requiereCambioPass' => true,
                            'message' => 'Debe actualizar su contraseña para cumplir con los requisitos de seguridad. La contraseña debe tener mínimo 8 caracteres, mayúsculas, minúsculas, números.'
                        ]);
                    }

                    $this->usuarios_model->reiniciarIntentos($user['Id']);

                    // Iniciar sesión
                    $session = session();
                    $session->set('usu_autenticado', 'si');
                    $session->set('usu_login', $user['Usuario']);
                    $session->set('usu_contrasena', $user['Contrasena']);
                    $session->set('usu_id', $user['Id']);
                    $session->set('usu_nombres', $user['Nombre']);
                    $session->set('usu_apellidos', $user['Apellido']);
                    $session->set('usu_empresa', $user['Empresa']);
                    $session->set('usu_telefono', $user['Telefono']);
                    $session->set('usu_email', $user['Email']);
                    $session->set('ultimo_acceso', time());

                    // Registrar Auditoria de sesiones 
                    $auditoria = [
                        'IdUsuario' => $user['Id'],
                        'FechaInicio' => date('Ymd H:i:s')
                    ];

                    $idAuditoria = $this->usuarios_model->registrarInicioSesion($auditoria);

                    $session->set('id_auditoria_sesion', $idAuditoria);

                    return $this->response->setJSON(['success' => true]);
                } else {
                    // Contraseña incorrecta: incrementar intentos
                    $this->usuarios_model->incrementarIntentos($user['Id']);

                    // Volver a consultar los intentos luego de incrementar
                    $userActualizado = $this->usuarios_model->getUsuarioPorUsername($username);

                    if ($userActualizado['IntentosFallidos'] >= 3) {
                        $this->usuarios_model->bloquearUsuario($user['Id']);
                        return $this->response->setJSON(['success' => false, 'message' => 'Cuenta bloqueada. Contacte a soporte para habilitar la cuenta.']);
                    }
                    return $this->response->setJSON(['success' => false, 'message' => 'Contraseña incorrecta. Intento ' . $userActualizado['IntentosFallidos'] . ' de 3.']);
                }
            } else {
                return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
            }
        }
        return $this->response->setJSON(['success' => false, 'message' => lang('Login.error_login')]);
    }


    /**
     * Metodo para cerrar sesion del usuario.
     */
    public function cerrar_sesion()
    {
        $session = session();
        $idAuditoria = $session->get('id_auditoria_sesion');

        if ($idAuditoria) {
            $this->usuarios_model->registrarCierreSesion($idAuditoria, [
                'FechaCierre' => date('Ymd H:i:s')
            ]);
        }

        $session->destroy();

        return $this->response->setJSON(['success' => true]);
    }


    public function logout()
    {
        $session = session();

        $idAuditoria = $session->get('id_auditoria_sesion');

        if ($idAuditoria) {
            $this->usuarios_model->registrarCierreSesion($idAuditoria, [
                'FechaCierre' => date('Ymd H:i:s')
            ]);
        }

        session()->destroy();

        // return redirect()->to(site_url('login/'));
        // return view('login');

        return redirect()->to(base_url(''));
    }



}

<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

use App\Models\LosetasModel;
use App\Models\UsuariosModel;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
    }

    /**
     * Metodo para obtener toda la informacion del usuario que se necesita
     * para renderizar en la vista.
     * 
     * @return array Informacion del usuario.
     */
    protected function getUserInformation()
    {
        $session = session();

        $nombres_apellidos = $session->get('usu_nombres') . ' ' . $session->get('usu_apellidos');
        $nombres = $session->get('usu_nombres');

        return [
            'nombres_apellidos' => $nombres_apellidos,
            'nombres' => $nombres
        ];
    }

    /**
     * Metodo para obtener los modulos de la loseta a los que tiene acceso el usuario
     * dependiendo del rol que tenga.
     * 
     * Se ordenan los modulos de la loseta, dejando el modulo "Onboarding" en primer lugar.
     * 
     * @param string $usu_id Codigo del usuario.
     * @param string $id_loseta Identificador de la loseta.
     * @return array Modulos de la loseta ordenados.
     */
    protected function getModuloByUsuarioRolAndLoseta($usu_id, $id_loseta): array
    {
        $losetas_model = new LosetasModel();
        $modulos = $losetas_model->getModuloByUsuarioRolAndLoseta($usu_id, $id_loseta);

        usort($modulos, function ($a, $b) {
            if ($a['Nombre'] === 'Onboarding') {
                return -1;
            }
            if ($b['Nombre'] === 'Onboarding') {
                return 1;
            }
            return 0;
        });

        return $modulos;
    }

    /**
     * Metodo para obtener los submodulos de la loseta a los que tiene acceso el usuario
     * dependiendo del rol que tenga.
     * 
     * @param string $usu_id Codigo del usuario.
     * @param string $id_loseta Identificador de la loseta.
     * @return array Modulos de la loseta ordenados.
     */
    protected function getSubModuloByUsuarioRolAndLoseta($usu_id, $id_loseta, $id_modulo): array
    {
        $losetas_model = new LosetasModel();
        $submodulos = $losetas_model->getSubModuloByUsuarioRolAndLoseta($usu_id, $id_loseta, $id_modulo);

        usort($submodulos, function ($a, $b) {
            if ($a['Nombre'] === 'Onboarding') {
                return -1;
            }
            if ($b['Nombre'] === 'Onboarding') {
                return 1;
            }
            return 0;
        });

        return $submodulos;
    }

    /**
     * Metodo para buscar la imagen de usuario.
     * En caso no encontrarse la imagen, se retorna una imagen por defecto.
     * 
     * @return string Ruta de la imagen de usuario.
     */
    protected function obtenerImagenUsuario(): string
    {
        $session = session();
        $userId = $session->get('usu_id');

        // Instancia del modelo de usuario
        $usuarioModel = new UsuariosModel();
        $usuario = $usuarioModel->getUserById($userId);

        // Verifica si tiene ruta de imagen en la BD
        if ($usuario && !empty($usuario['RutaImagen'])) {
            $rutaArchivo = FCPATH . $usuario['RutaImagen']; // ruta absoluta en el servidor
            if (file_exists($rutaArchivo)) {
                return base_url($usuario['RutaImagen']); // url pública
            }
        }

        // Si no tiene imagen o no existe el archivo, retorna la imagen por defecto
        return base_url('public/assets/user_images/usuario_sin_foto.png');
    }

}

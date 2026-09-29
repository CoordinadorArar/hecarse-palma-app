<?php

namespace App\Libraries;
use App\Models\LosetasModel;
use App\Models\UsuariosModel;

class InformacionMenus
{
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
    public function getModuloByUsuarioRolAndLoseta($usu_id, $id_loseta): array
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
    public function getSubModuloByUsuarioRolAndLoseta($usu_id, $id_loseta, $id_modulo): array
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
     * Metodo para obtener toda la informacion del usuario que se necesita
     * para renderizar en la vista.
     * 
     * @return array Informacion del usuario.
     */
    public function getUserInformation()
    {
        $session = session();

        $nombres_apellidos = $session->get('usu_nombres') . ' ' . $session->get('usu_apellidos');
        $nombres = $session->get('usu_nombres');
        $correo = $session->get('usu_email');

        return [
            'nombres_apellidos' => $nombres_apellidos,
            'nombres' => $nombres,
            'correo' => $correo
        ];
    }

    /**
     * Metodo para buscar la imagen de usuario.
     * En caso no encontrarse la imagen, se retorna una imagen por defecto.
     * 
     * @return string Ruta de la imagen de usuario.
     */
    public function obtenerImagenUsuario(): string
    {
        $session = session();
        $userId = $session->get('usu_id');

        $usuarioModel = new UsuariosModel();
        $usuario = $usuarioModel->getUserById($userId);

        if ($usuario && !empty($usuario['RutaImagen'])) {
            $rutaArchivo = FCPATH . $usuario['RutaImagen'];
            if (file_exists($rutaArchivo)) {
                return base_url($usuario['RutaImagen']);
            }
        }

        return base_url('public/assets/user_images/usuario_sin_foto.png');
    }
}
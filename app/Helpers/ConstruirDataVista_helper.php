<?php

use App\Models\LosetasModel;
use App\Models\UsuariosModel;
use App\Libraries\InformacionMenus;

function construirVista($usu_id, $id_loseta)
{
    $losetas_model = new LosetasModel();
    $usuarios_model = new UsuariosModel();
    $informacion_menus = new InformacionMenus();

    if (!$usu_id) {
        return redirect()->to(base_url(''));
    }

    $modulos = $informacion_menus->getModuloByUsuarioRolAndLoseta($usu_id, $id_loseta);

    $submodulos = [];
    foreach ($modulos as $modulo) {
        $submodulo = $informacion_menus->getSubModuloByUsuarioRolAndLoseta($usu_id, $id_loseta, $modulo['IdModulo']);

        $submodulos = array_merge($submodulos, $submodulo);
    }

    $nombre_loseta = $losetas_model->getNombreLosetaById($id_loseta);
    $informacion_usuario = $informacion_menus->getUserInformation();
    $imagen_usuario = $informacion_menus->obtenerImagenUsuario();
    $usuario = $usuarios_model->getUserById($usu_id);

    $data = [
        'title' => 'Hecarse',
        'nombre_loseta' => $nombre_loseta,
        'nombres_apellidos' => $informacion_usuario['nombres_apellidos'],
        'nombres' => $informacion_usuario['nombres'],
        'correo' => $informacion_usuario['correo'],
        'imagen_usuario' => $imagen_usuario,
        'usuario' => $usuario,
        'sidebar' => $modulos,
        'submodulos' => $submodulos,
        'id_loseta' => $id_loseta
    ];

    return $data;
}

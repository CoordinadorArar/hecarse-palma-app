<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LosetasModel;
use App\Models\UsuariosModel;
use App\Models\RolesModel;

class UsuariosController extends BaseController
{
    private $session;

    private $losetas_model;

    private $usuarios_model;

    private $roles_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->usuarios_model = new UsuariosModel();
        $this->roles_model = new RolesModel();
    }

    /**
     * Metodo para renderizar la vista de todos los usuarios.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los usuarios.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista', );

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $tabla_usuarios = $this->renderizarListaUsuarios();

        $data['subtitle'] = 'Lista de usuarios';
        $data['tabla_usuarios'] = $tabla_usuarios;
        $data['lista_usuarios_activos'] = $this->getActiveUsers();
        $data['lista_roles_activos'] = $this->getActiveRoles();

        return view('admin/usuarios', $data);
    }

    private function renderizarListaUsuarios(): string
    {
        $usuarios = $this->usuarios_model->getUsers();

        $html = '<table id="tabla_usuarios" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Documento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>';

        $html .= '<tbody>';

        foreach ($usuarios as $usuario) {
            $estado = is_null($usuario['FechaFinalizacion']) ? 'activo' : 'inactivo';
            $html .= '<tr class="' . $estado . '">
                    <td>' . htmlspecialchars($usuario['Nombre']) . '</td>
                    <td>' . htmlspecialchars($usuario['Apellido']) . '</td>
                    <td>' . htmlspecialchars($usuario['Usuario']) . '</td>
                    <td>' . htmlspecialchars($usuario['Email']) . '</td>
                    <td>' . htmlspecialchars($usuario['Documento']) . '</td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="obtenerUsuario(' . $usuario['Id'] . ')">Modificar</button>
                    </td>
                </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Metodo para obtener la informacion de un usuario en especifico.
     * 
     * @param int $id Identificador del usuario.
     * @return ResponseInterface Informacion del usuario formato JSON
     */
    public function getUserById(int $id): ResponseInterface
    {
        $usuario = $this->usuarios_model->getUserById($id);
        return $this->response->setJSON(['success' => true, 'usuario' => $usuario]);
    }

    /**
     * Metodo para actualizar un usuario.
     */
    public function updateUser(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];
        $esInactivo = $this->request->getVar('FechaFinalizacion') == 0;

        $data = [
            'Nombre' => $this->request->getVar('Nombre'),
            'Apellido' => $this->request->getVar('Apellido'),
            'Usuario' => $this->request->getVar('Usuario'),
            'Empresa' => $this->request->getVar('Empresa'),
            'Email' => $this->request->getVar('Email'),
            'Telefono' => $this->request->getVar('Telefono'),
            'Documento' => $this->request->getVar('Documento'),
            'FechaModificacion' => $fecha,
            'FechaFinalizacion' => $esInactivo ? $fecha : null
        ];

        if (!$esInactivo) {
            $data['IntentosFallidos'] = 0;
        }

        $this->usuarios_model->updateUser($this->request->getVar('Id'), $data);
        $table = $this->renderizarListaUsuarios();
        return $this->response->setBody($table);
    }

    /**
     * Metodo para buscar un usuario por su nombre.
     * Se renderiza la respuesta en el select para ver los roles del usuario.
     * 
     * @return ResponseInterface Resultados de la busqueda.
     */
    public function findUserByName()
    {
        $term = $this->request->getPost('term');

        $usuarios = $this->usuarios_model->findUserByName($term);

        $results = [];
        foreach ($usuarios as $usuario) {
            $results[] = [
                'id' => $usuario['Id'],
                'text' => $usuario['Nombre'] . ' ' . $usuario['Apellido'] . ' - ' . $usuario['Documento']
            ];
        }

        return $this->response->setJSON($results);
    }

    /**
     * Metodo para obtener los roles de un usuario en especifico y 
     * los roles que no tiene asignado.
     * 
     * @return ResponseInterface Resultados de la operacion.
     */
    public function getAllRolesByUser(): ResponseInterface
    {
        $all_roles = $this->getRolesNotUser($this->request->getVar('usuarioId'));
        $roles_usuario = $this->getRolesByUser($this->request->getVar('usuarioId'));

        return $this->response->setJSON(['asignados' => $roles_usuario, 'todos' => $all_roles]);
    }

    /**
     * Metodo para obtener los roles del sistema y armar el html
     * que se muestra en la vista para listarlos.
     * 
     * @param int $id_usuario Identificador del usuario.
     * @return string Roles del sistema.
     */
    public function getRolesNotUser($id_usuario): string
    {
        $roles = $this->roles_model->getRolesNotUser($id_usuario);

        $html = '';
        foreach ($roles as $rol) {
            $html .= '
                <li id="' . $rol['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $rol['Nombre'] . '
                    <button id="asignar_' . esc($rol['Id']) . '" type="button" class="btn btn-primary btn-sm" onclick="asginarRol(' . esc($rol['Id']) . ')">Asignar</button>
                </li>
            ';
        }

        return $html;
    }

    /**
     *  Metodo para obtener los roles de un usuario en especifico y armar
     * el html para renderizarlo en la vista.
     * 
     * @param int $id_usuario Identificador del usuario.
     * @return string html con el listado de roles por usuario.
     */
    public function getRolesByUser($id_usuario): string
    {
        $roles = $this->roles_model->getRolesByUser($id_usuario);

        $html = '';
        foreach ($roles as $rol) {
            $html .= '
                <li id="' . $rol['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $rol['Nombre'] . '
                    <button id="quitar_' . esc($rol['Id']) . '" type="button" class="btn btn-primary btn-sm" onclick="quitarRol(' . esc($rol['Id']) . ')">Quitar</button>
                </li>
            ';
        }

        return $html;
    }

    /**
     * Metodo para quitar un rol a un usuario en especifico.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function deleteUserRole(): ResponseInterface
    {
        $id_usuario = $this->request->getVar('usuarioId');
        $id_rol = $this->request->getVar('rolId');

        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $this->roles_model->deleteUserRole($id_usuario, $id_rol, $fecha);
        $roles_usuario = $this->getRolesByUser($id_usuario);
        $all_roles = $this->getRolesNotUser($id_usuario);

        return $this->response->setJSON(['asignados' => $roles_usuario, 'todos' => $all_roles]);
    }

    /**
     * Metodo para quitar un rol a un usuario en especifico.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function addUserRole(): ResponseInterface
    {
        $id_usuario = $this->request->getVar('usuarioId');
        $id_rol = $this->request->getVar('rolId');

        $roles_usuario = $this->roles_model->getAllRolesByUser($id_usuario);

        $flag = false;

        foreach ($roles_usuario as $rol) {
            if ($rol['Id'] == $id_rol) {
                $flag = true;
                break;
            }
        }

        if ($flag) {
            $this->roles_model->updateUserRole($id_usuario, $id_rol);
        } else {
            $this->roles_model->addUserRole($id_usuario, $id_rol);
        }

        $roles_usuario = $this->getRolesByUser($id_usuario);
        $all_roles = $this->getRolesNotUser($id_usuario);

        return $this->response->setJSON(['asignados' => $roles_usuario, 'todos' => $all_roles]);
    }

    /**
     * Metodo para gestionar la asignacion de un rol a varios usuarios.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function addUsersRole(): ResponseInterface
    {
        $id_rol = $this->request->getVar('rol');
        $usuarios = $this->request->getVar('usuarios');
        $usuarios_array = explode(',', $usuarios);

        foreach ($usuarios_array as $usuario) {
            $roles_usuario = $this->roles_model->getAllRolesByUser($usuario);

            $flag = false;

            foreach ($roles_usuario as $rol) {
                if ($rol['Id'] == $id_rol) {
                    $flag = true;
                    break;
                }
            }

            if ($flag) {
                $this->roles_model->updateUserRole($usuario, $id_rol);
            } else {
                $this->roles_model->addUserRole($usuario, $id_rol);
            }
        }

        $roles = $this->getActiveRoles();
        $usuarios = $this->getActiveUsers();

        return $this->response->setJSON([
            'success' => true,
            'message' =>
                'Roles asignados correctamente.',
            'roles' => $roles,
            'usuarios' => $usuarios
        ]);
    }

    /**
     * Metodo para gestionar la creacion de un usuario nuevo.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function createUser(): ResponseInterface
    {
        $usuario = $this->request->getPost('Usuario');

        if ($this->usuarios_model->verifyNameUser($usuario)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El nombre de usuario ya existe. Por favor elige otro.'
            ]);
        }

        $data = [
            'Nombre' => $this->request->getVar('Nombre'),
            'Apellido' => $this->request->getVar('Apellido'),
            'Usuario' => $this->request->getVar('Usuario'),
            'Empresa' => $this->request->getVar('Empresa'),
            'Email' => $this->request->getVar('Email'),
            'Telefono' => $this->request->getVar('Telefono'),
            'Documento' => $this->request->getVar('Documento'),
            'Contrasena' => password_hash($this->request->getVar('Password'), PASSWORD_ARGON2ID)
        ];

        $this->usuarios_model->createUser($data);
        $table = $this->renderizarListaUsuarios();
        return $this->response->setBody($table);
    }

    /**
     * Metodo para obtener los usuarios activos.
     * 
     * @return string Listado de usuarios en formato HTML.
     */
    public function getActiveUsers(): string
    {
        $usuarios = $this->usuarios_model->getActiveUsers();

        $html = '';
        foreach ($usuarios as $key => $usuario) {
            $html .= '<option value="' . $usuario['Id'] . '">' . $usuario['Nombre'] . ' ' . $usuario['Apellido'] . '</option>';
        }

        return $html;
    }

    /**
     * Metodo para obtener todos los roles que se encuentran activos.
     * 
     * @return string Listado de roles en formato HTML.
     */
    public function getActiveRoles(): string
    {
        $roles = $this->roles_model->getActiveRoles();

        $html = '<option value="0">-- Seleccione un rol --</option>';
        foreach ($roles as $key => $rol) {
            $html .= '<option value="' . $rol['Id'] . '">' . $rol['Nombre'] . '</option>';
        }

        return $html;
    }

    /**
     * Metodo para actualizar un usuario.
     */
    public function updatePass(): ResponseInterface
    {
        // $date = date('Y-m-d H:i:s');
        // $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $pass = $this->request->getVar('Contraseña');

        // $data = [
        //     'Contrasena' => $this->request->getVar('Contraseña'),
        //     'FechaModificacion' => $fecha
        // ];

        $this->usuarios_model->updatePassword($this->request->getVar('Id'), $pass);
        $table = $this->renderizarListaUsuarios();
        return $this->response->setBody($table);
    }


    public function guardarFotoPerfil()
    {
        $id = $this->request->getPost('id_usuario');
        $imagen = $this->request->getFile('imagenPerfil');

        if (!$imagen->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se recibió una imagen válida.'
            ]);
        }

        if ($imagen->getSize() > 2 * 1024 * 1024) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'La imagen no debe superar los 2MB.'
            ]);
        }

        // $imagen = $this->request->getFile('imagenPerfil');

        $nombreImagen = $id . '.' . $imagen->getExtension();
        $rutaDestino = 'public/assets/user_images/' . $nombreImagen;
        $rutaCompleta = FCPATH . $rutaDestino;

        //Eliminar la imagen que está relacionado con el usuario
        if (file_exists($rutaCompleta)) {
            unlink($rutaCompleta);
        }

        if ($imagen->move(FCPATH . 'public/assets/user_images/', $nombreImagen, true)) {
            $this->usuarios_model->actualizarFotoPerfil($id, $rutaDestino);

            return $this->response->setJSON([
                'success' => true,
                'rutaImagen' => base_url($rutaDestino)
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se pudo mover el archivo al servidor.'
            ]);
        }
    }
}

<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LosetasModel;
use App\Models\RolesModel;

class RolesController extends BaseController
{
    private $session;

    private $losetas_model;

    private $roles_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->roles_model = new RolesModel();
    }

    /**
     * Metodo para renderizar la vista de todos los roles.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los roles.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $tabla_roles = $this->renderizarListaRoles();

        $data['tabla_roles'] = $tabla_roles;

        return view('admin/roles', $data);
    }

    private function renderizarListaRoles(): string
    {
        $roles = $this->roles_model->getAllRoles();

        $html = '<table id="tabla_roles" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Fecha Creación</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>';

        $html .= '<tbody>';

        foreach ($roles as $rol) {
            $estado = is_null($rol['FechaFinalizacion']) ? 'activo' : 'inactivo';
            $html .= '<tr class="' . $estado . '">
                    <td>' . htmlspecialchars($rol['Nombre']) . '</td>
                    <td>' . htmlspecialchars($rol['FechaInicio']) . '</td>
                    <td>' . htmlspecialchars($rol['NombreUsuario'] . ' ' . $rol['ApellidoUsuario']) . '</td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editRolModal" onclick="obtenerRol(' . $rol['Id'] . ')">Modificar</button>
                    </td>
                </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Metodo para obtener la informacion de un rol en especifico.
     * 
     * @param int $id Identificador del rol.
     * @return ResponseInterface Informacion del rol formato JSON
     */
    public function getRolById(int $id): ResponseInterface
    {
        $rol = $this->roles_model->getRolById($id);
        return $this->response->setJSON(['success' => true, 'rol' => $rol]);
    }

    /**
     * Metodo para actualizar un rol.
     */
    public function updateRol(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $data = [
            'Nombre' => $this->request->getVar('Nombre'),
            'FechaModificacion' => $fecha,
            'FechaFinalizacion' => $this->request->getVar('FechaFinalizacion') == 0 ? $fecha : null
        ];

        $this->roles_model->updateRol($this->request->getVar('Id'), $data);
        $table = $this->renderizarListaRoles();
        return $this->response->setBody($table);
    }

    /**
     * Metodo para buscar un rol por su nombre.
     * Se renderiza la respuesta en el select para ver los módulos del rol.
     * 
     * @return ResponseInterface Resultados de la busqueda.
     */
    public function findRolByName()
    {
        $term = $this->request->getPost('term');

        $roles = $this->roles_model->findRolByName($term);

        $results = [];
        foreach ($roles as $rol) {
            $results[] = [
                'id' => $rol['Id'],
                'text' => $rol['Nombre']
            ];
        }

        return $this->response->setJSON($results);
    }

    /**
     * Metodo para obtener los módulos de un rol en especifico y 
     * los módulos que no tiene asignado.
     * 
     * @return ResponseInterface Resultados de la operacion.
     */
    public function getAllModulesByRol(): ResponseInterface
    {
        $all_modules = $this->getModulesNotRol($this->request->getVar('rolId'));
        $modules_rol = $this->getModulesByRol($this->request->getVar('rolId'));

        return $this->response->setJSON(['asignados' => $modules_rol, 'todos' => $all_modules]);
    }

    /**
     * Metodo para obtener los modulos del sistema y armar el html
     * que se muestra en la vista para listarlos.
     * 
     * @param int $id_rol Identificador del rol.
     * @return string Módulos del sistema.
     */
    public function getModulesNotRol($id_rol): string
    {
        $modulos = $this->losetas_model->getModulesNotRol($id_rol);

        $html = '';
        foreach ($modulos as $modulo) {
            $html_nombre_loseta = (!empty($modulo['IdLoseta'])) ? $modulo['LosetaPadre'] . ' > ' . $modulo['Nombre'] : $modulo['Nombre'];
            $html_nombre_modulo = (!empty($modulo['IdModulo'])) ? $modulo['LosetaPadre'] . ' > ' . $modulo['ModuloPadre'] . ' > ' . $modulo['Nombre'] : $html_nombre_loseta;
            $html .= '
                <li id="' . $modulo['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $html_nombre_modulo . '
                    <button id="asignar_' . esc($modulo['Id']) . '" type="button" class="btn btn-primary btn-sm" onclick="asginarModulo(' . esc($modulo['Id']) . ')">Asignar</button>
                </li>
            ';
        }

        return $html;
    }

    /**
     *  Metodo para obtener los modulos de un usuario en especifico y armar
     * el html para renderizarlo en la vista.
     * 
     * @param int $id_rol Identificador del usuario.
     * @return string html con el listado de modulos por usuario.
     */
    public function getModulesByRol($id_rol): string
    {
        $modulos = $this->losetas_model->getModulesByRol($id_rol);

        $html = '';
        foreach ($modulos as $modulo) {
            $html_nombre_loseta = (!empty($modulo['IdLoseta'])) ? $modulo['LosetaPadre'] . ' > ' . $modulo['Nombre'] : $modulo['Nombre'];
            $html_nombre_modulo = (!empty($modulo['IdModulo'])) ? $modulo['LosetaPadre'] . ' > ' . $modulo['ModuloPadre'] . ' > ' . $modulo['Nombre'] : $html_nombre_loseta;
            $html .= '
                <li id="' . $modulo['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $html_nombre_modulo . '
                    <button id="quitar_' . esc($modulo['Id']) . '" type="button" class="btn btn-primary btn-sm" onclick="quitarModulo(' . esc($modulo['Id']) . ')">Quitar</button>
                </li>
            ';
        }

        return $html;
    }

    /**
     * Metodo para quitar un módulo a un rol en especifico.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function deleteRolModule(): ResponseInterface
    {
        $id_rol = $this->request->getVar('rolId');
        $id_modulo = $this->request->getVar('moduloId');

        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $ok = $this->losetas_model->deleteRolModule($id_rol, $id_modulo, $fecha);
        $modulos_rol = $this->getModulesByRol($id_rol);
        $all_modulos = $this->getModulesNotRol($id_rol);

        return $this->response
            ->setStatusCode($ok ? 200 : 500)
            ->setJSON([
                'success' => $ok,
                'message' => $ok ? 'Módulo quitado correctamente.' : 'No se pudo quitar el módulo.',
                'asignados' => $modulos_rol,
                'todos' => $all_modulos
            ]);
    }

    /**
     * Metodo para agregar un modulo a un rol.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function addRolModule(): ResponseInterface
    {
        $id_rol = $this->request->getVar('rolId');
        $id_modulo = $this->request->getVar('moduloId');

        $modulos_rol = $this->losetas_model->getAllModulesByRol($id_rol);

        $flag = false;

        foreach ($modulos_rol as $rol) {
            if ($rol['IdModulo'] == $id_modulo) {
                $flag = true;
                break;
            }
        }

        if ($flag) {
            $ok = $this->losetas_model->updateRolModule($id_rol, $id_modulo);
        } else {
            $ok = $this->losetas_model->addRolModule($id_rol, $id_modulo);
        }

        $modulos_rol = $this->getModulesByRol($id_rol);
        $all_roles = $this->getModulesNotRol($id_rol);

        return $this->response
            ->setStatusCode($ok ? 200 : 500)
            ->setJSON([
                'success' => $ok,
                'message' => $ok ? 'Módulo asignado correctamente.' : 'No se pudo asignar el módulo.',
                'asignados' => $modulos_rol,
                'todos' => $all_roles
            ]);
    }

    /**
     * Metodo para gestionar la asignacion de un rol a varios usuarios.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function addRolsModule(): ResponseInterface
    {
        $id_modulo = $this->request->getVar('modulo');
        $roles = $this->request->getVar('roles');
        $roles_array = explode(',', $roles);

        foreach ($roles_array as $rol) {
            $modulos_rol = $this->losetas_model->getModulesByRol($rol);

            $flag = false;

            foreach ($modulos_rol as $modulo) {
                if ($modulo['Id'] == $id_modulo) {
                    $flag = true;
                    break;
                }
            }

            if ($flag) {
                $this->losetas_model->updateRolModule($rol, $id_modulo);
            } else {
                $this->losetas_model->addRolModule($rol, $id_modulo);
            }
        }

        $modulos = $this->getActiveModules();
        $roles = $this->getActiveRoles();

        return $this->response->setJSON([
            'success' => true,
            'message' =>
                'Módulos asignados correctamente.',
            'modulos' => $modulos,
            'roles' => $roles
        ]);
    }

    /**
     * Metodo para gestionar la creacion de un rol nuevo.
     * 
     * @return ResponseInterface Resultado de la operacion.
     */
    public function createRol(): ResponseInterface
    {
        $data = [
            'Nombre' => $this->request->getVar('Nombre'),
        ];

        $this->roles_model->createRol($data);
        $table = $this->renderizarListaRoles();
        return $this->response->setBody($table);
    }

    /**
     * Metodo para obtener los roles activos.
     * 
     * @return string Listado de roles en formato HTML.
     */
    public function getActiveRoles(): string
    {
        $roles = $this->roles_model->getActiveRoles();

        $html = '';
        foreach ($roles as $key => $rol) {
            $html .= '<option value="' . $rol['Id'] . '">' . $rol['Nombre'] . '</option>';
        }

        return $html;
    }

    /**
     * Metodo para obtener todos los roles que se encuentran activos.
     * 
     * @return string Listado de roles en formato HTML.
     */
    public function getActiveModules(): string
    {
        $modulos = $this->losetas_model->getActiveModules();

        $html = '<option value="0">-- Seleccione un rol --</option>';
        foreach ($modulos as $key => $modulo) {
            $html .= '<option value="' . $modulo['Id'] . '">' . $modulo['Nombre'] . '</option>';
        }

        return $html;
    }
}

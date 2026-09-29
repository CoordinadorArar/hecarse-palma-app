<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LosetasModel;
use App\Models\UsuariosModel;
use App\Models\RolesModel;

class AccionesController extends BaseController
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
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'),$id_loseta);
        $modulos = $this->losetas_model->getAllModules();

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $tabla_acciones = $this->renderizarListaAcciones();
        $data['modulos'] = $modulos;
        $data['tabla_acciones'] = $tabla_acciones;

        return view('admin/acciones', $data);
    }

    private function renderizarListaAcciones(): string
    {
        $acciones = $this->losetas_model->getAcciones();

        $html = '<table id="tabla_acciones" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre Módulo</th>
                        <th>Nombre Acción</th>
                        <th>Fecha Creación</td>
                        <th>Acciones</th>
                    </tr>
                </thead>';

        $html .= '<tbody>';

        foreach ($acciones as $accion) {
            $estado = is_null($accion['FechaFinalizacion']) ? 'activo' : 'inactivo';
            $html .= '<tr class="' . $estado . '">
                    <td>' . htmlspecialchars($accion['Modulo']) . '</td>
                    <td>' . htmlspecialchars($accion['Nombre']) . '</td>
                    <td>' . htmlspecialchars($accion['FechaInicio']) . '</td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editAccionModal" onclick="obtenerAccion(' . $accion['Id'] . ')">Modificar</button>
                    </td>
                </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }
    

    public function getAccionById(int $id): ResponseInterface
    {
        $accion =  $this->losetas_model->getAccionById($id);
        return $this->response->setJSON(['success' => true, 'accion' => $accion]);
    }


    public function createAccion(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $data = [
            'IdModulo' => $this->request->getVar('Modulo'),
            'Nombre' => $this->request->getVar('Nombre'),
            'IdUsuario' => $this->session->get('usu_id'),
            'FechaInicio' => $fecha
        ];

        $this->losetas_model->createAccion($data);
        $table = $this->renderizarListaAcciones();
        return $this->response->setBody($table);
    }


    public function updateAccion(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $data = [
            'IdModulo' => $this->request->getVar('IdModulo'),
            'Nombre' => $this->request->getVar('Nombre'),
            'FechaModificacion' => $fecha,
            'FechaFinalizacion' => $this->request->getVar('FechaFinalizacion') == 0 ? $fecha : null
        ];

        $this->losetas_model->updateAccion($this->request->getVar('Id'), $data);
        $table = $this->renderizarListaAcciones();
        return $this->response->setBody($table);
    }


    public function findRolByName()
    {
        $term = $this->request->getPost('term');

        $roles = $this->losetas_model->findRolByName($term);

        $results = [];
        foreach ($roles as $rol) {
            $results[] = [
                'id' => $rol['Id'],
                'text' => $rol['Nombre']
            ];
        }

        return $this->response->setJSON($results);
    }


    public function getAllAccionByRol(): ResponseInterface
    {
        $all_acciones = $this->getAccionesNotRol($this->request->getVar('rolId'));
        $acciones_rol = $this->getAccionesByRol($this->request->getVar('rolId'));

        return $this->response->setJSON(['asignados' => $acciones_rol, 'todos' => $all_acciones]);
    }


    public function getAccionesNotRol($id_rol): string
    {
        $acciones = $this->losetas_model->getAccionesNotRol($id_rol);

        $html = '';
        foreach ($acciones as $accion) {
            $html .= '
                <li id="' . $accion['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $accion['Nombre'] . '
                    <button id="asignar_' .  esc($accion['Id'])  . '" type="button" class="btn btn-primary btn-sm" onclick="asginarAccion(' . esc($accion['Id']) . ')">Asignar</button>
                </li>
            ';
            
        }
        return $html;
    }


    public function getAccionesByRol($id_rol): string
    {
        $acciones = $this->losetas_model->getAccionesByRol($id_rol);

        $html = '';
        foreach ($acciones as $accion) {
            $html .= '
                <li id="' . $accion['IdAccion'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' .$accion['NombreAccion'] . '
                    <button id="quitar_' .  esc($accion['IdAccion'])  . '" type="button" class="btn btn-primary btn-sm" onclick="quitarAccion(' . esc($accion['IdAccion']) . ')">Quitar</button>
                </li>
            ';
        }

        return $html;
    }


    public function deleteRolAccion(): ResponseInterface
    {
        $id_accion = $this->request->getVar('accionId');
        $id_rol = $this->request->getVar('rolId');

        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $this->losetas_model->deleteAccionRol($id_accion, $id_rol, $fecha);
        $acciones_rol = $this->getAccionesByRol($id_rol);
        $all_acciones = $this->getAccionesNotRol($id_rol);

        return $this->response->setJSON(['asignados' => $acciones_rol, 'todos' => $all_acciones]);
    }


    public function addRolAccion(): ResponseInterface
    {
        $id_rol = $this->request->getVar('rolId');
        $id_accion = $this->request->getVar('accionId');
        $usuario = $this->session->get('usu_id');

        $acciones_rol = $this->losetas_model->getAllAccionesByRol($id_rol);

        $flag = false;

        foreach ($acciones_rol as $rol) {
            if ($rol['IdAccion'] == $id_accion) {
                $flag = true;
                break;
            }
        }

        if ($flag) {
            $this->losetas_model->updateRolAccion($id_rol, $id_accion);
        } else {
            $this->losetas_model->addRolAccion($id_rol, $id_accion, $usuario);
        }

        $acciones_rol = $this->getAccionesByRol($id_rol);
        $all_acciones = $this->getAccionesNotRol($id_rol);

        return $this->response->setJSON(['asignados' => $acciones_rol, 'todos' => $all_acciones]);
    }

}

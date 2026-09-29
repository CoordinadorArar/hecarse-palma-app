<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\LosetasModel;
use App\Models\UsuariosModel;
use App\Models\RolesModel;

class TabsController extends BaseController
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

        $tabla_tabs = $this->renderizarListaTabs();
        $data['modulos'] = $modulos;
        $data['tabla_tabs'] = $tabla_tabs;

        return view('admin/tabs', $data);
    }

    private function renderizarListaTabs(): string
    {
        $tabs = $this->losetas_model->getTabs();

        $html = '<table id="tabla_tabs" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre Módulo</th>
                        <th>Nombre Tabs</th>
                        <th>Fecha Creación</td>
                        <th>Acciones</th>
                    </tr>
                </thead>';

        $html .= '<tbody>';

        foreach ($tabs as $tab) {
            $estado = is_null($tab['FechaFinalizacion']) ? 'activo' : 'inactivo';
            $html .= '<tr class="' . $estado . '">
                    <td>' . htmlspecialchars($tab['Modulo']) . '</td>
                    <td>' . htmlspecialchars($tab['Nombre']) . '</td>
                    <td>' . htmlspecialchars($tab['FechaInicio']) . '</td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTabModal" onclick="obtenerTab(' . $tab['Id'] . ')">Modificar</button>
                    </td>
                </tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }
    

    public function getTabById(int $id): ResponseInterface
    {
        $tab =  $this->losetas_model->getTabById($id);
        return $this->response->setJSON(['success' => true, 'tab' => $tab]);
    }


    public function createTab(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $data = [
            'IdModulo' => $this->request->getVar('Modulo'),
            'Nombre' => $this->request->getVar('Nombre'),
            'IdUsuario' => $this->session->get('usu_id'),
            'FechaInicio' => $fecha
        ];

        $this->losetas_model->createTab($data);
        $table = $this->renderizarListaTabs();
        return $this->response->setBody($table);
    }


    public function updateTab(): ResponseInterface
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $data = [
            'IdModulo' => $this->request->getVar('IdModulo'),
            'Nombre' => $this->request->getVar('Nombre'),
            'FechaModificacion' => $fecha,
            'FechaFinalizacion' => $this->request->getVar('FechaFinalizacion') == 0 ? $fecha : null
        ];

        $this->losetas_model->updateTab($this->request->getVar('Id'), $data);
        $table = $this->renderizarListaTabs();
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


    public function getAllTabByRol(): ResponseInterface
    {
        $all_tabs = $this->getTabsNotRol($this->request->getVar('rolId'));
        $tabs_rol = $this->getTabsByRol($this->request->getVar('rolId'));

        return $this->response->setJSON(['asignados' => $tabs_rol, 'todos' => $all_tabs]);
    }


    public function getTabsNotRol($id_rol): string
    {
        $tabs = $this->losetas_model->getTabsNotRol($id_rol);

        $html = '';
        foreach ($tabs as $tab) {
            $html .= '
                <li id="' . $tab['Id'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' . $tab['Nombre'] . '
                    <button id="asignar_' .  esc($tab['Id'])  . '" type="button" class="btn btn-primary btn-sm" onclick="asginarTab(' . esc($tab['Id']) . ')">Asignar</button>
                </li>
            ';
            
        }
        return $html;
    }


    public function getTabsByRol($id_rol): string
    {
        $tabs = $this->losetas_model->getTabsByRol($id_rol);

        $html = '';
        foreach ($tabs as $tab) {
            $html .= '
                <li id="' . $tab['IdTab'] . '" class="list-group-item d-flex justify-content-between align-items-center">
                    ' .$tab['NombreTab'] . '
                    <button id="quitar_' .  esc($tab['IdTab'])  . '" type="button" class="btn btn-primary btn-sm" onclick="quitarTab(' . esc($tab['IdTab']) . ')">Quitar</button>
                </li>
            ';
        }

        return $html;
    }


    public function deleteRolTab(): ResponseInterface
    {
        $id_tab = $this->request->getVar('tabId');
        $id_rol = $this->request->getVar('rolId');

        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $this->losetas_model->deleteTabRol($id_tab, $id_rol, $fecha);
        $tabs_rol = $this->getTabsByRol($id_rol);
        $all_tabs = $this->getTabsNotRol($id_rol);

        return $this->response->setJSON(['asignados' => $tabs_rol, 'todos' => $all_tabs]);
    }


    public function addRolTab(): ResponseInterface
    {
        $id_rol = $this->request->getVar('rolId');
        $id_tab = $this->request->getVar('tabId');
        $usuario = $this->session->get('usu_id');

        $tabs_rol = $this->losetas_model->getAllTabsByRol($id_rol);

        $flag = false;

        foreach ($tabs_rol as $rol) {
            if ($rol['IdTab'] == $id_tab) {
                $flag = true;
                break;
            }
        }

        if ($flag) {
            $this->losetas_model->updateRolTab($id_rol, $id_tab);
        } else {
            $this->losetas_model->addRolTab($id_rol, $id_tab, $usuario);
        }

        $tabs_rol = $this->getTabsByRol($id_rol);
        $all_tabs = $this->getTabsNotRol($id_rol);

        return $this->response->setJSON(['asignados' => $tabs_rol, 'todos' => $all_tabs]);
    }

}

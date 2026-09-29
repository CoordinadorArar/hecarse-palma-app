<?php

namespace App\Controllers\Comercial;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTime;

use App\Models\LosetasModel;
use App\Models\ReportesPowerBiModel;
use App\Models\UsuariosModel;
use App\Models\RolesModel;

class ReportesPowerBiController extends BaseController
{
    private $session;

    private $losetas_model;

    private $reportes_power_bi_model;

    private $usuarios_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->reportes_power_bi_model = new ReportesPowerBiModel();
        $this->usuarios_model = new UsuariosModel();
        $this->roles_model = new RolesModel();
    }

    /**
     * Metodo para renderizar la vista de todos los modulos.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los modulos.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        // Obtener el rol del usuario
        $usuarioId = $this->session->get('usu_id');
        $rol = $this->usuarios_model->obtenerRolUsuario($usuarioId);

        $tabla_reportes = $this->renderizarTablaReportes();
        $usuarios_activos = $this->usuarios_model->getActiveUsers();
        $tabsPermitidos = $this->roles_model->obtenerTabsPermitidos($rol['Id']);

        $data['tabla_reportes'] = $tabla_reportes;
        $data['usuarios_activos'] = $usuarios_activos;
        $data['tabs_permitidos'] = $tabsPermitidos;

        return view('comercial/reportesPowerBi', $data);
    }

    /**
     * Metodo para renderizar la vista de todos los modulos.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los modulos.
     */
    public function adminReportes($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        $reportes = $this->renderizarTablaReportes();
        $usuariosActivos = $this->usuarios_model->getActivedUsers();

        $data['lista_reportes'] = $reportes;
        $data['lista_usuarios'] = $usuariosActivos;

        return view('comercial/adminReportes', $data);
    }

    public function renderizarTablaReportes(): string
    {
        $reportes = $this->reportes_power_bi_model->obtenerListaReportes();

        $tabla = '<table class="table table-sm table-striped table-hover" style="width:100%" id="tabla_reportes">';
        $tabla .= '<thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Fecha de creacion</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>';
        $tabla .= '<tbody>';

        /**Validación de rol, solo el administrador o desarrollador puede ver todos los reportes */
        $datos_usuario = $this->usuarios_model->getUserById($this->session->get('usu_id'));
        $roles_usuario = $this->roles_model->getRolesByUser($this->session->get('usu_id'));

        $bandera_rol = false;
        $accionesPermitidas = [];

        foreach ($roles_usuario as $rol) {
            if ($rol['Nombre'] == 'Administrador' || $rol['Nombre'] == 'Desarrollador' || $rol['Nombre'] == 'Soporte') {
                $bandera_rol = true;
            }

            $acciones = $this->roles_model->obtenerAccionesPermitidas($rol['Id']);
            foreach ($acciones as $accion) {
                $accionesPermitidas[] = $accion;
            }
        }

        //Eliminar duplicados
        $accionesPermitidas = array_unique($accionesPermitidas);

        foreach ($reportes as $item) {
            $usuarios = json_decode($item['Usuarios'], true);

            foreach ($usuarios as $usuario) {
                if ($datos_usuario['Documento'] == $usuario['id'] || $bandera_rol) {
                    $fechaInicioFormateada = new DateTime($item['FechaInicio']);

                    $tabla .= '<tr>';
                    $tabla .= '<td>' . htmlspecialchars(trim($item['Nombre'])) . '</td>';
                    $tabla .= '<td>' . htmlspecialchars(trim($item['Descripcion'])) . '</td>';
                    $tabla .= '<td>' . htmlspecialchars(trim($fechaInicioFormateada->format('d-m-Y'))) . '</td>';
                    $tabla .= '<td class="text-center">
                                <div class="filter">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="dropdown"><i class="bi bi-gear"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                        <li>
                                            <a href="#" class="dropdown-item" type="button" onclick="abrirReporte(\'' . htmlspecialchars($item['Enlace']) . '\')"><i class="bi bi-search text-info"></i> Abrir</a>
                                        </li>';

                    if (in_array('editar-reporte', $accionesPermitidas)) {
                        $tabla .= '<li>
                                            <a href="#" class="dropdown-item" type="button" data-bs-toggle="modal" onclick="editarReporte(\'' . htmlspecialchars($item['Id']) . '\')"><i class="bi bi-pencil text-primary"></i> Editar</a>
                                        </li>';
                    }

                    if (in_array('eliminar-reporte', $accionesPermitidas)) {
                        $tabla .= '<li>
                                            <a href="#" class="dropdown-item" type="button" onclick="eliminarReporte(\'' . htmlspecialchars($item['Id']) . '\')"><i class="bi bi-trash text-danger"></i> Eliminar</a>
                                        </li>';
                    }
                    $tabla .= '
                                    </ul>
                                </div>
                            </td>';
                    $tabla .= '</tr>';
                }
            }
        }

        $tabla .= '</tbody>';
        $tabla .= '</table>';

        return $tabla;
    }

    /**
     * Metodo para obtener la informacion de un reporte en especifico.
     * 
     * @param int $id Identificador del reporte.
     * @return ResponseInterface Informacion del reporte formato JSON
     */
    public function getReporteById(int $id): ResponseInterface
    {
        $reporte = $this->reportes_power_bi_model->getReporteById($id);
        return $this->response->setJSON(['success' => true, 'reporte' => $reporte]);
    }

    /**
     * Metodo para obtener el listado de usuarios activos, que se 
     * mostraran en la vista de reportes.
     */
    public function getActivedUsers(): string
    {
        $usuarios = $this->usuarios_model->getActivedUsers();
        return json_encode($usuarios);
    }

    /**Metodo para crear nuevo reporte */
    public function createReporte()
    {
        $fecha = date('Y-m-d H:i:s');

        $jsonUsuarios = [];
        if (!empty($this->request->getVar('UsuariosAsignados'))) {
            foreach (json_decode($this->request->getVar('UsuariosAsignados')) as $usuario) {
                $infoUsuario = $this->usuarios_model->getUserById($usuario);

                if (empty($infoUsuario['Documento'])) {
                    return $this->response->setJSON(['success' => false, 'text' => 'El usuario no tiene documento, es necesario para hacer la asignación']);
                }

                $jsonUsuarios[] = [
                    'id' => $infoUsuario['Documento'],
                    'text' => $infoUsuario['Nombre'] . ' ' . $infoUsuario['Apellido']
                ];
            }
        }

        $data = [
            'Nombre' => $this->request->getVar('Nombre'),
            'Descripcion' => $this->request->getVar('Descripcion'),
            'FechaInicio' => explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1],
            'Enlace' => $this->request->getVar('Enlace'),
            'Usuarios' => json_encode($jsonUsuarios)
        ];

        $this->reportes_power_bi_model->createReporte($data);

        return $this->response->setJSON(['success' => true, 'text' => 'Reporte creado correctamente']);
    }

    /**
     * Metodo para gestionar los usuarios de un reporte
     */
    public function asignarUsuarios()
    {
        $accion = $this->request->getVar('accion');
        $Id = $this->request->getVar('Id');
        $id_reporte = $this->request->getVar('IdReporte');

        /**Datos del usuario a gestionar */
        $usuario_gestion = $this->usuarios_model->getUserById($Id);

        if (empty($usuario_gestion['Documento'])) {
            return $this->response->setJSON(['success' => false, 'text' => 'El usuario no tiene documento, es necesario para hacer la asignación']);
        }

        /**Datos del reporte a gestionar */
        $reporte_gestion = $this->reportes_power_bi_model->getReporteById($id_reporte);
        $usuarios_reporte = json_decode($reporte_gestion['Usuarios'], true);

        if (!is_array($usuarios_reporte)) {
            $usuarios_reporte = [];
        }

        if ($accion == 'asignar') {
            /** Verificar si ya está asignado */
            foreach ($usuarios_reporte as $usuario) {
                if ($usuario['id'] == $usuario_gestion['Documento']) {
                    return $this->response->setJSON(['success' => false, 'text' => 'El usuario ya está asignado a este tablero']);
                }
            }

            // Agregar el nuevo usuario al array
            $usuarios_reporte[] = [
                'id' => $usuario_gestion['Documento'],
                'text' => $usuario_gestion['Nombre'] . ' ' . $usuario_gestion['Apellido']
            ];
        } elseif ($accion == 'remover') {
            // Remover el usuario si está en el JSON
            $usuarios_reporte = array_filter($usuarios_reporte, function ($usuario) use ($usuario_gestion) {
                return $usuario['id'] !== $usuario_gestion['Documento'];
            });

            // Reindexar el array
            $usuarios_reporte = array_values($usuarios_reporte);
        } else {
            return $this->response->setJSON(['success' => false, 'text' => 'Acción no válida']);
        }

        // Actualizar el JSON en la base de datos
        $data = [];
        $data['Usuarios'] = json_encode($usuarios_reporte);
        $fecha = date('Y-m-d H:i:s');
        $data['FechaModificacion'] = explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1];

        $this->reportes_power_bi_model->updateReporte($id_reporte, $data);

        $accion_texto = $accion == 'asignar' ? 'asignado' : 'removido';

        return $this->response->setJSON(['success' => true, 'text' => 'Usuario ' . $accion_texto . ' correctamente']);
    }

    /**Actualizar reporte */
    public function updateReporte()
    {
        $id_reporte = $this->request->getVar('IdReporte');

        $data = [];
        $data['Nombre'] = $this->request->getVar('Nombre');
        $data['Enlace'] = $this->request->getVar('Enlace');
        $data['Descripcion'] = $this->request->getVar('Descripcion');
        $data['FechaFinalizacion'] = $this->request->getVar('FechaFinalizion');

        $this->reportes_power_bi_model->updateReporte($id_reporte, $data);

        $table = $this->renderizarTablaReportes();
        return $this->response->setJSON(['success' => true, 'text' => 'Reporte actualizado correctamente', 'table' => $table]);
    }

    /**Metodo para eliminar un reporte */
    public function deleteReporte($id_reporte)
    {
        $this->reportes_power_bi_model->deleteReporte($id_reporte);

        $table = $this->renderizarTablaReportes();
        return $this->response->setJSON(['success' => true, 'text' => 'Reporte eliminado correctamente', 'table' => $table]);
    }
}

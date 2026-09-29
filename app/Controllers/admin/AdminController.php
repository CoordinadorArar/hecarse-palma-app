<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LosetasModel;
use App\Models\AdminModel;
use CodeIgniter\HTTP\RedirectResponse;

use CodeIgniter\HTTP\ResponseInterface;

class AdminController extends BaseController
{
    private $session;

    private $losetas_model;

    private $admin_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->admin_model = new AdminModel();
    }

    /**
     * Metodo para renderizar la vista inicial del modulo "administracion".
     * Se prepara el nombre de usuario y la imagen de usuario para renderizarla en la vista.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de login.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('admin/onboarding', $data);
    }

    /**
     * Metodo para consultar departamentos
     */
    public function getDepartamentos(): ResponseInterface
    {
        $departamentos = $this->admin_model->getDepartamentos();

        return $this->response->setJSON(['success' => true, 'departamentos' => $departamentos]);
    }

    /**
     * Metodo para consultar ciudades
     */
    public function getCiudades(): ResponseInterface
    {

        $id_departamento = $this->request->getVar('idDepartamento');

        $ciudades = $this->admin_model->getCiudades($id_departamento);

        return $this->response->setJSON(['success' => true, 'ciudades' => $ciudades]);
    }

}

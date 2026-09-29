<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LosetasModel;
use App\Models\SiteModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class SiteController extends BaseController
{
    private $session;

    private $losetas_model;
    private $site_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->site_model = new SiteModel();
    }

    /**
     * Metodo para renderizar la vista de configuración del sitio.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los usuarios.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('admin/site', $data);
    }

    /**
     * Guardar configuración del sitio
     */
    public function guardar(): ResponseInterface
    {
        $data = [
            'nombre_app' => $this->request->getPost('nombre_app'),
            'color_primary' => $this->request->getPost('color_primary')
        ];

        // Manejar archivos
        $logoFile = $this->request->getFile('logo');
        $logoClaroFile = $this->request->getFile('logo_claro');
        $faviconFile = $this->request->getFile('favicon');

        if ($logoFile && $logoFile->isValid() && !$logoFile->hasMoved()) {
            $logoName = 'logo_' . time() . '.' . $logoFile->getClientExtension();
            $logoFile->move('public/uploads/config', $logoName);
            $data['logo'] = 'public/uploads/config/' . $logoName;
        }

        if ($logoClaroFile && $logoClaroFile->isValid() && !$logoClaroFile->hasMoved()) {
            $logoClaroName = 'logo_claro_' . time() . '.' . $logoClaroFile->getClientExtension();
            $logoClaroFile->move('public/uploads/config', $logoClaroName);
            $data['logo_claro'] = 'public/uploads/config/' . $logoClaroName;
        }

        if ($faviconFile && $faviconFile->isValid() && !$faviconFile->hasMoved()) {
            $faviconName = 'favicon_' . time() . '.' . $faviconFile->getClientExtension();
            $faviconFile->move('public/uploads/config', $faviconName);
            $data['favicon'] = 'public/uploads/config/' . $faviconName;
        }

        $result = $this->site_model->upsertConfiguracion($data);

        if ($result) {
            $img = [                
                'logo_claro' => $data['logo_claro'] ?? null,
                'favicon' => $data['favicon'] ?? null
            ];
            return $this->response->setJSON(['success' => true, 'message' => 'Configuración guardada correctamente', 'img' => $img]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al guardar configuración'])->setStatusCode(500);
        }
    }
}

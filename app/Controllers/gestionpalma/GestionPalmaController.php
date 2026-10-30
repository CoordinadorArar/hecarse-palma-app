<?php

namespace App\Controllers\gestionpalma;

use App\Controllers\BaseController;
use App\Models\GestionPalmaModel;
use CodeIgniter\HTTP\RedirectResponse;

class GestionPalmaController extends BaseController
{
    protected $session;

    public function __construct()
    {
        $this->session = session();
    }

    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $modelo     = new GestionPalmaModel();
        $fallo      = false;

        try {
            $empresas = $modelo->getEmpresas();
        } catch (\Throwable $e) {
            log_message('error', 'GestionPalmaController::index [empresas] ' . $e->getMessage());
            $empresas = [];
            $fallo    = true;
        }

        $empresaSel = $this->empresaSeleccionada($empresas);

        $data['empresas']    = $empresas;
        $data['empresa_sel'] = $empresaSel;
        $data['resumen']     = $modelo->resumen($empresaSel);

        if ($fallo) {
            $data['resumen']['fallos'][] = 'empresas';
            $data['resumen']['avisos'] = ['No fue posible cargar las empresas.'];
        }

        return view('gestionpalma/onboarding', $data);
    }

    protected function empresaSeleccionada(array $empresas): int
    {
        $solicitada = (int) $this->request->getGet('empresa');

        foreach ($empresas as $empresa) {
            if ($solicitada > 0 && (int) $empresa['id'] === $solicitada) {
                return $solicitada;
            }
        }

        return $empresas ? (int) $empresas[0]['id'] : 0;
    }

    protected function enConstruccion($id_loseta, $modulo, $icono, $descripcion): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $data['title'] = 'Gestión Palma · ' . $modulo;
        $data['modulo'] = $modulo;
        $data['icono'] = $icono;
        $data['descripcion'] = $descripcion;

        return view('gestionpalma/en_construccion', $data);
    }
}

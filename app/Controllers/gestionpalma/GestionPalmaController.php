<?php

namespace App\Controllers\gestionpalma;

use App\Controllers\BaseController;
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

<?php

namespace App\Controllers\Transacciones;

use App\Controllers\BaseController;
use App\Models\TransaccionesResumenModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Controlador de aterrizaje ("onboarding") de la loseta "Transacciones".
 */
class TransaccionesController extends BaseController
{
    private $session;

    public function __construct()
    {
        $this->session = session();
    }

    /**
     * Vista inicial de la loseta "Transacciones".
     *
     * @param int $id_loseta Identificador de la loseta.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $modelo = new TransaccionesResumenModel();
        $fallo  = false;

        try {
            $empresas = $modelo->getEmpresas();
        } catch (\Throwable $e) {
            log_message('error', 'TransaccionesController::index [empresas] ' . $e->getMessage());
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

        return view('transacciones/onboarding', $data);
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
}
